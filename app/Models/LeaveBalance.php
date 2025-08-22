<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
       protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'total_allocated',
        'used_days',
        'remaining_days',
        'carry_forward_days',
    ];
}
