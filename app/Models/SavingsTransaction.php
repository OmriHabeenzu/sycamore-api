<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingsTransaction extends Model
{
    protected $fillable = [
        'savings_account_id', 'type', 'amount', 'balance_after',
        'reference', 'notes', 'transaction_date', 'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'balance_after'    => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function account()
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
