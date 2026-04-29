<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareTransaction extends Model
{
    protected $fillable = [
        'member_share_id', 'type', 'shares', 'amount',
        'transaction_date', 'reference', 'notes', 'created_by',
    ];

    public function memberShare() { return $this->belongsTo(MemberShare::class); }
}
