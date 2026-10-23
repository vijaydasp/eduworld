<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enquiry extends Model
{
    use HasFactory;

    protected $fillable = ['full_name', 'phone', 'email', 'service_type', 'message', 'status', 'admin_notes', 'contacted_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
