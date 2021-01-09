<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Quiz extends Model
{
    use HasApiTokens;

    protected $fillable = ['name', 'shuffle', 'public', 'anonymous', 'reentry', 'time'];
    protected $hidden = ['updated_at', 'creator_id'];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class);
    }
}
