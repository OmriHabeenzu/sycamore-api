<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'group_no', 'name', 'loan_officer_id',
        'meeting_frequency', 'meeting_day', 'meeting_location', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function loanOfficer()
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    public function members()
    {
        return $this->belongsToMany(Borrower::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function groupLoans()
    {
        return $this->hasMany(GroupLoan::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
