<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contribution extends Model
{
    protected $fillable = [
        'company_id', 'borrower_id', 'contribution_schedule_id',
        'amount', 'contribution_date', 'reference', 'notes', 'received_by',
    ];

    public function borrower()   { return $this->belongsTo(Borrower::class); }
    public function schedule()   { return $this->belongsTo(ContributionSchedule::class, 'contribution_schedule_id'); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by'); }
}
