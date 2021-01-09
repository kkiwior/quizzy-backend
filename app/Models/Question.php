<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;

class Question extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'question',
        'answers',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
