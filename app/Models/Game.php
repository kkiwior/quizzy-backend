<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Game extends Model
{
    use HasApiTokens;

    protected $hidden = ['updated_at', 'user_id', 'questions_queue'];


    public function user()
    {
        return $this->hasMany(User::User);
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
