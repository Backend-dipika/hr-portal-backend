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
}
