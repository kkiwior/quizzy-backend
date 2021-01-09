<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class RequireCorrect implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $correctAnswers = 0;
        foreach($value as $answer)
        {
            if($answer["correct"]) return true;
        }
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Pytanie musi mieć conajmniej jedną poprawną odpowiedź.';
    }
}
