<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendenceCoversionLimit extends Model
{
    // use HasFactory;
    protected $fillable = [
        'uuid',
        'user_id',
        'month',
        'conversion_type',
        'allowed_conversions',
        'used_conversions',
        'modified_by',
    ];
}
