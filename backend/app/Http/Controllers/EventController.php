<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
	/**
	 * Display a listing of events
	 * GET /api/events
	 */
	public function index(Request $request)
	{
		$user = Auth::user();
		$query = Event::with(['organizer:id,name,email']);

		// If user is organizer (not super_admin), only show their events
		if ($user->role === 'organizer') {
			$query->where('organizer_id', $user->id);
		}

		// Apply filters
		if ($request->has('category') && $request->category) {
			$query->where('category', $request->category);
		}

		if ($request->has('status') && $request->status) {
			$query->where('status', $request->status);
		}

		// Search functionality
		if ($request->has('search') && $request->search) {
			$search = $request->search;
			$query->where(function ($q) use ($search) {
				$q->where('title', 'like', "%{$search}%")
					->orWhere('description', 'like', "%{$search}%")
					->orWhere('location', 'like', "%{$search}%");
			});
		}

		// Sorting
		$sortBy = $request->get('sort_by', 'created_at');
		$sortOrder = $request->get('sort_order', 'desc');
		$query->orderBy($sortBy, $sortOrder);

		$perPage = $request->get('per_page', 15);
		$events = $query->paginate($perPage);

		return response()->json([
			'success' => true,
			'data' => $events->items(),
			'pagination' => [
				'current_page' => $events->currentPage(),
				'last_page' => $events->lastPage(),
				'per_page' => $events->perPage(),
				'total' => $events->total()
			],
			'message' => 'Events retrieved successfully'
		]);
	}

	/**
	 * Store a newly created event
	 * POST /api/events
	 */
	public function store(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'title' => 'required|string|max:200',
			'description' => 'required|string|max:5000',
			'category' => 'required|string|max:100',
			'location' => 'required|string|max:255',
			'start_date' => 'required|date|after:now',
			'end_date' => 'required|date|after:start_date',
			'capacity' => 'required|integer|min:1|max:100000',
			'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
			'images' => 'nullable|array|max:5', // Max 5 additional images
			'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
		]);

		if ($validator->fails()) {
			return response()->json([
				'success' => false,
				'message' => 'Validation error',
				'errors' => $validator->errors()
			], 422);
		}

		try {
			$eventData = $request->only([
				'title',
				'description',
				'category',
				'location',
				'start_date',
				'end_date',
				'capacity'
			]);

			$eventData['organizer_id'] = Auth::id();
			$eventData['status'] = 'draft';

			// Handle banner upload
			if ($request->hasFile('banner')) {
				$bannerPath = $request->file('banner')->store('events/banners', 'public');
				$eventData['banner'] = $bannerPath;
			}

			// Handle additional images upload
			if ($request->hasFile('images')) {
				$imagePaths = [];
				foreach ($request->file('images') as $image) {
					$imagePath = $image->store('events/gallery', 'public');
					$imagePaths[] = $imagePath;
				}
				$eventData['images'] = $imagePaths;
			}

			$event = Event::create($eventData);
			$event->load(['organizer:id,name,email']);

			return response()->json([
				'success' => true,
				'data' => $event,
				'message' => 'Event created successfully'
			], 201);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error creating event: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Display the specified event
	 * GET /api/events/{id}
	 */
	public function show($id)
	{
		try {
			$user = Auth::user();
			$event = Event::with(['organizer:id,name,email', 'tickets', 'reviews.user:id,name'])
				->findOrFail($id);

			// Check if user can view this event
			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only view your own events'
				], 403);
			}

			return response()->json([
				'success' => true,
				'data' => $event,
				'message' => 'Event retrieved successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Event not found'
			], 404);
		}
	}

	/**
	 * Update the specified event
	 * PUT /api/events/{id}
	 */
	public function update(Request $request, $id)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($id);

			// Check if user can edit this event
			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only edit your own events'
				], 403);
			}

			$validator = Validator::make($request->all(), [
				'title' => 'sometimes|required|string|max:200',
				'description' => 'sometimes|required|string|max:5000',
				'category' => 'sometimes|required|string|max:100',
				'location' => 'sometimes|required|string|max:255',
				'start_date' => 'sometimes|required|date',
				'end_date' => 'sometimes|required|date|after:start_date',
				'capacity' => 'sometimes|required|integer|min:1|max:100000',
				'status' => 'sometimes|required|in:draft,published,cancelled,completed',
				'banner' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
				'images' => 'sometimes|nullable|array|max:5',
				'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation error',
					'errors' => $validator->errors()
				], 422);
			}

			$eventData = $request->only([
				'title',
				'description',
				'category',
				'location',
				'start_date',
				'end_date',
				'capacity',
				'status'
			]);

			// Handle banner upload
			if ($request->hasFile('banner')) {
				// Delete old banner if exists
				if ($event->banner) {
					Storage::disk('public')->delete($event->banner);
				}

				$bannerPath = $request->file('banner')->store('events/banners', 'public');
				$eventData['banner'] = $bannerPath;
			}

			// Handle additional images upload
			if ($request->hasFile('images')) {
				// Delete old images if exists
				if ($event->images) {
					foreach ($event->images as $oldImage) {
						Storage::disk('public')->delete($oldImage);
					}
				}

				$imagePaths = [];
				foreach ($request->file('images') as $image) {
					$imagePath = $image->store('events/gallery', 'public');
					$imagePaths[] = $imagePath;
				}
				$eventData['images'] = $imagePaths;
			}

			$event->update($eventData);
			$event->load(['organizer:id,name,email']);

			return response()->json([
				'success' => true,
				'data' => $event,
				'message' => 'Event updated successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error updating event: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Remove the specified event
	 * DELETE /api/events/{id}
	 */
	public function destroy($id)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($id);

			// Check if user can delete this event
			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only delete your own events'
				], 403);
			}

			// Delete all images
			$event->clearAllImages();

			$event->delete();

			return response()->json([
				'success' => true,
				'message' => 'Event deleted successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error deleting event: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Add image to event
	 * POST /api/events/{id}/images
	 */
	public function addImage(Request $request, $id)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($id);

			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only add images to your own events'
				], 403);
			}

			$validator = Validator::make($request->all(), [
				'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation error',
					'errors' => $validator->errors()
				], 422);
			}

			$imagePath = $request->file('image')->store('events/gallery', 'public');
			$event->addImage($imagePath);

			return response()->json([
				'success' => true,
				'data' => [
					'image_path' => $imagePath,
					'image_url' => asset('storage/' . $imagePath)
				],
				'message' => 'Image added successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error adding image: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Remove image from event
	 * DELETE /api/events/{id}/images
	 */
	public function removeImage(Request $request, $id)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($id);

			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only remove images from your own events'
				], 403);
			}

			$validator = Validator::make($request->all(), [
				'image_path' => 'required|string',
			]);

			if ($validator->fails()) {
				return response()->json([
					'success' => false,
					'message' => 'Validation error',
					'errors' => $validator->errors()
				], 422);
			}

			$event->removeImage($request->image_path);

			return response()->json([
				'success' => true,
				'message' => 'Image removed successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error removing image: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Publish an event
	 * PATCH /api/events/{id}/publish
	 */
	public function publish($id)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($id);

			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only publish your own events'
				], 403);
			}

			if ($event->status === 'published') {
				return response()->json([
					'success' => false,
					'message' => 'Event is already published'
				], 400);
			}

			$event->update(['status' => 'published']);

			return response()->json([
				'success' => true,
				'data' => $event,
				'message' => 'Event published successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error publishing event: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Cancel an event
	 * PATCH /api/events/{id}/cancel
	 */
	public function cancel($id)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($id);

			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only cancel your own events'
				], 403);
			}

			if ($event->status === 'cancelled') {
				return response()->json([
					'success' => false,
					'message' => 'Event is already cancelled'
				], 400);
			}

			$event->update(['status' => 'cancelled']);

			return response()->json([
				'success' => true,
				'data' => $event,
				'message' => 'Event cancelled successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error cancelling event: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Get event statistics
	 * GET /api/events/{id}/statistics
	 */
	public function statistics($id)
	{
		try {
			$user = Auth::user();
			$event = Event::with(['tickets', 'reviews'])->findOrFail($id);

			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only view statistics for your own events'
				], 403);
			}

			$stats = [
				'event_id' => $event->id,
				'event_title' => $event->title,
				'total_tickets' => $event->tickets->count(),
				'sold_tickets' => $event->tickets->where('status', 'purchased')->count(),
				'pending_tickets' => $event->tickets->where('status', 'pending')->count(),
				'cancelled_tickets' => $event->tickets->where('status', 'cancelled')->count(),
				'total_reviews' => $event->reviews->count(),
				'average_rating' => round($event->reviews->avg('rating') ?? 0, 2),
				'capacity' => $event->capacity,
				'capacity_used' => $event->tickets->where('status', 'purchased')->count(),
				'capacity_percentage' => $event->capacity > 0 ?
					round(($event->tickets->where('status', 'purchased')->count() / $event->capacity) * 100, 2) : 0,
				'revenue' => $event->tickets->where('status', 'purchased')->sum('price') ?? 0
			];

			return response()->json([
				'success' => true,
				'data' => $stats,
				'message' => 'Event statistics retrieved successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error retrieving statistics: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Duplicate an event
	 * POST /api/events/{id}/duplicate
	 */
	public function duplicate($id)
	{
		try {
			$user = Auth::user();
			$originalEvent = Event::findOrFail($id);

			if ($user->role === 'organizer' && $originalEvent->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only duplicate your own events'
				], 403);
			}

			$newEventData = $originalEvent->toArray();
			unset($newEventData['id'], $newEventData['created_at'], $newEventData['updated_at']);

			$newEventData['title'] = 'Copy of ' . $originalEvent->title;
			$newEventData['status'] = 'draft';
			$newEventData['organizer_id'] = $user->id;

			// Copy banner if exists
			if ($originalEvent->banner) {
				$extension = pathinfo($originalEvent->banner, PATHINFO_EXTENSION);
				$newBannerPath = 'events/banners/' . uniqid() . '.' . $extension;
				Storage::disk('public')->copy($originalEvent->banner, $newBannerPath);
				$newEventData['banner'] = $newBannerPath;
			}

			$newEvent = Event::create($newEventData);
			$newEvent->load(['organizer:id,name,email']);

			return response()->json([
				'success' => true,
				'data' => $newEvent,
				'message' => 'Event duplicated successfully'
			], 201);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error duplicating event: ' . $e->getMessage()
			], 500);
		}
	}
}
