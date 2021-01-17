<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;


class GameController extends Controller
{
    public function start(Request $request)
    {
        $user = Auth::guard('api')->user();
        $quiz = Quiz::find($request->route('id'));

        if ($quiz->anonymous && $user == null) return response()->json(["Dostęp tylko dla zalogowanych."], 401);
        if ($quiz->reentry && $user ?->id !== null)
            if (Game::where('quiz_id', $request->route('id'))->where('user_id', $user ?->id)->first()) return response()->json(["Quiz można rozwiązać tylko raz."], 401);


        $game = new Game();
        $game->uid = (string)Str::uuid();
        $game->quiz_id = $quiz->id;
        $game->user_id = $user ?->id;
        $game->correctAnswers = 0;
        $game->correct_answers_id = [];

        $questionsOrder = array();
        foreach ($quiz->questions as $question) {
            $questionsOrder[] = $question->id;
        }

        if ($quiz->shuffle) {
            shuffle($questionsOrder);
        }

        $questions_queue = [];
        foreach ($questionsOrder as $question) {
            $questions_queue[] = $quiz->questions->find($question);
        }

        $game->questions_queue = $questions_queue;

        $this->GameToRedis($game);

        return response(['game' => $game, 'question' => $this->nextQuestion($game)], 200);
    }

    public function answer(Request $request)
    {
        $game = $this->GameFromRedis($request->route('id'));

        if (!$game) return abort(404);
        if (($game->user_id ?? null) != (Auth::guard('api')->user()->id ?? null)) return abort(403);

        $answer = $request->input('answer');
        $correctAnswers = $game->correct_answers_id;

        if ($answer != 0 && (strtotime(date("Y-m-d h:i:s")) - strtotime($game->updated_at)) / 60 < ($game->load('quiz')->time + 5)) {
            if (in_array($answer, $correctAnswers)) $game->correctAnswers++;
        }

        if (!is_null($game->questions_queue)) return response(['correct' => $correctAnswers, 'question' => $this->nextQuestion($game)], 200);
        Redis::del('game:' . $game->uid);
        if($game->user_id=='') $game->user_id = null;
        unset($game->uid);
        unset($game->questions_queue);
        unset($game->correct_answers_id);
        $game->save();
        return response(['correct' => $correctAnswers, 'finish' => $game->correctAnswers], 200);
    }


    public function nextQuestion(Game $game)
    {
        $questions = $game->questions_queue;
        $question = array_shift($questions);
        $game->questions_queue = $questions;
        $answers = json_decode($question["answers"], true);
        $correct = [];
        foreach ($answers as &$answer) {
            if ($answer["correct"]) $correct[] = $answer["id"];
            unset($answer["correct"]);
        }
        $game->correct_answers_id = $correct;
        $question["answers"] = $answers;
        $this->GameToRedis($game);
        return $question;
    }

    public function GameFromRedis(string $key)
    {
        $game = new Game();
        $r = Redis::hgetall('game:' . $key);
        if (empty($r)) return false;
        $game->uid = $key;
        $game->user_id = $r["user_id"] ?? null;
        $game->quiz_id = $r["quiz_id"];
        $game->questions_queue = (strlen($r["questions_queue"]) > 2 ? json_decode($r["questions_queue"], true) : null);
        $game->correctAnswers = $r["correctAnswers"];
        $game->correct_answers_id = json_decode($r["correct_answers_id"], true);
        $game->updated_at = date_create_from_format('Y-m-d H:i:s', $r["updated_at"]);
        return $game;
    }

    public function GameToRedis(Game $game)
    {
        Redis::hmset('game:' . $game->uid, [
            'quiz_id' => $game->quiz_id,
            'user_id' => $game->user_id,
            'questions_queue' => json_encode($game->questions_queue),
            'correctAnswers' => $game->correctAnswers,
            'correct_answers_id' => json_encode($game->correct_answers_id),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    }
}
