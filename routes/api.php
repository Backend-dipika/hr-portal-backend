<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\user\AuthController;
use App\Http\Controllers\user\RegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Http\GraphQLController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('/auth')->group(function () {
    Route::post('/check', [AuthController::class, 'checkAuthenticatedUser']);
    Route::post('/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/getNewToken', [AuthController::class, 'refreshToken'])->middleware('auth:api');
    Route::post('/logoff', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/getuserinfo', [AuthController::class, 'sendUserDetails'])->middleware('auth:api');
});

Route::prefix('/user')->middleware('auth:api')->group(function () {
    Route::get('/form-options', [RegistrationController::class, 'sendFormOptions'])->name('user.form.options');
    Route::post('/import', [RegistrationController::class, 'importExcel']);
    Route::post('/personal-info', [RegistrationController::class, 'savePersonalInfo'])->name('user.personalInfo.save');
    Route::post('/address', [RegistrationController::class, 'saveAddress'])->name('user.address.save');
    Route::post('/employment-details', [RegistrationController::class, 'saveEmploymentDetails'])->name('user.employmentDetails.save');
    Route::post('/documents', [RegistrationController::class, 'saveDocuments'])->name('user.documents.save');
    Route::get('/show-all-employees', [RegistrationController::class, 'showEmployeeDetails']);
});

Route::middleware('auth:api')->group(function () {
    Route::put('/profile/{id}', [ProfileController::class, 'update'])->name('profile.update');
});

//added comment