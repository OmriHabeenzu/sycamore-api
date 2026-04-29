<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCharge extends Model
{
    protected $fillable = [
        'loan_id', 'charge_type', 'name', 'amount', 'is_paid', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
