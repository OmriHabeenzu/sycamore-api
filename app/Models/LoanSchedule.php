<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanSchedule extends Model
{
    protected $fillable = [
        'loan_id', 'installment_no', 'due_date',
        'principal_due', 'interest_due', 'fee_due', 'total_due',
        'principal_paid', 'interest_paid', 'fee_paid', 'total_paid',
        'status', 'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'principal_due' => 'decimal:2',
        'interest_due' => 'decimal:2',
        'fee_due' => 'decimal:2',
        'total_due' => 'decimal:2',
        'principal_paid' => 'decimal:2',
        'interest_paid' => 'decimal:2',
        'fee_paid' => 'decimal:2',
        'total_paid' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->total_due - (float) $this->total_paid;
    }
}
