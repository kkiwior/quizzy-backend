<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:3|max:32|unique:users',
            'email' => 'email|required|unique:users',
            'password' => 'required|confirmed|min:6|max:32'
        ], [
            'name.unique' => 'Nazwa użytkownika jest zajęta.',
            'email.unique'=> 'Email jest zajęty.'
        ]);

        if($validator->fails()) return response()->json($validator->messages(), 422);

        $request->merge(['password' => bcrypt($request->password)]);

        $user = User::create($request->all());

        $accessToken = $user->createToken('authToken')->accessToken;

        return response([ 'user' => $user, 'access_token' => $accessToken]);
    }

    public function login(Request $request)
    {
        $loginData = $request->validate([
           'name' => 'required',
           'password' => 'required'
        ]);

        if(!auth()->attempt($loginData)) return response()->json(['password' => 'Niepoprawne dane logowania.'], 422);

        $accessToken = auth()->user()->createToken('authToken')->accessToken;

        return response(['user' => auth()->user(), 'access_token' => $accessToken]);
    }

    public function uploadAvatar(Request $request) {
        if(!$request->hasFile("image")) return response()->json(['Wystąpił problem podczas wgrywania pliku.'], 400);
        $file = $request->file("image");
        if(!$file->isValid()) return response()->json(['Niepoprawny plik.'], 400);
        $path = public_path() . '/uploads/images/avatars/';
        $user = auth()->user();

        $name = $user->name . substr(Str::uuid()->toString(), 0, 8) . '.' . $file->extension();
        $file->move($path, $name);
        $user->avatar = $name;
        $user->save();

        return response()->json(['avatar' => $user->avatar], 200);
    }
}
