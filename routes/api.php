<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\VolunteerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckEventProgram;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\QRController;
use App\Http\Controllers\FileUploadController;

Route::middleware(['web'])->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication Routes
Route::prefix('auth')->middleware(['web'])->group(function () {
    // Registration
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('verify-otp', [RegisterController::class, 'verifyOtp']);
    Route::post('create-password', [RegisterController::class, 'createPassword']);

    // Login/Logout
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout'])->middleware(['web', 'auth']);

    // Password Reset
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendOtp']);
    Route::post('verify-reset-otp', [ForgotPasswordController::class, 'verifyOtp']);
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);
});

// Event Routes
// Protected Organizer Routes
Route::middleware(['web', 'auth', CheckRole::class.':organizer'])->group(function () {
    // Archive routes for organizers (MUST come before parameterized routes)
    // Temporarily bypass role check to debug
    Route::get('events/archived', [EventController::class, 'getArchivedEvents'])->withoutMiddleware(CheckRole::class);

    Route::post('events', [EventController::class, 'store']);
    Route::put('events/{event}', [EventController::class, 'update'])->middleware(CheckEventProgram::class);
    Route::delete('events/{event}', [EventController::class, 'destroy'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/start', [EventController::class, 'startEvent'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/end', [EventController::class, 'endEvent'])->middleware(CheckEventProgram::class);
    Route::get('events/{event}/analytics', [EventController::class, 'getAnalytics'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/attendance', [EventController::class, 'markAttendance'])->middleware(CheckEventProgram::class);
    Route::get('events/{event}/attendance', [EventController::class, 'getAttendance'])->middleware(CheckEventProgram::class);
    Route::get('events/{event}/feedback', [EventController::class, 'getFeedback'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/scan-qr', [AttendanceController::class, 'scanQR'])->middleware(CheckEventProgram::class);
    Route::get('events/{event}/attendance-status', [AttendanceController::class, 'getAttendanceStatus'])->middleware(CheckEventProgram::class);

    // Post-evaluation routes for organizers
    Route::get('events/{event}/post-evaluations', [EventController::class, 'getPostEvaluations'])->middleware(CheckEventProgram::class);

    // Suggestions route for organizers
    Route::get('events/{event}/suggestions', [EventController::class, 'getSuggestions'])->middleware(CheckEventProgram::class);
});

// Public Event Routes (require authentication since controller uses $request->user())
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show'])->middleware(CheckEventProgram::class);
});

// Truly public routes (no authentication required)
Route::middleware(['web'])->group(function () {
    Route::get('evaluation-questions', [EventController::class, 'getEvaluationQuestions']);
});

// Protected Volunteer Routes
Route::middleware(['web', 'auth', CheckRole::class.':volunteer'])->group(function () {
    // Archive routes for volunteers (MUST come before parameterized routes)
    Route::get('events/archived', [VolunteerController::class, 'getArchivedEvents']);

    Route::post('events/{event}/register', [VolunteerController::class, 'registerForEvent'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/unregister', [VolunteerController::class, 'unregisterFromEvent'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/things-brought', [VolunteerController::class, 'submitThingsBrought'])->middleware(CheckEventProgram::class);
    Route::post('events/{event}/suggestions', [VolunteerController::class, 'submitSuggestion'])->middleware(CheckEventProgram::class);
    Route::get('event-history', [VolunteerController::class, 'getEventHistory']);
    Route::post('events/{event}/feedback', [EventController::class, 'submitFeedback'])->middleware(CheckEventProgram::class);

    // Post-evaluation routes for volunteers
    Route::post('events/{event}/post-evaluation', [EventController::class, 'submitPostEvaluation'])->middleware(CheckEventProgram::class);

    // File upload routes for volunteers
    Route::post('upload/reflection-paper', [FileUploadController::class, 'uploadReflectionPaper']);
    Route::delete('upload/reflection-paper', [FileUploadController::class, 'deleteReflectionPaper']);
    Route::get('upload/file-info', [FileUploadController::class, 'getFileInfo']);

    // QR Code Routes for Volunteers
    Route::post('events/{event}/generate-qr', [QRController::class, 'generateQR'])->middleware(CheckEventProgram::class);
    Route::get('events/{event}/qr-status', [QRController::class, 'getQRStatus'])->middleware(CheckEventProgram::class);
});

// Protected User Routes (for both organizers and volunteers)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('user/profile', [AuthController::class, 'profile']);
    Route::put('user/profile', [AuthController::class, 'updateProfile']);

    // Notification routes
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('user/qr', [AuthController::class, 'getUserQR']);
});
