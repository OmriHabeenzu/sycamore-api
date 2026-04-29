<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guarantor extends Model
{
    protected $fillable = [
        'loan_id', 'borrower_id', 'name', 'phone', 'national_id',
        'relationship', 'address', 'employer', 'monthly_income',
    ];

    protected $casts = [
        'monthly_income' => 'decimal:2',
    ];

    public function loan()     { return $this->belongsTo(Loan::class); }
    public function borrower() { return $this->belongsTo(Borrower::class); }
}
