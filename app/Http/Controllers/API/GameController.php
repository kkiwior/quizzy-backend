<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class GameController extends Controller
{
    public function start(Request $request)
    {
        $user = Auth::guard('api')->user();
        $quiz = Quiz::find($request->route('id'));

        if($quiz->anonymous && $user == null) return response()->json(["Dostęp tylko dla zalogowanych."], 401);
        if($quiz->reentry && $user?->id !== null)
            if(Game::where('quiz_id', $request->route('id'))->where('user_id', $user?->id)->first()) return response()->json(["Quiz można rozwiązać tylko raz."], 401);


        $game = new Game();
        $game->quiz_id = $quiz->id;
        $game->user_id = $user?->id;

        $questionsOrder = array();
        foreach($quiz->questions as $question)
        {
            $questionsOrder[] = $question->id;
        }

        if($quiz->shuffle){
            shuffle($questionsOrder);
        }

        $game->questions_queue = json_encode($questionsOrder);
        $game->save();

        return response([ 'game' => $game, 'question' => $this->nextQuestion($game) ]);
    }

    public function answer(Request $request)
    {
        $game::find($request->route('id'));
    }


    public function nextQuestion(Game $game)
    {
        $questions = json_decode($game->questions_queue);
        $qid = array_shift($questions);
        $game->questions_queue = json_encode($questions);
        $game->save();
        $question = Question::find($qid);
        $question->answers = json_decode(Str::of($question->answers)->replaceMatches('/,\\"correct\\":((false)|(true))/', ''), true);

        return $question;
    }
}
