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

    protected $hidden = ['id', 'quiz_id', 'created_at', 'updated_at'];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
