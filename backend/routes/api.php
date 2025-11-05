<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
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

	// 🎪 EVENT CRUD ROUTES - Admin and Organizer only
	Route::middleware('role:super_admin,organizer')->group(function () {
		// Standard CRUD operations
		Route::get('/events', [EventController::class, 'index']);          // GET /api/events
		Route::post('/events', [EventController::class, 'store']);         // POST /api/events
		Route::get('/events/{id}', [EventController::class, 'show']);      // GET /api/events/{id}
		Route::put('/events/{id}', [EventController::class, 'update']);    // PUT /api/events/{id}
		Route::delete('/events/{id}', [EventController::class, 'destroy']); // DELETE /api/events/{id}

		// Additional event actions
		Route::patch('/events/{id}/publish', [EventController::class, 'publish']);     // Publish event
		Route::patch('/events/{id}/cancel', [EventController::class, 'cancel']);       // Cancel event
		Route::get('/events/{id}/statistics', [EventController::class, 'statistics']); // Get event stats
		Route::post('/events/{id}/duplicate', [EventController::class, 'duplicate']);  // Duplicate event

		// Image management
		Route::post('/events/{id}/images', [EventController::class, 'addImage']);      // Add image
		Route::delete('/events/{id}/images', [EventController::class, 'removeImage']); // Remove image
	});
});
