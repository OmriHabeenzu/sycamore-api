<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanCharge;
use App\Models\LoanSchedule;
use App\Models\MemberShare;
use App\Models\Penalty;
use App\Models\Repayment;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Collections Sheet — all repayments in a date range, grouped by day.
     */
    public function collectionsSheet(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $companyId = $request->user()->company_id;

        $repayments = Repayment::where('company_id', $companyId)
            ->whereBetween('payment_date', [$request->date_from, $request->date_to])
            ->with(['loan.borrower', 'loan.loanProduct', 'receivedBy'])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'payment_date'    => $r->payment_date,
                'loan_no'         => $r->loan->loan_no,
                'borrower'        => $r->loan->borrower->first_name . ' ' . $r->loan->borrower->last_name,
                'phone'           => $r->loan->borrower->phone,
                'product'         => $r->loan->loan_product->name,
                'amount'          => $r->amount,
                'principal'       => $r->principal_amount,
                'interest'        => $r->interest_amount,
                'fees'            => $r->fee_amount,
                'payment_method'  => $r->payment_method,
                'reference'       => $r->reference_number,
                'received_by'     => $r->receivedBy?->name,
            ]);

        return response()->json([
            'data'  => $repayments,
            'total' => $repayments->sum('amount'),
        ]);
    }

    /**
     * Portfolio Report — snapshot of all active loans.
     */
    public function portfolio(Request $request)
    {
        $companyId = $request->user()->company_id;

        $loans = Loan::where('company_id', $companyId)
            ->whereIn('status', ['active', 'disbursed', 'defaulted'])
            ->with(['borrower', 'loanProduct', 'loanOfficer'])
            ->orderBy('disbursement_date')
            ->get()
            ->map(fn($l) => [
                'loan_no'          => $l->loan_no,
                'borrower'         => $l->borrower->first_name . ' ' . $l->borrower->last_name,
                'phone'            => $l->borrower->phone,
                'product'          => $l->loan_product->name,
                'principal'        => $l->principal_amount,
                'total_due'        => $l->total_amount_due,
                'total_paid'       => $l->total_paid,
                'outstanding'      => $l->outstanding_balance,
                'disbursement_date'=> $l->disbursement_date,
                'maturity_date'    => $l->maturity_date,
                'days_in_arrears'  => $l->days_in_arrears,
                'is_overdue'       => $l->is_overdue,
                'officer'          => $l->loanOfficer?->name,
                'status'           => $l->status,
            ]);

        return response()->json([
            'data'              => $loans,
            'total_outstanding' => $loans->sum('outstanding'),
            'total_disbursed'   => $loans->sum('principal'),
            'par_amount'        => $loans->where('is_overdue', true)->sum('outstanding'),
        ]);
    }

    /**
     * Aging Analysis — buckets overdue loans by days in arrears.
     * Buckets: 1-30, 31-60, 61-90, 90+
     */
    public function agingAnalysis(Request $request)
    {
        $companyId = $request->user()->company_id;

        $loans = Loan::where('company_id', $companyId)
            ->where('is_overdue', true)
            ->whereIn('status', ['active', 'disbursed', 'defaulted'])
            ->with(['borrower', 'loanProduct', 'loanOfficer'])
            ->get();

        $buckets = [
            '1-30'  => ['label' => '1–30 days',  'loans' => [], 'total' => 0],
            '31-60' => ['label' => '31–60 days', 'loans' => [], 'total' => 0],
            '61-90' => ['label' => '61–90 days', 'loans' => [], 'total' => 0],
            '90+'   => ['label' => '90+ days',   'loans' => [], 'total' => 0],
        ];

        foreach ($loans as $loan) {
            $days = $loan->days_in_arrears;
            $key  = match(true) {
                $days <= 30 => '1-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default     => '90+',
            };

            $row = [
                'loan_no'        => $loan->loan_no,
                'borrower'       => $loan->borrower->first_name . ' ' . $loan->borrower->last_name,
                'phone'          => $loan->borrower->phone,
                'product'        => $loan->loan_product->name,
                'outstanding'    => $loan->outstanding_balance,
                'days_in_arrears'=> $days,
                'officer'        => $loan->loanOfficer?->name,
            ];

            $buckets[$key]['loans'][] = $row;
            $buckets[$key]['total']  += $loan->outstanding_balance;
        }

        return response()->json([
            'buckets'   => $buckets,
            'total_par' => $loans->sum('outstanding_balance'),
            'total_loans_in_arrears' => $loans->count(),
        ]);
    }

    /**
     * Officer Performance — per loan officer: loans count, disbursed, collected, PAR.
     */
    public function officerPerformance(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $companyId = $request->user()->company_id;

        $query = Loan::where('company_id', $companyId)
            ->whereNotNull('loan_officer_id')
            ->whereIn('status', ['active', 'disbursed', 'closed', 'defaulted']);

        if ($request->date_from) $query->where('disbursement_date', '>=', $request->date_from);
        if ($request->date_to)   $query->where('disbursement_date', '<=', $request->date_to);

        $performance = $query
            ->select(
                'loan_officer_id',
                DB::raw('count(*) as loan_count'),
                DB::raw('SUM(principal_amount) as total_disbursed'),
                DB::raw('SUM(total_paid) as total_collected'),
                DB::raw('SUM(outstanding_balance) as total_outstanding'),
                DB::raw('SUM(CASE WHEN is_overdue = 1 THEN outstanding_balance ELSE 0 END) as par_amount'),
                DB::raw('SUM(CASE WHEN is_overdue = 1 THEN 1 ELSE 0 END) as par_count')
            )
            ->groupBy('loan_officer_id')
            ->with('loanOfficer')
            ->get()
            ->map(fn($row) => [
                'officer'          => $row->loanOfficer?->name ?? 'Unassigned',
                'loan_count'       => $row->loan_count,
                'total_disbursed'  => round($row->total_disbursed, 2),
                'total_collected'  => round($row->total_collected, 2),
                'total_outstanding'=> round($row->total_outstanding, 2),
                'par_amount'       => round($row->par_amount, 2),
                'par_count'        => $row->par_count,
                'collection_rate'  => $row->total_disbursed > 0
                    ? round(($row->total_collected / $row->total_disbursed) * 100, 1)
                    : 0,
            ]);

        return response()->json(['data' => $performance]);
    }

    /**
     * Income Statement — revenue vs expenses for a date range.
     */
    public function incomeStatement(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $companyId = $request->user()->company_id;
        $from      = $request->date_from;
        $to        = $request->date_to;

        // --- REVENUE ---

        // Interest collected from repayments
        $interestCollected = Repayment::where('company_id', $companyId)
            ->whereBetween('payment_date', [$from, $to])
            ->sum('interest_amount');

        // Processing fees collected (paid loan charges)
        $feesCollected = LoanCharge::whereHas('loan', fn($q) => $q->where('company_id', $companyId))
            ->where('is_paid', true)
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('amount');

        // Penalties collected (paid penalties)
        $penaltiesCollected = Penalty::whereHas('loan', fn($q) => $q->where('company_id', $companyId))
            ->where('is_paid', true)
            ->whereBetween('paid_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('amount');

        // Penalties applied (billed, regardless of payment)
        $penaltiesApplied = Penalty::whereHas('loan', fn($q) => $q->where('company_id', $companyId))
            ->whereBetween('applied_at', [$from, $to])
            ->sum('amount');

        $totalRevenue = round($interestCollected + $feesCollected + $penaltiesCollected, 2);

        // --- EXPENSES ---

        $expenseRows = Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$from, $to])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $totalExpenses = round($expenseRows->sum('total'), 2);

        // --- MONTHLY BREAKDOWN (revenue vs expenses per month in the range) ---

        $monthlyRevenue = Repayment::where('company_id', $companyId)
            ->whereBetween('payment_date', [$from, $to])
            ->select(
                DB::raw("DATE_FORMAT(payment_date, '%Y-%m') as month"),
                DB::raw('SUM(interest_amount) as interest'),
                DB::raw('SUM(fee_amount) as fees'),
                DB::raw('SUM(amount) as total_collected')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyExpenses = Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$from, $to])
            ->select(
                DB::raw("DATE_FORMAT(expense_date, '%Y-%m') as month"),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthly = $monthlyRevenue->map(fn($row) => [
            'month'          => $row->month,
            'interest'       => round($row->interest, 2),
            'fees'           => round($row->fees, 2),
            'total_revenue'  => round($row->interest + $row->fees, 2),
            'total_expenses' => round($monthlyExpenses[$row->month]->total ?? 0, 2),
            'net_income'     => round(
                ($row->interest + $row->fees) - ($monthlyExpenses[$row->month]->total ?? 0),
                2
            ),
        ]);

        return response()->json([
            'period'             => ['from' => $from, 'to' => $to],
            'revenue' => [
                'interest_collected'  => round($interestCollected, 2),
                'fees_collected'      => round($feesCollected, 2),
                'penalties_collected' => round($penaltiesCollected, 2),
                'penalties_applied'   => round($penaltiesApplied, 2),
                'total'              => $totalRevenue,
            ],
            'expenses' => [
                'by_category' => $expenseRows,
                'total'       => $totalExpenses,
            ],
            'net_income'         => round($totalRevenue - $totalExpenses, 2),
            'monthly_breakdown'  => $monthly,
        ]);
    }

    /**
     * Balance Sheet — snapshot of assets, liabilities, and equity.
     */
    public function balanceSheet(Request $request)
    {
        $companyId = $request->user()->company_id;
        $asAt      = $request->input('as_at', now()->toDateString());

        // --- ASSETS ---

        // Loan portfolio (gross outstanding)
        $loanPortfolio = Loan::where('company_id', $companyId)
            ->whereIn('status', ['active', 'disbursed', 'defaulted'])
            ->sum('outstanding_balance');

        // Cash / bank (total repayments collected - total disbursed - total expenses)
        $totalDisbursed    = Loan::where('company_id', $companyId)->whereNotNull('disbursement_date')->sum('principal_amount');
        $totalCollected    = Repayment::where('company_id', $companyId)->sum('amount');
        $totalExpenses     = Expense::where('company_id', $companyId)->sum('amount');
        $cash              = round($totalCollected - $totalDisbursed - $totalExpenses, 2);

        // Savings pool held (cash already captured above; show separately for info)
        $savingsDeposits   = SavingsAccount::where('company_id', $companyId)->sum('balance');

        $totalAssets       = round($loanPortfolio + max($cash, 0), 2);

        // --- LIABILITIES ---

        // Member savings balances (owed back to members)
        $memberSavings     = round($savingsDeposits, 2);

        // Total liabilities
        $totalLiabilities  = $memberSavings;

        // --- EQUITY ---

        // Share capital paid in
        $shareCapital      = round(MemberShare::where('company_id', $companyId)->sum('total_paid'), 2);

        // Contributions pool
        $contributionsPool = round(Contribution::where('company_id', $companyId)->sum('amount'), 2);

        // Retained surplus = total revenue earned - expenses - dividends (use net income approx)
        $interestEarned    = Repayment::where('company_id', $companyId)->sum('interest_amount');
        $feesEarned        = LoanCharge::whereHas('loan', fn($q) => $q->where('company_id', $companyId))->where('is_paid', true)->sum('amount');
        $totalRevenue      = round($interestEarned + $feesEarned, 2);
        $retainedSurplus   = round($totalRevenue - $totalExpenses, 2);

        $totalEquity       = round($shareCapital + $contributionsPool + $retainedSurplus, 2);

        return response()->json([
            'as_at'       => $asAt,
            'assets' => [
                'loan_portfolio'  => round($loanPortfolio, 2),
                'cash_and_bank'   => $cash,
                'total'           => $totalAssets,
            ],
            'liabilities' => [
                'member_savings'  => $memberSavings,
                'total'           => round($totalLiabilities, 2),
            ],
            'equity' => [
                'share_capital'       => $shareCapital,
                'contributions_pool'  => $contributionsPool,
                'retained_surplus'    => $retainedSurplus,
                'total'               => $totalEquity,
            ],
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
        ]);
    }
}
