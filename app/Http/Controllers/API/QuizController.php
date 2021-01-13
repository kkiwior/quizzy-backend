<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Rules\RequireCorrect;


class QuizController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());
        if($validator->fails()) return response()->json($validator->messages(), 422);

        $quiz = new Quiz();
        $quiz->name = $request->input('name');
        $quiz->shuffle = $request->input('shuffle');
        $quiz->public = $request->input('public');
        $quiz->anonymous = $request->input('anonymous');
        $quiz->reentry = $request->input('reentry');
        $quiz->time = $request->input('time');
        $quiz->questions_count = count($request->input('questions'));
        $quiz->creator_id = auth()->user()->id;
        $quiz->save();

        foreach($request->input('questions') as $questionRequest)
        {
            $question = new Question();
            $question->question = $questionRequest["text"];
            $question->answers = json_encode($questionRequest["answers"]);
            $quiz->questions()->save($question);
        }

        return response([ 'quiz_id' => $quiz->id ]);
    }

    public function delete(Request $request)
    {
        $quiz = Quiz::find($request->query('id'));
        if($quiz->creator_id != auth()->user()->id) response()->json([], 401);
        $quiz->delete();
        return $this->getUserQuizzes($request);
    }

    public function read(Request $request)
    {
        $quiz = Quiz::find($request->route('id'));

        return response(['quiz' => $quiz->load('creator')]);
    }

    public function getUserQuizzes(Request $request)
    {
        $quizzes = Quiz::where('creator_id', auth()->user()->id)->orderBy('created_at')->paginate(5)->withQueryString();
        return response(['quizzes' => $quizzes->makeHidden(['created_at', 'questions_count', 'shuffle', 'public', 'anonymous', 'reentry', 'time']), 'pages' => $quizzes->lastPage()]);
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
            'questions.*.text' => 'required|string',
            'questions.*.answers' => new RequireCorrect,
            'questions.*.answers.*.text' => 'required|string',
            'questions.*.answers.*.correct' => 'boolean|required'
        ];
    }
}
