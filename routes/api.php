<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\SquadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'database' => 'connected',
        'version' => '1.0.0'
    ]);
});

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // OAuth routes
    Route::get('/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::get('/microsoft', [AuthController::class, 'redirectToMicrosoft']);
    Route::get('/microsoft/callback', [AuthController::class, 'handleMicrosoftCallback']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Squad routes
    Route::apiResource('squads', SquadController::class);
    Route::get('/squads/{id}/members', [SquadController::class, 'members']);
    Route::post('/squads/{id}/members', [SquadController::class, 'addMember']);
    Route::delete('/squads/{id}/members/{userId}', [SquadController::class, 'removeMember']);
    Route::get('/squads/{id}/sprints', [SquadController::class, 'sprints']);
    Route::get('/squads/{id}/active-sprint', [SquadController::class, 'activeSprint']);

    // Attendance routes
    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::get('/attendance/squad/{squadId}/today', [AttendanceController::class, 'squadToday']);
    Route::get('/attendance/stats', [AttendanceController::class, 'stats']);
    Route::apiResource('attendance', AttendanceController::class);

    // Leave request routes
    Route::post('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve']);
    Route::post('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject']);
    Route::get('/leave-requests/pending-approvals', [LeaveRequestController::class, 'pendingApprovals']);
    Route::get('/leave-requests/calendar', [LeaveRequestController::class, 'calendar']);
    Route::apiResource('leave-requests', LeaveRequestController::class);

    // User management routes (Admin only)
    Route::apiResource('users', UserController::class);
    Route::patch('/users/{id}/status', [UserController::class, 'toggleStatus']);

    // Audit log routes
    Route::get('/audit-logs', [UserController::class, 'auditLogs']);
});
