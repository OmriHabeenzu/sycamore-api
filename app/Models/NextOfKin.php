<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NextOfKin extends Model
{
    protected $table = 'next_of_kin';

    protected $fillable = [
        'borrower_id', 'name', 'relationship', 'phone', 'address',
    ];

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }
}
