<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    /**
     * Display comments for a specific event (Public)
     * GET /api/events/{eventId}/comments
     */
    public function getEventComments(Request $request, $eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            
            $query = Comment::where('event_id', $eventId)
                           ->topLevel() // Only top-level comments
                           ->approved()
                           ->with(['user:id,name,avatar', 'approvedReplies'])
                           ->orderBy('created_at', 'desc');
            
            $perPage = $request->get('per_page', 10);
            $comments = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $comments->items(),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total()
                ],
                'message' => 'Comments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving comments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display all comments (Admin only or user's own comments)
     * GET /api/comments
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Comment::with(['event:id,title', 'user:id,name,avatar', 'parent:id,content']);
            
            // If user is not admin, only show their own comments
            if ($user->role !== 'super_admin') {
                $query->where('user_id', $user->id);
            }
            
            // Apply filters
            if ($request->has('event_id') && $request->event_id) {
                $query->where('event_id', $request->event_id);
            }
            
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('is_reply')) {
                if ($request->is_reply === 'true') {
                    $query->whereNotNull('parent_id');
                } else {
                    $query->whereNull('parent_id');
                }
            }
            
            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('content', 'like', "%{$search}%");
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 15);
            $comments = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $comments->items(),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total()
                ],
                'message' => 'Comments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving comments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created comment
     * POST /api/comments
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'event_id' => 'required|exists:events,id',
                'parent_id' => 'nullable|exists:comments,id',
                'content' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // If parent_id is provided, validate it belongs to the same event
            if ($request->parent_id) {
                $parentComment = Comment::findOrFail($request->parent_id);
                if ($parentComment->event_id != $request->event_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent comment must belong to the same event'
                    ], 422);
                }
                
                // Prevent nested replies (only allow 2 levels)
                if ($parentComment->parent_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot reply to a reply. Please reply to the main comment.'
                    ], 422);
                }
            }

            $commentData = $request->only(['event_id', 'parent_id', 'content']);
            $commentData['user_id'] = $user->id;
            $commentData['status'] = 'approved'; // Auto-approve comments

            $comment = Comment::create($commentData);
            $comment->load(['event:id,title', 'user:id,name,avatar', 'parent:id,content']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified comment
     * GET /api/comments/{id}
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $comment = Comment::with([
                'event:id,title', 
                'user:id,name,avatar', 
                'parent:id,content',
                'approvedReplies'
            ])->findOrFail($id);
            
            // Check if user can view this comment
            if ($user->role !== 'super_admin' && $comment->user_id !== $user->id && $comment->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found or access denied'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comment not found'
            ], 404);
        }
    }

    /**
     * Update the specified comment
     * PUT /api/comments/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $comment = Comment::findOrFail($id);
            
            // Check if user can edit this comment
            if ($user->role !== 'super_admin' && $comment->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own comments'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content' => 'sometimes|required|string|max:1000',
                'status' => 'sometimes|required|in:pending,approved,rejected', // Admin only
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $commentData = [];
            
            // Users can only edit content
            if ($request->has('content')) {
                $commentData['content'] = $request->content;
            }
            
            // Only admin can change status
            if ($user->role === 'super_admin' && $request->has('status')) {
                $commentData['status'] = $request->status;
            }

            $comment->update($commentData);
            $comment->load(['event:id,title', 'user:id,name,avatar', 'parent:id,content']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified comment
     * DELETE /api/comments/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $comment = Comment::findOrFail($id);
            
            // Check if user can delete this comment
            if ($user->role !== 'super_admin' && $comment->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own comments'
                ], 403);
            }

            // If comment has replies, inform user
            if ($comment->replies()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete comment that has replies. Delete replies first or contact admin.'
                ], 422);
            }

            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get replies for a specific comment
     * GET /api/comments/{id}/replies
     */
    public function getReplies(Request $request, $id)
    {
        try {
            $comment = Comment::findOrFail($id);
            
            $query = $comment->replies()
                           ->where('status', 'approved')
                           ->orderBy('created_at', 'asc');
            
            $perPage = $request->get('per_page', 5);
            $replies = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $replies->items(),
                'pagination' => [
                    'current_page' => $replies->currentPage(),
                    'last_page' => $replies->lastPage(),
                    'per_page' => $replies->perPage(),
                    'total' => $replies->total()
                ],
                'message' => 'Replies retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving replies: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a comment (Admin only)
     * PATCH /api/comments/{id}/approve
     */
    public function approve($id)
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can approve comments'
                ], 403);
            }

            $comment = Comment::findOrFail($id);
            $comment->update(['status' => 'approved']);
            $comment->load(['event:id,title', 'user:id,name,avatar']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a comment (Admin only)
     * PATCH /api/comments/{id}/reject
     */
    public function reject($id)
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can reject comments'
                ], 403);
            }

            $comment = Comment::findOrFail($id);
            $comment->update(['status' => 'rejected']);
            $comment->load(['event:id,title', 'user:id,name,avatar']);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Comment rejected successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comment statistics (Admin only)
     * GET /api/comments/statistics
     */
    public function statistics()
    {
        try {
            $user = Auth::user();
            
            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can view comment statistics'
                ], 403);
            }

            $stats = [
                'total_comments' => Comment::count(),
                'approved_comments' => Comment::where('status', 'approved')->count(),
                'pending_comments' => Comment::where('status', 'pending')->count(),
                'rejected_comments' => Comment::where('status', 'rejected')->count(),
                'top_level_comments' => Comment::whereNull('parent_id')->count(),
                'reply_comments' => Comment::whereNotNull('parent_id')->count(),
                'recent_comments' => Comment::with(['event:id,title', 'user:id,name'])
                                          ->orderBy('created_at', 'desc')
                                          ->limit(5)
                                          ->get(),
                'comments_by_event' => Comment::selectRaw('event_id, COUNT(*) as count')
                                             ->groupBy('event_id')
                                             ->with('event:id,title')
                                             ->orderBy('count', 'desc')
                                             ->limit(5)
                                             ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Comment statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}