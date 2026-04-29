<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collateral extends Model
{
    protected $table = 'collateral';

    protected $fillable = [
        'loan_id', 'type', 'description', 'estimated_value',
        'serial_number', 'location', 'notes',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
    ];

    public function loan() { return $this->belongsTo(Loan::class); }
}
