<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'uuid',
        'name',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'designation_id');
    }
}
