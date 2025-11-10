<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews
     * GET /api/reviews
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Review::with(['event:id,title', 'user:id,name']);
            
            // If user is not admin, only show their own reviews
            if (!$user->hasRole('super_admin')) {
                $query->where('user_id', $user->id);
            }
            
            // Apply filters
            if ($request->has('event_id') && $request->event_id) {
                $query->where('event_id', $request->event_id);
            }
            
            if ($request->has('rating') && $request->rating) {
                $query->where('rating', $request->rating);
            }
            
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                      ->orWhereHas('event', function($eq) use ($search) {
                          $eq->where('title', 'like', "%{$search}%");
                      });
                });
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 15);
            $reviews = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $reviews->items(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total()
                ],
                'message' => 'Reviews retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display reviews for a specific event (Public)
     * GET /api/events/{eventId}/reviews
     */
    public function getEventReviews(Request $request, $eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $query = Review::where('event_id', $eventId)
                          ->where('status', 'approved')
                          ->with(['user:id,name']);
            
            // Apply filters
            if ($request->has('rating') && $request->rating) {
                $query->where('rating', $request->rating);
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 10);
            $reviews = $query->paginate($perPage);
            
            // Calculate review statistics
            $allReviews = Review::where('event_id', $eventId)->where('status', 'approved');
            $stats = [
                'total_reviews' => $allReviews->count(),
                'average_rating' => round($allReviews->avg('rating') ?? 0, 2),
                'rating_distribution' => [
                    '5' => $allReviews->where('rating', 5)->count(),
                    '4' => $allReviews->where('rating', 4)->count(),
                    '3' => $allReviews->where('rating', 3)->count(),
                    '2' => $allReviews->where('rating', 2)->count(),
                    '1' => $allReviews->where('rating', 1)->count(),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $reviews->items(),
                'statistics' => $stats,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total()
                ],
                'message' => 'Event reviews retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving event reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created review
     * POST /api/reviews
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'event_id' => 'required|exists:events,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user already reviewed this event
            $existingReview = Review::where('event_id', $request->event_id)
                                   ->where('user_id', $user->id)
                                   ->first();
            
            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this event'
                ], 422);
            }

            // Check if event exists and has ended (optional business rule)
            $event = Event::findOrFail($request->event_id);
            if ($event->end_date > now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review events that have ended'
                ], 422);
            }

            $reviewData = $request->only(['event_id', 'rating', 'comment']);
            $reviewData['user_id'] = $user->id;
            $reviewData['status'] = 'approved'; // Auto-approve reviews (you can change this)

            $review = Review::create($reviewData);
            $review->load(['event:id,title', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified review
     * GET /api/reviews/{id}
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $review = Review::with(['event:id,title', 'user:id,name'])->findOrFail($id);
            
            // Check if user can view this review
            if (!$user->hasRole('super_admin') && $review->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view your own reviews'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Update the specified review
     * PUT /api/reviews/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $review = Review::findOrFail($id);
            
            // Check if user can edit this review
            if (!$user->hasRole('super_admin') && $review->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own reviews'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'sometimes|required|integer|min:1|max:5',
                'comment' => 'sometimes|required|string|max:1000',
                'status' => 'sometimes|required|in:pending,approved,rejected', // Admin only
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reviewData = $request->only(['rating', 'comment']);
            
            // Only admin can change status
            if ($user->role === 'super_admin' && $request->has('status')) {
                $reviewData['status'] = $request->status;
            }

            $review->update($reviewData);
            $review->load(['event:id,title', 'user:id,name']);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified review
     * DELETE /api/reviews/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $review = Review::findOrFail($id);
            
            // Check if user can delete this review
            if (!$user->hasRole('super_admin') && $review->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own reviews'
                ], 403);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a review (Admin only)
     * PATCH /api/reviews/{id}/approve
     */
    public function approve($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can approve reviews'
                ], 403);
            }

            $review = Review::findOrFail($id);
            $review->update(['status' => 'approved']);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a review (Admin only)
     * PATCH /api/reviews/{id}/reject
     */
    public function reject($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can reject reviews'
                ], 403);
            }

            $review = Review::findOrFail($id);
            $review->update(['status' => 'rejected']);

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting review: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get review statistics for admin
     * GET /api/reviews/statistics
     */
    public function statistics()
    {
        try {
            $user = Auth::user();
            
            if (!$user->hasRole('super_admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can view review statistics'
                ], 403);
            }

            $stats = [
                'total_reviews' => Review::count(),
                'approved_reviews' => Review::where('status', 'approved')->count(),
                'pending_reviews' => Review::where('status', 'pending')->count(),
                'rejected_reviews' => Review::where('status', 'rejected')->count(),
                'average_rating' => round(Review::where('status', 'approved')->avg('rating') ?? 0, 2),
                'rating_distribution' => [
                    '5' => Review::where('status', 'approved')->where('rating', 5)->count(),
                    '4' => Review::where('status', 'approved')->where('rating', 4)->count(),
                    '3' => Review::where('status', 'approved')->where('rating', 3)->count(),
                    '2' => Review::where('status', 'approved')->where('rating', 2)->count(),
                    '1' => Review::where('status', 'approved')->where('rating', 1)->count(),
                ],
                'recent_reviews' => Review::with(['event:id,title', 'user:id,name'])
                                         ->orderBy('created_at', 'desc')
                                         ->limit(5)
                                         ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Review statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}