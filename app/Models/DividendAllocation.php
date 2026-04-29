<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendAllocation extends Model
{
    protected $fillable = [
        'dividend_id', 'borrower_id', 'shares', 'amount', 'is_paid', 'paid_at', 'payment_method',
    ];

    protected $casts = ['is_paid' => 'boolean', 'amount' => 'float', 'shares' => 'float'];

    public function borrower() { return $this->belongsTo(Borrower::class); }
    public function dividend() { return $this->belongsTo(Dividend::class); }
}
