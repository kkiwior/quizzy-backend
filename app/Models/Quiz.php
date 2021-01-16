<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Quiz extends Model
{
    use HasApiTokens;

    protected $fillable = ['name', 'shuffle', 'public', 'anonymous', 'reentry', 'time', 'thumbnail'];
    protected $hidden = ['updated_at', 'creator_id'];

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class);
    }

    public function game()
    {
        return $this->hasMany(Game::class);
    }
}
