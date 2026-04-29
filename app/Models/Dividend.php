<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dividend extends Model
{
    protected $fillable = [
        'company_id', 'year', 'total_surplus', 'distributable_amount',
        'per_share_rate', 'status', 'approved_at', 'distributed_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'total_surplus'       => 'float',
        'distributable_amount'=> 'float',
        'per_share_rate'      => 'float',
    ];

    public function allocations() { return $this->hasMany(DividendAllocation::class); }
    public function company()     { return $this->belongsTo(Company::class); }
}
