<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveApproval extends Model
{
    protected $fillable = [
        'leave_request_id',
        'approver_id',
        'level',
        'status',
        'action_type',
        'approved_on',
    ];

    public function approver()
{
    return $this->belongsTo(User::class, 'approver_id');
}

}
