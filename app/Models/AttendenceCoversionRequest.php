<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendenceCoversionRequest extends Model
{
   // use HasFactory;

    protected $fillable = [
        'attendance_id',
        'requested_by',
        'status',
        'approved_by',
        'comments',
    ];
}
