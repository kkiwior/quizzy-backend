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
            'name' => 'required|min:3|max:32|unique:users|string',
            'email' => 'email|required|unique:users',
            'password' => 'required|confirmed|min:6|max:32|string'
        ], [], [
            'name' => 'nazwa użytkownika',
            'password' => 'hasło'
        ]);

        if ($validator->fails()) return response()->json($validator->messages(), 422);

        $request->merge(['password' => bcrypt($request->password)]);

        $user = User::create($request->all());

        $accessToken = $user->createToken('authToken')->accessToken;

        return response(['user' => $user, 'access_token' => $accessToken], 200);
    }

    public function login(Request $request)
    {
        $loginData = $request->validate([
            'name' => 'required',
            'password' => 'required'
        ]);

        if (!auth()->attempt($loginData)) return response()->json(['password' => 'Niepoprawne dane logowania.'], 422);

        $accessToken = auth()->user()->createToken('authToken')->accessToken;

        return response(['user' => auth()->user(), 'access_token' => $accessToken], 200);
    }

    public function uploadAvatar(Request $request)
    {
        if (!$request->hasFile("image")) return abort(400);
        $file = $request->file("image");
        if (!$file->isValid()) return abort(400);
        if (exif_imagetype($file) !== IMAGETYPE_WEBP) return abort(400);
        $path = public_path() . config('app.cdn') . 'avatars/';
        $user = auth()->user();
        $name = $user->name . substr(Str::uuid()->toString(), 0, 8) . '.' . $file->extension();
        $file->move($path, $name);
        $user->avatar = config('app.url') . config('app.cdn') . 'avatars/' . $name;
        $user->save();

        return response()->json(['avatar' => $user->avatar], 200);
    }
}
