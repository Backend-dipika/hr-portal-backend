<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveYearEndAction extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'action_type',
        'days',
        'processed_on',
    ];

    protected $casts = [
        'processed_on' => 'date',
        'year' => 'integer',
    ];
}
