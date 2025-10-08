<?php

use App\Http\Controllers\appreciation\AppreciationController;
use App\Http\Controllers\dashboard\DashboardController;
use App\Http\Controllers\holiday\HolidaysController;
use App\Http\Controllers\manage_Leaves\LeaveRequestController;
use App\Http\Controllers\manage_Leaves\LeaveTypeController;
use App\Http\Controllers\manage_Leaves\YearEndLeaveController;
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
    Route::post('/profile-picture/update', [ProfileController::class, 'updateProfilePicture']);
    Route::delete('/profile-picture/delete', [ProfileController::class, 'deleteProfilePicture']);
});


Route::prefix('/resign')->middleware('verify.tokens')->group(function () {
    Route::get('/employees', [ResignationController::class, 'showResignedEmployees']);
    Route::post('/initiate', [ResignationController::class, 'initiateResignation']);
    Route::get('/check', [ResignationController::class, 'checkIfResigned']);
    Route::post('/cancel', [ResignationController::class, 'cancelResignation']);
    Route::post('/response', [ResignationController::class, 'responseToResignation']);
});

Route::prefix('/holidays')->middleware('verify.tokens')->group(function () {
    Route::post('/add', [HolidaysController::class, 'addHolidays']);
    Route::get('/list', [HolidaysController::class, 'showHolidayList']);
    Route::delete('/delete/{id}', [HolidaysController::class, 'deleteHoliday']);
    Route::post('/update', [HolidaysController::class, 'updateHoliday']);
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
    Route::get('/summary', [LeaveRequestController::class, 'leaveSummary'])->name('leave.summary');

});

Route::prefix('leave-types')->middleware('verify.tokens')->group(function () {
    Route::get('/show', [LeaveTypeController::class, 'index']);
    Route::post('/add', [LeaveTypeController::class, 'store']);
    Route::put('/update', [LeaveTypeController::class, 'update']);
    Route::post('/cancel/{id}', [LeaveRequestController::class, 'cancelLeave']);
    Route::get('/pending', [LeaveTypeController::class, 'showPendingLeavesOfAllEmployees']);
});



Route::prefix('forward-encash')->middleware('verify.tokens')->group(function () {
    Route::get('/get', [YearEndLeaveController::class, 'checkIfYearEndProcessNeeded']);
    Route::post('/update', [YearEndLeaveController::class, 'updateYearEndAction']);
    Route::get('/requests', [YearEndLeaveController::class, 'showApprovalRequests']);
    Route::post('/response', [YearEndLeaveController::class, 'saveResponseForEncashment']);
});

Route::prefix('dashboard')->middleware('verify.tokens')->group(function () {
    Route::get('/bday-anniversaries', [DashboardController::class, 'showbirthdayAnniversaries']);
    Route::get('/on-leave', [DashboardController::class, 'showOffThisWeekEmployees']);
    Route::get('/stats', [DashboardController::class, 'showStatsComponentData']);
});




Route::middleware('verify.tokens')->group(function () {
    // Fetch all leaves of the authenticated user
    Route::get('/my-leaves', [LeaveRequestController::class, 'userLeaves']);

    // Fetch leave status of a specific leave
    Route::get('/my-leaves/{id}/status', [LeaveRequestController::class, 'leaveStatus']);
});
