<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repayment extends Model
{
    protected $fillable = [
        'company_id', 'loan_id', 'receipt_no', 'received_by', 'amount',
        'principal_amount', 'interest_amount', 'fee_amount', 'penalty_amount',
        'payment_date', 'payment_method', 'reference_number', 'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
