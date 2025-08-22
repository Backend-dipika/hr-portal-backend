<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holidays extends Model
{

    protected $fillable = [
        'uuid',
        'name',
        'day',
        'start_date',
        'end_date',
    ];
}
