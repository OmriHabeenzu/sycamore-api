<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'company_id', 'borrower_id', 'loan_id', 'phone', 'message',
        'status', 'provider', 'provider_reference', 'response', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }
}
