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
        'remarks',
        'status',
        'approver_id',
        'approval_date',
        'is_closed'
    ];

    protected $casts = [
        'processed_on' => 'date',
        'year' => 'integer',
    ];
    public function user()
{
    return $this->belongsTo(User::class, 'user_id', 'id');
}
}
