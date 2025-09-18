<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResignationRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requested_by_id',
        'type',
        'submission_date',
        'effective_date',
        'notice_period_end_date',
        'reason',
        'message',
        'final_status',
        'document',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function requestedBy()
    {
        return $this->hasMany(User::class, 'requested_by_id');
    }

    public function approvals()
    {
        return $this->hasMany(ResignationRequestApproval::class, 'resignation_request_id');
    }
}
