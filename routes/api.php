<?php

use sanctum;
use Illuminate\Http\Request;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QRController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Middleware\CheckEventProgram;
use App\Http\Controllers\VolunteerController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ForgotPasswordController;


Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Test endpoint for debugging CORS and connectivity
Route::get('/test-connection', function () {
    return response()->json([
        'message' => 'Connection successful!',
        'timestamp' => now(),
        'cors_working' => true
    ]);
});

// Debug cookie and session endpoint
Route::get('/debug-cookies', function (Request $request) {
    // Force session start
    $request->session()->start();
    $request->session()->put('test_key', 'test_value');

    return response()->json([
        'session_config' => [
            'driver' => config('session.driver'),
            'domain' => config('session.domain'),
            'secure' => config('session.secure'),
            'same_site' => config('session.same_site'),
            'http_only' => config('session.http_only'),
        ],
        'request_info' => [
            'origin' => $request->header('Origin'),
            'referer' => $request->header('Referer'),
            'user_agent' => $request->header('User-Agent'),
            'has_session' => $request->hasSession(),
            'session_id' => $request->session()->getId(),
            'session_data' => $request->session()->all(),
        ],
        'cookies_sent' => $request->cookies->all(),
        'app_env' => config('app.env'),
        'app_url' => config('app.url'),
    ])->withHeaders([
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Origin' => $request->header('Origin') ?: '*',
    ]);
});

// Token-based user info endpoint
Route::middleware('auth:sanctum')->get('/auth/user', function (Request $request) {
    return response()->json([
        'user' => $request->user()
    ]);
});

// Test token authentication endpoint
Route::middleware('auth:sanctum')->get('/auth/test-token', function (Request $request) {
    return response()->json([
        'message' => 'Token authentication successful!',
        'user' => $request->user(),
        'timestamp' => now()
    ]);
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    // Registration (no auth required)
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('verify-otp', [RegisterController::class, 'verifyOtp']);
    Route::post('create-password', [RegisterController::class, 'createPassword']);

    // Login (no auth required)
    Route::post('login', [LoginController::class, 'login']);

    // Logout (requires token auth)
    Route::post('logout', [LoginController::class, 'logout'])->middleware(['auth:sanctum']);

    // Password Reset (no auth required)
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendOtp']);
    Route::post('verify-reset-otp', [ForgotPasswordController::class, 'verifyOtp']);
    Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);
});

// Event Routes
// Protected Organizer Routes
Route::middleware(['auth:sanctum', CheckRole::class.':organizer'])->group(function () {
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
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show'])->middleware(CheckEventProgram::class);
});

// Truly public routes (no authentication required)
Route::get('evaluation-questions', [EventController::class, 'getEvaluationQuestions']);

// Protected Volunteer Routes
Route::middleware(['auth:sanctum', CheckRole::class.':volunteer'])->group(function () {
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



    // QR Code Routes for Volunteers
    Route::post('events/{event}/generate-qr', [QRController::class, 'generateQR'])->middleware(CheckEventProgram::class);
    Route::get('events/{event}/qr-status', [QRController::class, 'getQRStatus'])->middleware(CheckEventProgram::class);
});

// Protected User Routes (for both organizers and volunteers)
Route::middleware(['auth:sanctum'])->group(function () {
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
