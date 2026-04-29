<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Repayment;
use App\Services\RepaymentService;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
    public function __construct(private RepaymentService $repaymentService) {}

    /**
     * List repayments for a loan.
     */
    public function index(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        return response()->json(
            $loan->repayments()->with('receivedBy')->orderBy('payment_date', 'desc')->get()
        );
    }

    /**
     * Record a new repayment against a loan.
     */
    public function store(Request $request, Loan $loan)
    {
        $this->authorizeCompany($request, $loan);

        if (!$loan->isDisbursed()) {
            return response()->json(['message' => 'Loan is not active.'], 422);
        }

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'payment_method'   => 'required|in:cash,mobile_money,bank',
            'reference_number' => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        $repayment = $this->repaymentService->record($loan, array_merge($validated, [
            'received_by' => $request->user()->id,
        ]));

        // Refresh arrears after payment
        $this->repaymentService->refreshArrears($loan->fresh());

        return response()->json($repayment->load('receivedBy'), 201);
    }

    /**
     * Fetch a single repayment (for receipt printing).
     */
    public function show(Request $request, Repayment $repayment)
    {
        if ($repayment->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }

        return response()->json(
            $repayment->load(['loan.borrower', 'loan.loanProduct', 'receivedBy'])
        );
    }

    /**
     * All repayments across a company (for the repayments page).
     */
    public function companyIndex(Request $request)
    {
        $query = Repayment::where('company_id', $request->user()->company_id)
            ->with(['loan.borrower', 'receivedBy']);

        if ($request->date_from) $query->whereDate('payment_date', '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate('payment_date', '<=', $request->date_to);

        return response()->json(
            $query->orderBy('payment_date', 'desc')->paginate(20)
        );
    }

    private function authorizeCompany(Request $request, Loan $loan): void
    {
        if ($loan->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}
