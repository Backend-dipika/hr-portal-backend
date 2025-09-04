<?php


use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

Route::get('login', function () {
    return response()->json(['message' => 'please LogIn'], 401); //401 Unauthorized
})->name('login');

Route::get('/protected', function (Request $request) {
    return response()->json(['message' => 'This is a protected Route'], 401);
})->middleware('auth:api');

// Route::post('/import-users', [ProfileController::class, 'importExcel']);



