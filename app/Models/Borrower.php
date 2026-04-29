<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Borrower extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'borrower_no', 'first_name', 'last_name', 'email',
        'phone', 'dob', 'gender', 'national_id', 'address', 'city', 'photo',
        'employment_status', 'employer', 'monthly_income', 'created_by',
    ];

    protected $casts = [
        'dob' => 'date',
        'monthly_income' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function nextOfKin()
    {
        return $this->hasMany(NextOfKin::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'entity');
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
