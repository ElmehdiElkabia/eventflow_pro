<?php

namespace App\Http\Controllers;

use App\Models\Analytics;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\Review;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
	/**
	 * Get analytics for a specific event
	 * GET /api/events/{eventId}/analytics
	 */
	public function getEventAnalytics(Request $request, $eventId)
	{
		try {
			$user = Auth::user();
			$event = Event::findOrFail($eventId);

			// Check if user can view analytics for this event
			if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
				return response()->json([
					'success' => false,
					'message' => 'You can only view analytics for your own events'
				], 403);
			}

			// Get or create analytics record
			$analytics = Analytics::firstOrCreate(
				['event_id' => $eventId],
				['views' => 0, 'ticket_sales' => 0, 'revenue' => 0]
			);

			// Calculate real-time data
			$ticketStats = $this->calculateTicketStats($eventId);
			$engagementStats = $this->calculateEngagementStats($eventId);
			$revenueStats = $this->calculateRevenueStats($eventId);

			// Update analytics record
			$analytics->update([
				'ticket_sales' => $ticketStats['total_sold'],
				'revenue' => $revenueStats['total_revenue'],
				'updated_at' => now()
			]);

			$analyticsData = [
				'event_id' => $eventId,
				'event_title' => $event->title,
				'views' => $analytics->views,
				'ticket_sales' => $ticketStats['total_sold'],
				'revenue' => $revenueStats['total_revenue'],
				'last_updated' => $analytics->updated_at,

				// Ticket Analytics
				'ticket_analytics' => [
					'total_tickets_available' => $ticketStats['total_available'],
					'total_tickets_sold' => $ticketStats['total_sold'],
					'tickets_remaining' => $ticketStats['remaining'],
					'conversion_rate' => $ticketStats['conversion_rate'],
					'by_ticket_type' => $ticketStats['by_type']
				],

				// Revenue Analytics
				'revenue_analytics' => [
					'total_revenue' => $revenueStats['total_revenue'],
					'average_ticket_price' => $revenueStats['avg_price'],
					'revenue_by_ticket_type' => $revenueStats['by_type'],
					'daily_revenue' => $revenueStats['daily_revenue'] ?? []
				],

				// Engagement Analytics
				'engagement_analytics' => [
					'total_reviews' => $engagementStats['reviews_count'],
					'average_rating' => $engagementStats['avg_rating'],
					'total_comments' => $engagementStats['comments_count'],
					'engagement_score' => $engagementStats['engagement_score']
				],

				// Time-based Analytics
				'timeline_analytics' => $this->getTimelineAnalytics($eventId)
			];

			return response()->json([
				'success' => true,
				'data' => $analyticsData,
				'message' => 'Analytics retrieved successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error retrieving analytics: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Get dashboard analytics (Admin/Organizer)
	 * GET /api/analytics/dashboard
	 */
	public function getDashboardAnalytics(Request $request)
	{
		try {
			$user = Auth::user();
			$timeframe = $request->get('timeframe', '30'); // days
			$startDate = Carbon::now()->subDays($timeframe);

			if ($user->hasRole('super_admin')) {
				// Admin sees all analytics
				$dashboardData = $this->getAdminDashboardData($startDate);
			} elseif ($user->role === 'organizer') {
				// Organizer sees only their events analytics
				$dashboardData = $this->getOrganizerDashboardData($user->id, $startDate);
			} else {
				return response()->json([
					'success' => false,
					'message' => 'Unauthorized to view dashboard analytics'
				], 403);
			}

			return response()->json([
				'success' => true,
				'data' => $dashboardData,
				'message' => 'Dashboard analytics retrieved successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error retrieving dashboard analytics: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Increment event views
	 * POST /api/events/{eventId}/analytics/view
	 */
	public function incrementViews($eventId)
	{
		try {
			Event::findOrFail($eventId); // Verify event exists

			$analytics = Analytics::firstOrCreate(
				['event_id' => $eventId],
				['views' => 0, 'ticket_sales' => 0, 'revenue' => 0]
			);

			$analytics->increment('views');
			$analytics->touch('updated_at');

			return response()->json([
				'success' => true,
				'data' => ['views' => $analytics->views],
				'message' => 'View count updated'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error updating view count: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Get analytics comparison between events
	 * GET /api/analytics/compare
	 */
	public function compareEvents(Request $request)
	{
		try {
			$user = Auth::user();
			$eventIds = $request->get('event_ids', []);

			if (empty($eventIds) || !is_array($eventIds)) {
				return response()->json([
					'success' => false,
					'message' => 'Please provide event IDs to compare'
				], 422);
			}

			$query = Event::whereIn('id', $eventIds);

			// Organizers can only compare their own events
			if ($user->role === 'organizer') {
				$query->where('organizer_id', $user->id);
			}

			$events = $query->get();

			if ($events->count() !== count($eventIds)) {
				return response()->json([
					'success' => false,
					'message' => 'Some events not found or access denied'
				], 403);
			}

			$comparison = [];
			foreach ($events as $event) {
				$analytics = Analytics::where('event_id', $event->id)->first();
				$ticketStats = $this->calculateTicketStats($event->id);
				$revenueStats = $this->calculateRevenueStats($event->id);
				$engagementStats = $this->calculateEngagementStats($event->id);

				$comparison[] = [
					'event_id' => $event->id,
					'event_title' => $event->title,
					'event_date' => $event->start_date,
					'views' => $analytics->views ?? 0,
					'tickets_sold' => $ticketStats['total_sold'],
					'revenue' => $revenueStats['total_revenue'],
					'reviews_count' => $engagementStats['reviews_count'],
					'average_rating' => $engagementStats['avg_rating'],
					'comments_count' => $engagementStats['comments_count'],
					'conversion_rate' => $ticketStats['conversion_rate']
				];
			}

			return response()->json([
				'success' => true,
				'data' => $comparison,
				'message' => 'Events comparison retrieved successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error comparing events: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Get top performing events
	 * GET /api/analytics/top-events
	 */
	public function getTopEvents(Request $request)
	{
		try {
			$user = Auth::user();
			$metric = $request->get('metric', 'revenue'); // revenue, tickets, views, rating
			$limit = $request->get('limit', 10);

			$query = Analytics::with('event:id,title,start_date,organizer_id');

			// Organizers can only see their events
			if ($user->role === 'organizer') {
				$query->whereHas('event', function ($q) use ($user) {
					$q->where('organizer_id', $user->id);
				});
			}

			switch ($metric) {
				case 'revenue':
					$query->orderBy('revenue', 'desc');
					break;
				case 'tickets':
					$query->orderBy('ticket_sales', 'desc');
					break;
				case 'views':
					$query->orderBy('views', 'desc');
					break;
				default:
					$query->orderBy('revenue', 'desc');
			}

			$topEvents = $query->limit($limit)->get();

			// Add additional metrics for rating-based sorting
			if ($metric === 'rating') {
				$eventIds = $topEvents->pluck('event_id');
				$ratingData = Review::whereIn('event_id', $eventIds)
					->where('status', 'approved')
					->selectRaw('event_id, AVG(rating) as avg_rating, COUNT(*) as review_count')
					->groupBy('event_id')
					->get()
					->keyBy('event_id');

				$topEvents = $topEvents->map(function ($analytics) use ($ratingData) {
					$rating = $ratingData->get($analytics->event_id);
					$analytics->avg_rating = $rating ? round($rating->avg_rating, 2) : 0;
					$analytics->review_count = $rating ? $rating->review_count : 0;
					return $analytics;
				})->sortByDesc('avg_rating')->take($limit);
			}

			return response()->json([
				'success' => true,
				'data' => $topEvents->values(),
				'message' => "Top events by {$metric} retrieved successfully"
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Error retrieving top events: ' . $e->getMessage()
			], 500);
		}
	}

	/**
	 * Calculate ticket statistics
	 */
	private function calculateTicketStats($eventId)
	{
		$tickets = Ticket::where('event_id', $eventId)->get();

		$totalAvailable = $tickets->sum('quantity');
		$totalSold = $tickets->sum('sold');
		$remaining = $totalAvailable - $totalSold;
		$conversionRate = $totalAvailable > 0 ? round(($totalSold / $totalAvailable) * 100, 2) : 0;

		$byType = $tickets->groupBy('type')->map(function ($typeTickets) {
			return [
				'available' => $typeTickets->sum('quantity'),
				'sold' => $typeTickets->sum('sold'),
				'revenue' => $typeTickets->sum(function ($ticket) {
					return $ticket->sold * $ticket->price;
				})
			];
		});

		return [
			'total_available' => $totalAvailable,
			'total_sold' => $totalSold,
			'remaining' => $remaining,
			'conversion_rate' => $conversionRate,
			'by_type' => $byType
		];
	}

	/**
	 * Calculate revenue statistics
	 */
	private function calculateRevenueStats($eventId)
	{
		$tickets = Ticket::where('event_id', $eventId)->get();

		$totalRevenue = $tickets->sum(function ($ticket) {
			return $ticket->sold * $ticket->price;
		});

		$totalSold = $tickets->sum('sold');
		$avgPrice = $totalSold > 0 ? round($totalRevenue / $totalSold, 2) : 0;

		$byType = $tickets->groupBy('type')->map(function ($typeTickets) {
			return $typeTickets->sum(function ($ticket) {
				return $ticket->sold * $ticket->price;
			});
		});

		// Daily revenue (last 30 days) - Updated status
		$dailyRevenue = Transaction::where('event_id', $eventId)
			->where('status', 'completed') // Changed from 'success' to 'completed'
			->where('created_at', '>=', Carbon::now()->subDays(30))
			->selectRaw('DATE(created_at) as date, SUM(amount) as revenue')
			->groupBy('date')
			->orderBy('date')
			->get();

		return [
			'total_revenue' => $totalRevenue,
			'avg_price' => $avgPrice,
			'by_type' => $byType,
			'daily_revenue' => $dailyRevenue
		];
	}

	/**
	 * Calculate engagement statistics
	 */
	private function calculateEngagementStats($eventId)
	{
		$reviewsCount = Review::where('event_id', $eventId)->where('status', 'approved')->count();
		$avgRating = Review::where('event_id', $eventId)->where('status', 'approved')->avg('rating') ?? 0;
		$commentsCount = Comment::where('event_id', $eventId)->where('status', 'approved')->count();

		// Simple engagement score calculation
		$engagementScore = ($reviewsCount * 2) + ($commentsCount * 1) + ($avgRating * 10);

		return [
			'reviews_count' => $reviewsCount,
			'avg_rating' => round($avgRating, 2),
			'comments_count' => $commentsCount,
			'engagement_score' => round($engagementScore, 2)
		];
	}

	/**
	 * Get timeline analytics
	 */
	private function getTimelineAnalytics($eventId)
	{
		return [
			'daily_views' => Analytics::where('event_id', $eventId)
				->selectRaw('DATE(updated_at) as date, views')
				->where('updated_at', '>=', Carbon::now()->subDays(30))
				->get(),

			'ticket_sales_trend' => Transaction::where('event_id', $eventId)
				->where('status', 'completed')
				->selectRaw('DATE(created_at) as date, COUNT(*) as sales')
				->where('created_at', '>=', Carbon::now()->subDays(30))
				->groupBy('date')
				->orderBy('date')
				->get()
		];
	}

	/**
	 * Get admin dashboard data
	 */
	private function getAdminDashboardData($startDate)
	{
		return [
			'overview' => [
				'total_events' => Event::count(),
				'total_revenue' => Analytics::sum('revenue'),
				'total_ticket_sales' => Analytics::sum('ticket_sales'),
				'total_views' => Analytics::sum('views'),
			],
			'recent_performance' => [
				'new_events' => Event::where('created_at', '>=', $startDate)->count(),
				'recent_revenue' => Transaction::where('created_at', '>=', $startDate)
					->where('status', 'completed')->sum('amount'),
				'recent_sales' => Transaction::where('created_at', '>=', $startDate)
					->where('status', 'completed')->count(),
			],
			'top_organizers' => DB::table('events')
				->join('analytics', 'events.id', '=', 'analytics.event_id')
				->join('users', 'events.organizer_id', '=', 'users.id')
				->select('users.name', DB::raw('SUM(analytics.revenue) as total_revenue'))
				->groupBy('users.id', 'users.name')
				->orderBy('total_revenue', 'desc')
				->limit(5)
				->get(),
		];
	}

	/**
	 * Get organizer dashboard data
	 */
	private function getOrganizerDashboardData($organizerId, $startDate)
	{
		$eventIds = Event::where('organizer_id', $organizerId)->pluck('id');

		return [
			'overview' => [
				'total_events' => Event::where('organizer_id', $organizerId)->count(),
				'total_revenue' => Analytics::whereIn('event_id', $eventIds)->sum('revenue'),
				'total_ticket_sales' => Analytics::whereIn('event_id', $eventIds)->sum('ticket_sales'),
				'total_views' => Analytics::whereIn('event_id', $eventIds)->sum('views'),
			],
			'recent_performance' => [
				'new_events' => Event::where('organizer_id', $organizerId)
					->where('created_at', '>=', $startDate)->count(),
				'recent_revenue' => Transaction::whereIn('event_id', $eventIds)
					->where('created_at', '>=', $startDate)
					->where('status', 'completed')->sum('amount'),
				'recent_sales' => Transaction::whereIn('event_id', $eventIds)
					->where('created_at', '>=', $startDate)
					->where('status', 'completed')->count(),
			],
			'event_performance' => Analytics::whereIn('event_id', $eventIds)
				->with('event:id,title')
				->orderBy('revenue', 'desc')
				->limit(5)
				->get(),
		];
	}
}
