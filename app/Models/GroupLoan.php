<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupLoan extends Model
{
    protected $fillable = [
        'company_id', 'group_id', 'loan_product_id', 'loan_officer_id',
        'cycle_no', 'total_amount', 'status', 'disbursement_date', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'disbursement_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    public function loanOfficer()
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    // Individual member loans within this group loan cycle
    public function memberLoans()
    {
        return $this->hasMany(Loan::class, 'group_loan_id');
    }
}
