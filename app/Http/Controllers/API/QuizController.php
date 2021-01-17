<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Rules\RequireCorrect;
use Illuminate\Support\Str;


class QuizController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules(), [], $this->niceNames());
        if ($validator->fails()) return response()->json($validator->messages(), 422);

        $quiz = new Quiz();
        $quiz->name = $request->input('name');
        $quiz->thumbnail = $request->input('thumbnail');
        $quiz->shuffle = $request->input('shuffle');
        $quiz->public = $request->input('public');
        $quiz->anonymous = $request->input('anonymous');
        $quiz->reentry = $request->input('reentry');
        $quiz->time = $request->input('time');
        $quiz->questions_count = count($request->input('questions'));
        $quiz->creator_id = auth()->user()->id;
        $quiz->save();

        $i = 0;
        foreach ($request->input('questions') as $questionRequest) {
            $question = new Question();
            $question->order = ++$i;
            $question->question = $questionRequest["text"];
            $id = 0;
            foreach ($questionRequest["answers"] as &$answer) $answer["id"] = ++$id;
            $question->answers = json_encode($questionRequest["answers"]);
            $quiz->questions()->save($question);
        }

        return response(['quiz_id' => $quiz->id], 200);
    }

    public function read(Request $request)
    {
        $quiz = Quiz::find($request->route('id'));
        if ($quiz == null) return abort(404);

        if ($request->query('edit') && Auth::guard('api')->user()->id == $quiz->creator_id) $quiz->load('questions');
        return response(['quiz' => $quiz->load('creator')], 200);
    }

    public function update(Request $request)
    {
        $quiz = Quiz::find($request->route('id'));
        if ($quiz->creator_id != auth()->user()->id) abort(401);

        $validator = Validator::make($request->all(), $this->rules(), [], $this->niceNames());
        if ($validator->fails()) return response()->json($validator->messages(), 422);

        $quiz->name = $request->input('name');
        $quiz->thumbnail = $request->input('thumbnail');
        $quiz->shuffle = $request->input('shuffle');
        $quiz->public = $request->input('public');
        $quiz->anonymous = $request->input('anonymous');
        $quiz->reentry = $request->input('reentry');
        $quiz->time = $request->input('time');
        $quiz->questions_count = count($request->input('questions'));
        $quiz->save();

        $quiz->load('questions');
        foreach ($quiz->questions as $question) {
            $deleted = true;
            foreach ($request->input('questions') as $questionRequest) {
                if ($question->id == ($questionRequest["id"] ?? null)) $deleted = false;
            }
            if ($deleted) $question->delete();
        }

        $i = 0;
        foreach ($request->input('questions') as $questionRequest) {
            $id = 0;
            foreach ($questionRequest["answers"] as &$answer) $answer["id"] = ++$id;
            $quiz->questions()->updateOrCreate(['id' => $questionRequest["id"] ?? null], [
                'order' => ++$i,
                'question' => $questionRequest["text"],
                'answers' => json_encode($questionRequest["answers"]),
            ]);
        }

        return response(['quiz_id' => $quiz->id], 200);
    }

    public function delete(Request $request)
    {
        $quiz = Quiz::find($request->query('id'));
        if ($quiz->creator_id != auth()->user()->id) return abort(401);
        $quiz->delete();
        return $this->getUserQuizzes($request);
    }

    public function getQuizzes(Request $request)
    {
        $isLogged = Auth::guard('api')->user() == null ? false : true;
        $quizzes = Quiz::withCount('game');
        if (!$isLogged)
            $quizzes->where('public', '0');

        if ($request->query('search')) $quizzes->where('name', 'like', '%' . $request->query('search') . '%');

        switch ($request->query('sort')) {
            case 'popular':
                $quizzes->orderBy('game_count', $request->query('order') ?? 'DESC');
                break;
            case 'recent':
                $quizzes->orderBy('created_at', $request->query('order') ?? 'DESC');
                break;
        }

        $paginator = $quizzes->paginate(20)->withQueryString();

        return response()->json(['quizzes' => $quizzes->get()->makeHidden(['shuffle', 'public', 'anonymous', 'reentry', 'time', 'questions_count', 'created_at', 'game_count']), 'pages' => $paginator->lastPage()], 200);
    }

    public function getUserQuizzes(Request $request)
    {
        $quizzes = Quiz::where('creator_id', auth()->user()->id)->orderBy('created_at')->paginate(5)->withQueryString();
        return response(['quizzes' => $quizzes->makeHidden(['created_at', 'questions_count', 'shuffle', 'public', 'anonymous', 'reentry', 'time']), 'pages' => $quizzes->lastPage()], 200);
    }

    public function uploadThumbnail(Request $request)
    {
        if (!$request->hasFile("image")) return abort(400);
        $file = $request->file("image");
        if (!$file->isValid()) return abort(400);
        $path = public_path() . '/uploads/images/thumbnails/';

        $name = auth()->user()->name . substr(Str::uuid()->toString(), 0, 16) . '.' . $file->extension();
        $file->move($path, $name);

        return response()->json(['thumbnail' => 'http://localhost:8000/uploads/images/thumbnails/' . $name], 200);
    }

    public function getRanking(Request $request)
    {
        $games = Game::where('quiz_id', $request->route('id'))->orderBy('correctAnswers', 'DESC')->limit(10)->get()->makeHidden(['id', 'quiz_id'])->load('user');
        return response()->json(['ranking' => $games], 200);
    }

    public function rules()
    {
        return [
            'name' => 'required|min:5|max:128',
            'shuffle' => 'boolean|required',
            'public' => 'boolean|required',
            'anonymous' => 'boolean|required',
            'reentry' => 'boolean|required',
            'time' => 'integer|required',
            'questions' => 'array|required',
            'questions.*.text' => 'required|string|min:3|max:200',
            'questions.*.answers' => new RequireCorrect,
            'questions.*.answers.*.text' => 'required|string|min:2|max:100',
            'questions.*.answers.*.correct' => 'boolean|required'
        ];
    }

    public function niceNames()
    {
        return [
            'name' => 'nazwa quizu',
            'shuffle' => 'ustawień',
            'public' => 'ustawień',
            'anonymous' => 'ustawień',
            'reentry' => 'ustawień',
            'time' => 'z ustawieniem czasu',
            'questions' => 'pytań',
            'questions.*.text' => 'z treścią pytania',
            'questions.*.answers.*.text' => 'z treścią odpowiedzi',
            'questions.*.answers.*.correct' => 'z prawidłową odpowiedzią'
        ];
    }
}
