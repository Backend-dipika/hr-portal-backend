<?php

use App\Http\Controllers\appreciation\AppreciationController;
use App\Http\Controllers\holiday\holidaysController;
use App\Http\Controllers\manage_Leaves\LeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\resignation\ResignationController;
use App\Http\Controllers\user\ProfileController;
use App\Http\Controllers\user\AuthController;
use App\Http\Controllers\user\RegistrationController;
use App\Models\Appreciation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Http\GraphQLController;


Route::options('{any}', function (Request $request) {
    return response()->json([], 200);
})->where('any', '.*');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('/auth')->group(function () {
    Route::post('/check', [AuthController::class, 'checkAuthenticatedUser']);
    Route::post('/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/getNewToken', [AuthController::class, 'refreshToken']);
    Route::post('/logoff', [AuthController::class, 'logout']);
    Route::get('/getuserinfo', [AuthController::class, 'sendUserDetails'])->middleware('verify.tokens');
});

Route::prefix('/user')->middleware('verify.tokens')->group(function () {
    Route::get('/form-options', [RegistrationController::class, 'sendFormOptions'])->name('user.form.options');
    Route::post('/import', [RegistrationController::class, 'importExcel']);
    Route::post('/personal-info', [RegistrationController::class, 'savePersonalInfo'])->name('user.personalInfo.save');
    Route::post('/address', [RegistrationController::class, 'saveAddress'])->name('user.address.save');
    Route::post('/employment-details', [RegistrationController::class, 'saveEmploymentDetails'])->name('user.employmentDetails.save');
    Route::post('/documents', [RegistrationController::class, 'saveDocuments'])->name('user.documents.save');
});

Route::middleware('verify.tokens')->group(function () {
    Route::put('/profile/{id}', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/get-roles', [ProfileController::class, 'sendRoles']);
    Route::get('/get-departments', [ProfileController::class, 'sendDepartments']);
});


Route::prefix('/resign')->middleware('verify.tokens')->group(function () {
    Route::get('/employees', [ResignationController::class, 'showResignedEmployees']);
    Route::post('/initiate', [ResignationController::class, 'initiateResignation']);
    Route::get('/check', [ResignationController::class, 'checkIfResigned']);
    Route::post('/cancel', [ResignationController::class, 'cancelResignation']);
    Route::post('/response', [ResignationController::class, 'responseToResignation']);
});

Route::prefix('/holidays')->middleware('verify.tokens')->group(function () {
    Route::post('/add', [holidaysController::class, 'addHolidays']);
    Route::get('/list', [holidaysController::class, 'showHolidayList']);
});

Route::prefix('/appreciation')->middleware('verify.tokens')->group(function () {
    Route::post('/send', [AppreciationController::class, 'sendAppreiation']);
    Route::get('/get-user', [AppreciationController::class, 'sendUsername']);
});

Route::prefix('/notification')->middleware('verify.tokens')->group(function () {
    Route::get('/all', [NotificationController::class, 'getNotifications']);
});


Route::middleware('verify.tokens')->post('/leaves', [LeaveRequestController::class, 'store']);
Route::middleware('verify.tokens')->get('/leave-requests', [LeaveRequestController::class, 'index']);

Route::prefix('leave')->middleware('verify.tokens')->group(function () {
    Route::post('/{id}/approve', [LeaveRequestController::class, 'approveLeave'])->name('leave.approve');
    Route::post('/{id}/reject', [LeaveRequestController::class, 'rejectLeave'])->name('leave.reject');
});