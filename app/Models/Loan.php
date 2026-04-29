<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'loan_no', 'borrower_id', 'loan_product_id', 'loan_officer_id',
        'group_id', 'group_loan_id', 'principal_amount', 'interest_rate',
        'interest_method', 'repayment_frequency', 'term', 'term_unit',
        'application_date', 'disbursement_date', 'first_repayment_date', 'maturity_date',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
        'disbursed_by', 'disbursed_at', 'disbursement_method', 'disbursement_reference',
        'total_interest', 'processing_fee', 'total_amount_due',
        'total_paid', 'outstanding_balance', 'days_in_arrears', 'is_overdue', 'notes',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'total_interest' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'total_amount_due' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'application_date' => 'date',
        'disbursement_date' => 'date',
        'first_repayment_date' => 'date',
        'maturity_date' => 'date',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
        'is_overdue' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function loanOfficer()
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursedBy()
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function groupLoan()
    {
        return $this->belongsTo(GroupLoan::class);
    }

    public function schedule()
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_no');
    }

    public function repayments()
    {
        return $this->hasMany(Repayment::class)->orderBy('payment_date');
    }

    public function charges()
    {
        return $this->hasMany(LoanCharge::class);
    }

    public function guarantors()
    {
        return $this->hasMany(Guarantor::class);
    }

    public function collateral()
    {
        return $this->hasMany(Collateral::class);
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'entity');
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isActive(): bool { return $this->status === 'active'; }
    public function isClosed(): bool { return $this->status === 'closed'; }
    public function isDisbursed(): bool { return in_array($this->status, ['disbursed', 'active']); }
}
