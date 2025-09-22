<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appreciation extends Model
{
   
        
    use HasFactory;
    protected $fillable = [
        'uuid',
        'from_user_id',
        'to_user_id',
        'title',
        'category',
        'message',
        'date_of_appreciation',
    ];
}
