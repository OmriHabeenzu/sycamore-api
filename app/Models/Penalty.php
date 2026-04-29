<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'loan_id', 'amount', 'reason', 'applied_at', 'is_paid', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'applied_at' => 'date',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
