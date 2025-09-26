<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    // use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'duration_type',
        'start_date',
        'end_date',
        'actual_end_date',
        'reason',
        'status',
        'approved_on',
        'is_cancel_request',
        'total_days_requested',
        'total_days_approved',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_end_date' => 'date',
        'approved_on' => 'datetime',
        'is_cancel_request' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvals()
{
    return $this->hasMany(LeaveApproval::class, 'leave_request_id');
}
}
