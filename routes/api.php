<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers;


Route::post('/user/register', 'App\Http\Controllers\API\AuthController@register');
Route::post('/user/login', 'App\Http\Controllers\API\AuthController@login');
Route::post('/user/upload_avatar', 'App\Http\Controllers\API\AuthController@uploadAvatar')->middleware('auth:api');
Route::get('/user/me', function(Request $request){ return $request->user(); })->middleware('auth:api');


Route::post('/quiz/create', 'App\Http\Controllers\API\QuizController@create')->middleware('auth:api');
Route::get('/quiz/{id}', 'App\Http\Controllers\API\QuizController@read');
Route::delete('/quiz', 'App\Http\Controllers\API\QuizController@delete')->middleware('auth:api');

Route::get('/quizzes/my', 'App\Http\Controllers\API\QuizController@getUserQuizzes')->middleware('auth:api');


Route::post('/game/start/{id}', 'App\Http\Controllers\API\GameController@start');
