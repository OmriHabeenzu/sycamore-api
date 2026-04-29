<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContributionSchedule extends Model
{
    protected $fillable = [
        'company_id', 'borrower_id', 'expected_amount',
        'frequency', 'start_date', 'end_date', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function borrower()      { return $this->belongsTo(Borrower::class); }
    public function contributions() { return $this->hasMany(Contribution::class); }
}
