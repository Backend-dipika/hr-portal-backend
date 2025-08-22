<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeType extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'max_minutes_perday',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'designation_id');
    }
}
