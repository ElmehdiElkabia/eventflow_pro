<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TicketController;
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

// 🟢 Public review routes (anyone can view approved reviews)
Route::get('/events/{eventId}/reviews', [ReviewController::class, 'getEventReviews']);

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

		// 🎫 TICKET CRUD ROUTES - Admin and Organizer only
		// Tickets for specific event
		Route::get('/events/{eventId}/tickets', [TicketController::class, 'index']);          // GET /api/events/1/tickets
		Route::post('/events/{eventId}/tickets', [TicketController::class, 'store']);        // POST /api/events/1/tickets

		// Individual ticket operations
		Route::get('/tickets/{id}', [TicketController::class, 'show']);                      // GET /api/tickets/1
		Route::put('/tickets/{id}', [TicketController::class, 'update']);                   // PUT /api/tickets/1
		Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);               // DELETE /api/tickets/1

		// Ticket actions
		Route::get('/tickets/{id}/statistics', [TicketController::class, 'statistics']);    // GET /api/tickets/1/statistics
		Route::post('/tickets/{id}/duplicate', [TicketController::class, 'duplicate']);     // POST /api/tickets/1/duplicate


	});
	// ⭐ REVIEW CRUD ROUTES - Users can manage their own, Admin can manage all
	Route::get('/reviews', [ReviewController::class, 'index']);                    // List user's reviews (or all for admin)
	Route::post('/reviews', [ReviewController::class, 'store']);                  // Create review
	Route::get('/reviews/{id}', [ReviewController::class, 'show']);               // Show review
	Route::put('/reviews/{id}', [ReviewController::class, 'update']);             // Update review
	Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);         // Delete review

	// 🟣 Admin-only routes
	Route::middleware('role:super_admin')->group(function () {
		Route::get('/tickets', [TicketController::class, 'allTickets']);
		Route::post('/users/change-role', [AuthController::class, 'changeRole']);

		// Review admin actions
		Route::patch('/reviews/{id}/approve', [ReviewController::class, 'approve']);   // Approve review
		Route::patch('/reviews/{id}/reject', [ReviewController::class, 'reject']);     // Reject review
		Route::get('/reviews/statistics', [ReviewController::class, 'statistics']);    // Review statistics
	});
});
