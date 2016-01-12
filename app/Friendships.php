<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Friendships extends Model
{
    protected $fillable = ['user_id', 'friend_id'];
}
