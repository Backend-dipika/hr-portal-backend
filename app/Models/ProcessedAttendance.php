<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedAttendance extends Model
{
    protected $fillable = [
        'user_id',
        'employee_name',
        'attendance_date',
        'checkin_time',
        'checkout_time',     
    ];

}
