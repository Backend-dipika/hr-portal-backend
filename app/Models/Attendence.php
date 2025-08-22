<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendence extends Model
{
    
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'day',
        'date',
        'checkIn',
        'checkOut',
        'working_minutes',
        'status',
        'is_late',
    ];

    protected $casts = [
        'date' => 'date',
        'checkIn' => 'datetime:H:i',
        'checkOut' => 'datetime:H:i',
        'is_late' => 'boolean',
    ];
}
