<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


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

        return response([ 'game' => $game, 'question' => $this->nextQuestion($game) ], 200);
    }

    public function answer(Request $request)
    {
        $game = Game::find($request->route('id'));

        if(($game->user_id ?? null) != (Auth::guard('api')->user()->id ?? null)) return abort (403);

        $answer = $request->input('answer');
        $correctAnswers = json_decode($game->correct_answers_id);

        if($answer != 0 && (strtotime(date("Y-m-d h:i:s")) - strtotime($game->updated_at))/60 < ($game->load('quiz')->time + 5))
        {
            if(in_array($answer, $correctAnswers)) $game->correctAnswers++;
        }

        if(strlen($game->questions_queue) > 2) return response([ 'correct' => $correctAnswers, 'question' => $this->nextQuestion($game) ], 200);
        $game->save();
        return response([ 'correct' => $correctAnswers, 'finish' => $game->correctAnswers ], 200);
    }


    public function nextQuestion(Game $game)
    {
        $questions = json_decode($game->questions_queue);
        $qid = array_shift($questions);
        $game->questions_queue = json_encode($questions);
        $question = Question::find($qid);
        $answers = json_decode($question->answers, true);
        $correct = [];
        foreach($answers as &$answer){
            if($answer["correct"]) $correct[] = $answer["id"];
            unset($answer["correct"]);
        }
        $question->answers = $answers;
        $game->correct_answers_id = json_encode($correct);
        $game->save();
        return $question;
    }
}
