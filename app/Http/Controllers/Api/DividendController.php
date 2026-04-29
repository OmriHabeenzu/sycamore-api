<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dividend;
use App\Models\DividendAllocation;
use App\Models\Expense;
use App\Models\LoanCharge;
use App\Models\MemberShare;
use App\Models\Penalty;
use App\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DividendController extends Controller
{
    // GET /dividends
    public function index(Request $request)
    {
        $dividends = Dividend::where('company_id', $request->user()->company_id)
            ->withCount('allocations')
            ->orderByDesc('year')
            ->get();

        return response()->json($dividends);
    }

    // GET /dividends/{dividend}
    public function show(Request $request, Dividend $dividend)
    {
        $this->authorise($request, $dividend);

        return response()->json(
            $dividend->load(['allocations.borrower:id,first_name,last_name,borrower_no'])
        );
    }

    // POST /dividends/calculate — compute surplus for a year and preview allocations
    public function calculate(Request $request)
    {
        $request->validate(['year' => 'required|integer|min:2000|max:2100']);

        $companyId = $request->user()->company_id;
        $year      = $request->year;
        $from      = "{$year}-01-01";
        $to        = "{$year}-12-31";

        // Revenue
        $interest  = Repayment::where('company_id', $companyId)->whereBetween('payment_date', [$from, $to])->sum('interest_amount');
        $fees      = LoanCharge::whereHas('loan', fn($q) => $q->where('company_id', $companyId))
                        ->where('is_paid', true)->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->sum('amount');
        $penalties = Penalty::whereHas('loan', fn($q) => $q->where('company_id', $companyId))
                        ->where('is_paid', true)->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])->sum('amount');
        $totalRevenue = round($interest + $fees + $penalties, 2);

        // Expenses
        $totalExpenses = round(Expense::where('company_id', $companyId)->whereBetween('expense_date', [$from, $to])->sum('amount'), 2);

        $surplus = round($totalRevenue - $totalExpenses, 2);

        // Member shares (active members with shares > 0)
        $shares = MemberShare::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('shares', '>', 0)
            ->with('borrower:id,first_name,last_name,borrower_no')
            ->get();

        $totalShares = $shares->sum('shares');
        $perShareRate = $totalShares > 0 && $surplus > 0
            ? round($surplus / $totalShares, 4) : 0;

        $allocations = $shares->map(fn($s) => [
            'borrower_id' => $s->borrower_id,
            'member'      => $s->borrower->first_name . ' ' . $s->borrower->last_name,
            'member_no'   => $s->borrower->borrower_no,
            'shares'      => $s->shares,
            'amount'      => round($s->shares * $perShareRate, 2),
        ]);

        return response()->json([
            'year'                => $year,
            'revenue'             => $totalRevenue,
            'expenses'            => $totalExpenses,
            'surplus'             => $surplus,
            'total_shares'        => round($totalShares, 2),
            'per_share_rate'      => $perShareRate,
            'allocations'         => $allocations,
            'eligible_members'    => $shares->count(),
        ]);
    }

    // POST /dividends — save a dividend declaration
    public function store(Request $request)
    {
        $request->validate([
            'year'                 => 'required|integer|min:2000|max:2100',
            'total_surplus'        => 'required|numeric',
            'distributable_amount' => 'required|numeric|min:0',
            'per_share_rate'       => 'required|numeric|min:0',
            'notes'                => 'nullable|string',
        ]);

        $companyId = $request->user()->company_id;

        if (Dividend::where('company_id', $companyId)->where('year', $request->year)->exists()) {
            return response()->json(['message' => "A dividend for {$request->year} already exists."], 422);
        }

        return DB::transaction(function () use ($request, $companyId) {
            $dividend = Dividend::create([
                'company_id'           => $companyId,
                'year'                 => $request->year,
                'total_surplus'        => $request->total_surplus,
                'distributable_amount' => $request->distributable_amount,
                'per_share_rate'       => $request->per_share_rate,
                'notes'                => $request->notes,
                'status'               => 'draft',
                'created_by'           => $request->user()->id,
            ]);

            // Create allocations for all active members with shares
            $shares = MemberShare::where('company_id', $companyId)
                ->where('status', 'active')->where('shares', '>', 0)->get();

            foreach ($shares as $s) {
                $dividend->allocations()->create([
                    'borrower_id' => $s->borrower_id,
                    'shares'      => $s->shares,
                    'amount'      => round($s->shares * $request->per_share_rate, 2),
                ]);
            }

            return response()->json($dividend->load('allocations'), 201);
        });
    }

    // POST /dividends/{dividend}/approve
    public function approve(Request $request, Dividend $dividend)
    {
        $this->authorise($request, $dividend);
        if ($dividend->status !== 'draft') return response()->json(['message' => 'Only draft dividends can be approved.'], 422);
        $dividend->update(['status' => 'approved', 'approved_at' => now()->toDateString()]);
        return response()->json($dividend);
    }

    // POST /dividends/{dividend}/distribute
    public function distribute(Request $request, Dividend $dividend)
    {
        $this->authorise($request, $dividend);
        if ($dividend->status !== 'approved') return response()->json(['message' => 'Dividend must be approved first.'], 422);
        $dividend->update(['status' => 'distributed', 'distributed_at' => now()->toDateString()]);
        $dividend->allocations()->update(['is_paid' => true, 'paid_at' => now()->toDateString()]);
        return response()->json($dividend);
    }

    private function authorise(Request $request, Dividend $dividend): void
    {
        if ($dividend->company_id !== $request->user()->company_id) abort(403);
    }
}
