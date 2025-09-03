<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'department_id',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'designation_id');
    }
}
