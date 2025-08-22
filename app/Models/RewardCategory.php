<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardCategory extends Model
{

    protected $fillable = [
        'name',
    ];
    public function rewards()
    {
        return $this->hasMany(Reward::class, 'reward_category_id');
    }
    public function users()
    {
        return $this->hasMany(User::class, 'user_id');
    }
}
