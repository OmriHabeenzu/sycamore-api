<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SavingsAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'borrower_id', 'account_no', 'product_name',
        'balance', 'interest_rate', 'status', 'opened_at', 'closed_at', 'notes',
    ];

    protected $casts = [
        'balance'       => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'opened_at'     => 'date',
        'closed_at'     => 'date',
    ];

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class)->orderByDesc('transaction_date');
    }
}
