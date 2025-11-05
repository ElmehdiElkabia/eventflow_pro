<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
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

// 🟢 Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 🔒 Protected routes (need Sanctum token)
Route::middleware('auth:sanctum')->group(function () {

    // User info
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 🟣 Only for ADMIN users
    Route::middleware('role:super_admin')->group(function () {
        Route::post('/users/change-role', [AuthController::class, 'changeRole']);
    });

    // 🟢 Organizer-only routes example
    Route::middleware('role:organizer')->group(function () {
        Route::get('/events/manage', function () {
            return response()->json(['message' => 'Welcome Organizer!']);
        });
    });

    // 🟢 Routes protected by permissions
    Route::middleware('permission:edit_event')->group(function () {	
        Route::get('/events/edit', function () {
            return response()->json(['message' => 'You can edit events!']);
        });
    });
});