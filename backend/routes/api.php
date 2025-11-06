<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 🟢 Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// 🟢 Public routes for viewing content
Route::get('/events/{eventId}/reviews', [ReviewController::class, 'getEventReviews']);
Route::get('/events/{eventId}/comments', [CommentController::class, 'getEventComments']);

// 🔒 Protected routes (need Sanctum token)
Route::middleware('auth:sanctum')->group(function () {

	// User info & profile
	Route::get('/user', [AuthController::class, 'user']);
	Route::post('/logout', [AuthController::class, 'logout']);
	Route::get('/profile', [UserController::class, 'profile']);
	Route::put('/profile', [UserController::class, 'updateProfile']);

	// 🎪 EVENT CRUD ROUTES - Admin and Organizer only
	Route::middleware('role:super_admin,organizer')->group(function () {
		// Event CRUD
		Route::get('/events', [EventController::class, 'index']);
		Route::post('/events', [EventController::class, 'store']);
		Route::get('/events/{id}', [EventController::class, 'show']);
		Route::put('/events/{id}', [EventController::class, 'update']);
		Route::delete('/events/{id}', [EventController::class, 'destroy']);

		// Event actions
		Route::patch('/events/{id}/publish', [EventController::class, 'publish']);
		Route::patch('/events/{id}/cancel', [EventController::class, 'cancel']);
		Route::get('/events/{id}/statistics', [EventController::class, 'statistics']);
		Route::post('/events/{id}/duplicate', [EventController::class, 'duplicate']);

		// 🎫 TICKET CRUD ROUTES - Admin and Organizer only
		Route::get('/events/{eventId}/tickets', [TicketController::class, 'index']);
		Route::post('/events/{eventId}/tickets', [TicketController::class, 'store']);
		Route::get('/tickets/{id}', [TicketController::class, 'show']);
		Route::put('/tickets/{id}', [TicketController::class, 'update']);
		Route::delete('/tickets/{id}', [TicketController::class, 'destroy']);
		Route::get('/tickets/{id}/statistics', [TicketController::class, 'statistics']);
		Route::post('/tickets/{id}/duplicate', [TicketController::class, 'duplicate']);

		// 📊 ANALYTICS ROUTES
		Route::get('/events/{eventId}/analytics', [AnalyticsController::class, 'getEventAnalytics']);
		Route::get('/analytics/dashboard', [AnalyticsController::class, 'getDashboardAnalytics']);
		Route::get('/analytics/compare', [AnalyticsController::class, 'compareEvents']);
		Route::get('/analytics/top-events', [AnalyticsController::class, 'getTopEvents']);

		// Transaction statistics
		Route::get('/transactions/statistics', [TransactionController::class, 'statistics']);
	});

	// ⭐ REVIEW CRUD ROUTES - Users can manage their own, Admin can manage all
	Route::get('/reviews', [ReviewController::class, 'index']);
	Route::post('/reviews', [ReviewController::class, 'store']);
	Route::get('/reviews/{id}', [ReviewController::class, 'show']);
	Route::put('/reviews/{id}', [ReviewController::class, 'update']);
	Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

	// 💬 COMMENT CRUD ROUTES - Users can manage their own, Admin can manage all
	Route::get('/comments', [CommentController::class, 'index']);                      // List user's comments (or all for admin)
	Route::post('/comments', [CommentController::class, 'store']);                    // Create comment/reply
	Route::get('/comments/{id}', [CommentController::class, 'show']);                 // Show comment
	Route::put('/comments/{id}', [CommentController::class, 'update']);               // Update comment
	Route::delete('/comments/{id}', [CommentController::class, 'destroy']);           // Delete comment
	Route::get('/comments/{id}/replies', [CommentController::class, 'getReplies']);   // Get comment replies

	// Public route for incrementing views
	Route::post('/events/{eventId}/analytics/view', [AnalyticsController::class, 'incrementViews']);

	// 💳 TRANSACTION ROUTES - All authenticated users
	Route::get('/transactions', [TransactionController::class, 'index']);                     // List transactions
	Route::post('/transactions/purchase', [TransactionController::class, 'purchase']);        // Purchase tickets
	Route::get('/transactions/{id}', [TransactionController::class, 'show']);                 // Show transaction
	Route::post('/transactions/{id}/refund', [TransactionController::class, 'requestRefund']); // Request refund
	Route::get('/my-tickets', [TransactionController::class, 'myTickets']);                   // User's tickets

	// 🟣 Admin-only routes
	Route::middleware('role:super_admin')->group(function () {
		// Ticket management
		Route::get('/tickets', [TicketController::class, 'allTickets']);

		// Review management
		Route::patch('/reviews/{id}/approve', [ReviewController::class, 'approve']);
		Route::patch('/reviews/{id}/reject', [ReviewController::class, 'reject']);
		Route::get('/reviews/statistics', [ReviewController::class, 'statistics']);

		// Comment management
		Route::patch('/comments/{id}/approve', [CommentController::class, 'approve']);
		Route::patch('/comments/{id}/reject', [CommentController::class, 'reject']);
		Route::get('/comments/statistics', [CommentController::class, 'statistics']);

		// User management
		Route::get('/users', [UserController::class, 'index']);
		Route::post('/users', [UserController::class, 'store']);
		Route::get('/users/{id}', [UserController::class, 'show']);
		Route::put('/users/{id}', [UserController::class, 'update']);
		Route::delete('/users/{id}', [UserController::class, 'destroy']);
		Route::patch('/users/{id}/role', [UserController::class, 'changeRole']);
		Route::patch('/users/{id}/ban', [UserController::class, 'banUser']);
		Route::patch('/users/{id}/unban', [UserController::class, 'unbanUser']);
		Route::get('/users/statistics', [UserController::class, 'statistics']);
	});
});
