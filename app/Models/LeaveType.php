<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
   // use HasFactory;

    protected $fillable = [
        'name',
        'max_allowed_days',
        'requires_approval',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];
}
