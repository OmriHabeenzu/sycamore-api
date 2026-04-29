<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Contribution;
use App\Models\Loan;
use App\Models\MemberShare;
use App\Models\Repayment;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId  = $request->user()->company_id;
        $today      = now()->toDateString();
        $thisMonth  = now()->startOfMonth()->toDateString();

        // Members
        $totalMembers = Borrower::where('company_id', $companyId)->count();

        // Savings pool
        $savingsPool = SavingsAccount::where('company_id', $companyId)
            ->where('status', 'active')
            ->sum('balance');

        // Share capital
        $shareCapital = MemberShare::where('company_id', $companyId)
            ->where('status', 'active')
            ->sum('total_paid');

        // Contributions this month
        $contributionsThisMonth = Contribution::where('company_id', $companyId)
            ->where('contribution_date', '>=', $thisMonth)
            ->sum('amount');

        // Loan portfolio
        $portfolio = Loan::where('company_id', $companyId)
            ->whereIn('status', ['active', 'disbursed'])
            ->selectRaw('
                count(*) as active_loans,
                COALESCE(SUM(principal_amount), 0) as total_disbursed,
                COALESCE(SUM(outstanding_balance), 0) as total_outstanding
            ')
            ->first();

        $pendingLoans = Loan::where('company_id', $companyId)->where('status', 'pending')->count();

        // PAR
        $parCount  = Loan::where('company_id', $companyId)->where('is_overdue', true)->count();
        $parAmount = Loan::where('company_id', $companyId)->where('is_overdue', true)->sum('outstanding_balance');
        $parRate   = $portfolio->total_outstanding > 0
            ? round(($parAmount / $portfolio->total_outstanding) * 100, 2) : 0;

        // Collections today + this month
        $collectedToday      = Repayment::where('company_id', $companyId)->whereDate('payment_date', $today)->sum('amount');
        $collectedThisMonth  = Repayment::where('company_id', $companyId)->where('payment_date', '>=', $thisMonth)->sum('amount');

        // Monthly savings contributions (last 6 months) for chart
        $monthlyContributions = Contribution::where('company_id', $companyId)
            ->where('contribution_date', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(contribution_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Monthly loan repayments (last 6 months)
        $monthlyRepayments = Repayment::where('company_id', $companyId)
            ->where('payment_date', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw("DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Recent loans
        $recentLoans = Loan::where('company_id', $companyId)
            ->with(['borrower', 'loanProduct'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_members'           => $totalMembers,
                'savings_pool'            => round($savingsPool, 2),
                'share_capital'           => round($shareCapital, 2),
                'contributions_this_month'=> round($contributionsThisMonth, 2),
                'active_loans'            => $portfolio->active_loans,
                'pending_loans'           => $pendingLoans,
                'total_outstanding'       => round($portfolio->total_outstanding, 2),
                'collected_today'         => round($collectedToday, 2),
                'collected_this_month'    => round($collectedThisMonth, 2),
                'par_count'               => $parCount,
                'par_amount'              => round($parAmount, 2),
                'par_rate'                => $parRate,
            ],
            'recent_loans'          => $recentLoans,
            'monthly_contributions' => $monthlyContributions,
            'monthly_repayments'    => $monthlyRepayments,
        ]);
    }
}
