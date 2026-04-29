<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\DividendController;
use App\Http\Controllers\Api\GovernanceController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\LoanChargeController;
use App\Http\Controllers\Api\MemberShareController;
use App\Http\Controllers\Api\SavingsController;
use App\Http\Controllers\Api\CollateralController;
use App\Http\Controllers\Api\GuarantorController;
use App\Http\Controllers\Api\BorrowerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LoanProductController;
use App\Http\Controllers\Api\RepaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SuperAdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health check (Railway)
Route::get('/health', fn() => response()->json(['status' => 'ok']));

// Public
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Borrowers
    Route::apiResource('borrowers', BorrowerController::class);

    // Loan Products
    Route::apiResource('loan-products', LoanProductController::class);

    // Loans
    Route::post('/loans/preview-schedule',     [LoanController::class, 'previewSchedule']);
    Route::post('/loans/{loan}/approve',        [LoanController::class, 'approve']);
    Route::post('/loans/{loan}/reject',         [LoanController::class, 'reject']);
    Route::post('/loans/{loan}/disburse',       [LoanController::class, 'disburse']);
    Route::post('/loans/{loan}/top-up',         [LoanController::class, 'topUp']);
    Route::post('/loans/{loan}/restructure',    [LoanController::class, 'restructure']);
    Route::post('/loans/{loan}/write-off',      [LoanController::class, 'writeOff']);
    Route::apiResource('loans', LoanController::class);

    // Super admin
    Route::get('/admin/overview',                          [SuperAdminController::class, 'overview']);
    Route::get('/admin/tenants',                           [SuperAdminController::class, 'tenants']);
    Route::post('/admin/tenants',                          [SuperAdminController::class, 'storeTenant']);
    Route::put('/admin/tenants/{company}',                 [SuperAdminController::class, 'updateTenant']);
    Route::post('/admin/tenants/{company}/suspend',        [SuperAdminController::class, 'suspendTenant']);
    Route::post('/admin/tenants/{company}/activate',       [SuperAdminController::class, 'activateTenant']);

    // Settings
    Route::get('/settings/company',                     [SettingsController::class, 'getCompany']);
    Route::put('/settings/company',                     [SettingsController::class, 'updateCompany']);
    Route::get('/settings/users',                       [SettingsController::class, 'getUsers']);
    Route::post('/settings/users',                      [SettingsController::class, 'storeUser']);
    Route::put('/settings/users/{user}',                [SettingsController::class, 'updateUser']);
    Route::post('/settings/users/{user}/reset-password',[SettingsController::class, 'resetPassword']);
    Route::get('/settings/sms-logs',                    [SettingsController::class, 'smsLogs']);

    // Governance
    Route::get('/governance/board',          [GovernanceController::class, 'boardIndex']);
    Route::post('/governance/board',         [GovernanceController::class, 'boardStore']);
    Route::delete('/governance/board/{id}',  [GovernanceController::class, 'boardDestroy']);
    Route::get('/governance/minutes',        [GovernanceController::class, 'minutesIndex']);
    Route::post('/governance/minutes',       [GovernanceController::class, 'minutesStore']);
    Route::delete('/governance/minutes/{id}',[GovernanceController::class, 'minutesDestroy']);

    // Dividends
    Route::get('/dividends',                      [DividendController::class, 'index']);
    Route::post('/dividends/calculate',           [DividendController::class, 'calculate']);
    Route::post('/dividends',                     [DividendController::class, 'store']);
    Route::get('/dividends/{dividend}',           [DividendController::class, 'show']);
    Route::post('/dividends/{dividend}/approve',  [DividendController::class, 'approve']);
    Route::post('/dividends/{dividend}/distribute',[DividendController::class, 'distribute']);

    // Member Shares
    Route::get('/members/{borrower}/shares',           [MemberShareController::class, 'show']);
    Route::post('/members/{borrower}/shares',          [MemberShareController::class, 'store']);
    Route::post('/members/{borrower}/shares/purchase', [MemberShareController::class, 'purchase']);
    Route::get('/shares/summary',                      [MemberShareController::class, 'summary']);

    // Member Contributions
    Route::get('/contributions',                       [ContributionController::class, 'index']);
    Route::post('/contributions',                      [ContributionController::class, 'store']);
    Route::delete('/contributions/{contribution}',     [ContributionController::class, 'destroy']);
    Route::get('/members/{borrower}/contributions',    [ContributionController::class, 'memberIndex']);
    Route::get('/contribution-schedules/{borrower}',   [ContributionController::class, 'getSchedule']);
    Route::post('/contribution-schedules',             [ContributionController::class, 'storeSchedule']);

    // Reports
    Route::get('/reports/collections',        [ReportController::class, 'collectionsSheet']);
    Route::get('/reports/portfolio',           [ReportController::class, 'portfolio']);
    Route::get('/reports/aging',               [ReportController::class, 'agingAnalysis']);
    Route::get('/reports/officer-performance', [ReportController::class, 'officerPerformance']);
    Route::get('/reports/income-statement',    [ReportController::class, 'incomeStatement']);
    Route::get('/reports/balance-sheet',       [ReportController::class, 'balanceSheet']);

    // Repayments — nested under loan + top-level list
    Route::get('/repayments',                               [RepaymentController::class, 'companyIndex']);
    Route::get('/repayments/{repayment}',                   [RepaymentController::class, 'show']);
    Route::get('/loans/{loan}/repayments',                  [RepaymentController::class, 'index']);
    Route::post('/loans/{loan}/repayments',                 [RepaymentController::class, 'store']);

    // Guarantors
    Route::get('/loans/{loan}/guarantors',                  [GuarantorController::class, 'index']);
    Route::post('/loans/{loan}/guarantors',                 [GuarantorController::class, 'store']);
    Route::put('/loans/{loan}/guarantors/{guarantor}',      [GuarantorController::class, 'update']);
    Route::delete('/loans/{loan}/guarantors/{guarantor}',   [GuarantorController::class, 'destroy']);

    // Collateral
    Route::get('/loans/{loan}/collateral',                  [CollateralController::class, 'index']);
    Route::post('/loans/{loan}/collateral',                 [CollateralController::class, 'store']);
    Route::put('/loans/{loan}/collateral/{collateral}',     [CollateralController::class, 'update']);
    Route::delete('/loans/{loan}/collateral/{collateral}',  [CollateralController::class, 'destroy']);

    // Expenses
    Route::apiResource('expenses', ExpenseController::class)->except(['show']);

    // Groups
    Route::apiResource('groups', GroupController::class)->except(['destroy']);
    Route::post('/groups/{group}/members',              [GroupController::class, 'addMember']);
    Route::delete('/groups/{group}/members/{borrower}', [GroupController::class, 'removeMember']);

    // Savings
    Route::get('/savings',                              [SavingsController::class, 'index']);
    Route::post('/savings',                             [SavingsController::class, 'store']);
    Route::get('/savings/{savingsAccount}',             [SavingsController::class, 'show']);
    Route::put('/savings/{savingsAccount}',             [SavingsController::class, 'update']);
    Route::post('/savings/{savingsAccount}/deposit',      [SavingsController::class, 'deposit']);
    Route::post('/savings/{savingsAccount}/withdraw',     [SavingsController::class, 'withdraw']);
    Route::post('/savings/{savingsAccount}/post-interest',[SavingsController::class, 'postInterest']);

    // Loan Charges
    Route::get('/loans/{loan}/charges',                       [LoanChargeController::class, 'index']);
    Route::post('/loans/{loan}/charges',                      [LoanChargeController::class, 'store']);
    Route::post('/loans/{loan}/charges/{charge}/mark-paid',   [LoanChargeController::class, 'markPaid']);
    Route::delete('/loans/{loan}/charges/{charge}',           [LoanChargeController::class, 'destroy']);

    // Documents (shared controller, entity type resolved in route closure)
    Route::get('/borrowers/{borrower}/documents',  fn (Request $req, $b) => app(DocumentController::class)->index($req, 'borrower', $b));
    Route::post('/borrowers/{borrower}/documents', fn (Request $req, $b) => app(DocumentController::class)->store($req, 'borrower', $b));
    Route::get('/loans/{loan}/documents',          fn (Request $req, $l) => app(DocumentController::class)->index($req, 'loan', $l));
    Route::post('/loans/{loan}/documents',         fn (Request $req, $l) => app(DocumentController::class)->store($req, 'loan', $l));
    Route::get('/documents/{document}/download',   [DocumentController::class, 'download']);
    Route::delete('/documents/{document}',         [DocumentController::class, 'destroy']);
});
