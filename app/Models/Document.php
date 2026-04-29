<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'company_id', 'entity_type', 'entity_id', 'name',
        'file_path', 'file_type', 'uploaded_by',
    ];

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Polymorphic: entity_type = borrower|loan
    public function entity()
    {
        return $this->morphTo();
    }
}
