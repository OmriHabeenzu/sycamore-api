<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'logo', 'primary_color', 'phone', 'email',
        'address', 'city', 'country', 'status', 'subscription_plan',
        'subscription_expires_at',
    ];

    protected $casts = [
        'subscription_expires_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function borrowers()
    {
        return $this->hasMany(Borrower::class);
    }

    public function loanProducts()
    {
        return $this->hasMany(LoanProduct::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function groups()
    {
        return $this->hasMany(Group::class);
    }
}
