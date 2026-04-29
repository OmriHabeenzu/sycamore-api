<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoanProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'name', 'description', 'interest_method', 'interest_rate',
        'repayment_frequency', 'min_amount', 'max_amount', 'min_term', 'max_term',
        'term_unit', 'processing_fee_type', 'processing_fee_value',
        'late_penalty_type', 'late_penalty_value', 'grace_period_days', 'is_active',
    ];

    protected $casts = [
        'interest_rate' => 'decimal:4',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'processing_fee_value' => 'decimal:4',
        'late_penalty_value' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
