<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDocuments extends Model
{
    protected $fillable = [
        'adhar_card',
        'pan_card',
        'certificate',
        'experience_letter',
        'salary_slip',
    ];
}
