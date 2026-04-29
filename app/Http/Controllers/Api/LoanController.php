<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\MemberShare;
use App\Services\LoanScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(private LoanScheduleService $scheduleService) {}

    public function index(Request $request)
    {
        $query = Loan::where('company_id', $request->user()->company_id)
            ->with(['borrower', 'loanProduct', 'loanOfficer']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('loan_no', 'like', "%{$search}%")
                  ->orWhereHas('borrower', fn($b) =>
                      $b->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                  );
            });
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_id'          => 'required|exists:borrowers,id',
            'loan_product_id'      => 'required|exists:loan_products,id',
            'loan_officer_id'      => 'nullable|exists:users,id',
            'principal_amount'     => 'required|numeric|min:1',
            'interest_rate'        => 'required|numeric|min:0',
            'interest_method'      => 'required|in:flat_rate,reducing_balance',
            'repayment_frequency'  => 'required|in:daily,weekly,biweekly,monthly,quarterly',
            'term'                 => 'required|integer|min:1',
            'term_unit'            => 'required|in:days,weeks,months',
            'application_date'     => 'required|date',
            'notes'                => 'nullable|string',
        ]);

        $companyId = $request->user()->company_id;

        // Generate loan_no: LN-00001
        $last   = Loan::where('company_id', $companyId)->orderBy('id', 'desc')->first();
        $next   = $last ? ((int) ltrim(substr($last->loan_no, 3), '0') + 1) : 1;
        $loanNo = 'LN-' . str_pad($next, 5, '0', STR_PAD_LEFT);

        $loan = Loan::create(array_merge($validated, [
            'company_id' => $companyId,
            'loan_no'    => $loanNo,
            'status'     => 'pending',
        ]));

        return response()->json($loan->load(['borrower', 'loanProduct', 'loanOfficer']), 201);
    }

    public function show(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        return response()->json(
            $loan->load(['borrower.nextOfKin', 'loanProduct', 'loanOfficer', 'schedule', 'repayments.receivedBy', 'charges', 'penalties'])
        );
    }

    /**
     * Approve a pending loan.
     */
    public function approve(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Only pending loans can be approved.'], 422);
        }

        // Eligibility check: max loan = 3× member's share value
        $share = MemberShare::where('company_id', $loan->company_id)
            ->where('borrower_id', $loan->borrower_id)
            ->where('status', 'active')
            ->first();

        $shareValue = $share ? ($share->shares * $share->amount_per_share) : 0;
        $maxAllowed = $shareValue * 3;

        if ($shareValue > 0 && $loan->principal_amount > $maxAllowed) {
            return response()->json([
                'message' => "Loan amount exceeds eligibility limit. Member's share value is K{$shareValue}, maximum loan allowed is K{$maxAllowed}.",
                'share_value' => $shareValue,
                'max_allowed' => $maxAllowed,
            ], 422);
        }

        $loan->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json($loan->fresh());
    }

    /**
     * Reject a pending loan.
     */
    public function reject(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        $request->validate(['reason' => 'nullable|string']);

        if ($loan->status !== 'pending') {
            return response()->json(['message' => 'Only pending loans can be rejected.'], 422);
        }

        $loan->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return response()->json($loan->fresh());
    }

    /**
     * Disburse an approved loan — calculates repayment schedule.
     */
    public function disburse(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        $validated = $request->validate([
            'disbursement_date'      => 'required|date',
            'first_repayment_date'   => 'required|date|after:disbursement_date',
            'disbursement_method'    => 'required|in:cash,mobile_money,bank',
            'disbursement_reference' => 'nullable|string|max:100',
        ]);

        if ($loan->status !== 'approved') {
            return response()->json(['message' => 'Only approved loans can be disbursed.'], 422);
        }

        // Calculate maturity date
        $maturityDate = $this->calculateMaturityDate(
            $validated['first_repayment_date'],
            $loan->term,
            $loan->repayment_frequency
        );

        $loan->update(array_merge($validated, [
            'status'       => 'active',
            'disbursed_by' => $request->user()->id,
            'disbursed_at' => now(),
            'maturity_date'=> $maturityDate,
        ]));

        // Generate the repayment schedule
        $this->scheduleService->generate($loan->fresh());

        return response()->json(
            $loan->fresh()->load(['schedule', 'borrower', 'loanProduct'])
        );
    }

    /**
     * Top-up: add extra principal to an active loan and regenerate schedule.
     */
    public function topUp(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        if ($loan->status !== 'active') {
            return response()->json(['message' => 'Only active loans can be topped up.'], 422);
        }

        $validated = $request->validate([
            'top_up_amount'        => 'required|numeric|min:1',
            'new_term'             => 'nullable|integer|min:1',
            'first_repayment_date' => 'required|date',
            'notes'                => 'nullable|string',
        ]);

        $extraPrincipal  = (float) $validated['top_up_amount'];
        $newPrincipal    = (float) $loan->outstanding_balance + $extraPrincipal;
        $newTerm         = $validated['new_term'] ?? $loan->term;

        // Recalculate totals
        $maturityDate = $this->calculateMaturityDate(
            $validated['first_repayment_date'],
            $newTerm,
            $loan->repayment_frequency
        );

        // Drop existing future (unpaid) schedule rows
        $loan->schedule()->whereIn('status', ['pending', 'partial', 'overdue'])->delete();

        $loan->update([
            'principal_amount'     => $newPrincipal,
            'term'                 => $newTerm,
            'first_repayment_date' => $validated['first_repayment_date'],
            'maturity_date'        => $maturityDate,
            'notes'                => $validated['notes'] ?? $loan->notes,
        ]);

        $this->scheduleService->generate($loan->fresh(), $newPrincipal);

        return response()->json($loan->fresh()->load(['schedule', 'borrower', 'loanProduct']));
    }

    /**
     * Restructure: change term/rate/frequency and regenerate remaining schedule.
     */
    public function restructure(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        if ($loan->status !== 'active') {
            return response()->json(['message' => 'Only active loans can be restructured.'], 422);
        }

        $validated = $request->validate([
            'new_term'             => 'required|integer|min:1',
            'new_interest_rate'    => 'nullable|numeric|min:0',
            'new_frequency'        => 'nullable|in:daily,weekly,biweekly,monthly,quarterly',
            'first_repayment_date' => 'required|date',
            'notes'                => 'nullable|string',
        ]);

        $newTerm      = $validated['new_term'];
        $newRate      = $validated['new_interest_rate'] ?? $loan->interest_rate;
        $newFrequency = $validated['new_frequency'] ?? $loan->repayment_frequency;
        $outstanding  = (float) $loan->outstanding_balance;

        $maturityDate = $this->calculateMaturityDate(
            $validated['first_repayment_date'],
            $newTerm,
            $newFrequency
        );

        // Drop unpaid schedule rows
        $loan->schedule()->whereIn('status', ['pending', 'partial', 'overdue'])->delete();

        $loan->update([
            'term'                 => $newTerm,
            'interest_rate'        => $newRate,
            'repayment_frequency'  => $newFrequency,
            'first_repayment_date' => $validated['first_repayment_date'],
            'maturity_date'        => $maturityDate,
            'notes'                => $validated['notes'] ?? $loan->notes,
        ]);

        $this->scheduleService->generate($loan->fresh(), $outstanding);

        return response()->json($loan->fresh()->load(['schedule', 'borrower', 'loanProduct']));
    }

    /**
     * Write off a loan (bad debt).
     */
    public function writeOff(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        if (!in_array($loan->status, ['active', 'defaulted'])) {
            return response()->json(['message' => 'Only active or defaulted loans can be written off.'], 422);
        }

        $loan->update(['status' => 'written_off']);

        return response()->json($loan->fresh());
    }

    /**
     * Preview repayment schedule before disbursement (no DB writes).
     */
    public function previewSchedule(Request $request)
    {
        $request->validate([
            'principal_amount'    => 'required|numeric|min:1',
            'interest_rate'       => 'required|numeric|min:0',
            'interest_method'     => 'required|in:flat_rate,reducing_balance',
            'repayment_frequency' => 'required|in:daily,weekly,biweekly,monthly,quarterly',
            'term'                => 'required|integer|min:1',
            'first_repayment_date'=> 'required|date',
        ]);

        $schedule = $this->scheduleService->preview(
            (float) $request->principal_amount,
            (float) $request->interest_rate,
            $request->interest_method,
            $request->repayment_frequency,
            (int)   $request->term,
            $request->first_repayment_date
        );

        return response()->json($schedule);
    }

    private function calculateMaturityDate(string $firstRepaymentDate, int $term, string $frequency): string
    {
        $date = Carbon::parse($firstRepaymentDate);
        for ($i = 1; $i < $term; $i++) {
            $date = match ($frequency) {
                'daily'     => $date->addDay(),
                'weekly'    => $date->addWeek(),
                'biweekly'  => $date->addWeeks(2),
                'monthly'   => $date->addMonth(),
                'quarterly' => $date->addMonths(3),
            };
        }
        return $date->toDateString();
    }

    private function authorizeCompany(Request $request, Loan $loan): void
    {
        if ($loan->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}
