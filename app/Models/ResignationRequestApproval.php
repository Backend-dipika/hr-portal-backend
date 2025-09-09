<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResignationRequestApproval extends Model
{
    protected $fillable = [
        'resignation_request_id',
        'approver_id',
        'approval_order',
        'approval_status',
        'approval_date',
    ];
}
