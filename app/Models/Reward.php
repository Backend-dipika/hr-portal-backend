<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{

    protected $fillable = [
        'uuid',
        'reward_category_id',
        'user_id',
        'department_id',
        'title',
        'description',
        'reward_date',
    ];
    public function category()
    {
        return $this->belongsTo(RewardCategory::class, 'reward_category_id');
    }
}
