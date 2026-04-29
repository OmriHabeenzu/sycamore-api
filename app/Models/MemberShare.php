<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberShare extends Model
{
    protected $fillable = [
        'company_id', 'borrower_id', 'shares', 'amount_per_share',
        'total_paid', 'joined_date', 'status', 'notes',
    ];

    protected $casts = [
        'shares'          => 'float',
        'amount_per_share'=> 'float',
        'total_paid'      => 'float',
    ];

    public function borrower()   { return $this->belongsTo(Borrower::class); }
    public function company()    { return $this->belongsTo(Company::class); }
    public function transactions(){ return $this->hasMany(ShareTransaction::class); }

    public function getShareValueAttribute(): float
    {
        return round($this->shares * $this->amount_per_share, 2);
    }
}
