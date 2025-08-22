<?php

use App\Http\Controllers\user\AuthController;
use App\Http\Controllers\user\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('/auth')->group(function () {
    Route::post('/check', [AuthController::class, 'checkAuthenticatedUser']);
    Route::post('/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/getNewToken', [AuthController::class, 'refreshToken']);
});

Route::prefix('/user')->group(function () {
    Route::post('/personal-info', [ProfileController::class, 'savePersonalInfo'])->name('user.personalInfo.save');
    Route::post('/address', [ProfileController::class, 'saveAddress'])->name('user.address.save');
    Route::post('/employment-details', [ProfileController::class, 'saveEmploymentDetails'])->name('user.employmentDetails.save');
    Route::post('/documents', [ProfileController::class, 'saveDocuments'])->name('user.documents.save');
    Route::get('/show-all-employees', [ProfileController::class, 'showEmployeeDetails']);
});

Route::post('/import-users', [ProfileController::class, 'importExcel']); 


// use Nuwave\Lighthouse\Http\GraphQLController;

// Route::post('/employees', GraphQLController::class)
//     ->name('graphql.employees');


// Route::post('/', [ProfileController::class, 'importExcel']);


