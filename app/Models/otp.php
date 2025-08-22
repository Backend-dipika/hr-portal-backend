<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class otp extends Model
{
    protected $fillable = [
        'phone_number',
        'otp',
        'is_used',
        'expires_at',
        'user_id',
    ];
}
