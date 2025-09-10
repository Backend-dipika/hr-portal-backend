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
    
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
   

    // public function resignationRequest()
    // {
    //     return $this->belongsTo(ResignationRequest::class, 'resignation_request_id');
    // }
}
