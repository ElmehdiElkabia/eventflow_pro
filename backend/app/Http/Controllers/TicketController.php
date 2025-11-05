<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets for a specific event
     * GET /api/events/{eventId}/tickets
     */
    public function index(Request $request, $eventId)
    {
        try {
            $user = Auth::user();
            $event = Event::findOrFail($eventId);
            
            // Check if user can view tickets for this event
            if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view tickets for your own events'
                ], 403);
            }

            $query = Ticket::where('event_id', $eventId)->with(['event:id,title']);
            
            // Apply filters
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }
            
            // Search by name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('name', 'like', "%{$search}%");
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 15);
            $tickets = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $tickets->items(),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total()
                ],
                'message' => 'Tickets retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created ticket
     * POST /api/events/{eventId}/tickets
     */
    public function store(Request $request, $eventId)
    {
        try {
            $user = Auth::user();
            $event = Event::findOrFail($eventId);
            
            // Check if user can create tickets for this event
            if ($user->role === 'organizer' && $event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only create tickets for your own events'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'type' => 'required|in:free,paid,early_bird',
                'price' => 'required|numeric|min:0|max:999999.99',
                'quantity' => 'required|integer|min:1|max:100000',
                'sale_start' => 'required|date|after_or_equal:now',
                'sale_end' => 'required|date|after:sale_start',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if sale dates are within event dates
            if ($request->sale_end > $event->end_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket sale end date cannot be after event end date'
                ], 422);
            }

            $ticketData = $request->only([
                'name', 'type', 'price', 'quantity', 'sale_start', 'sale_end'
            ]);
            
            $ticketData['event_id'] = $eventId;
            $ticketData['sold'] = 0;
            $ticketData['qr_code'] = Str::random(32); // Generate unique QR code

            $ticket = Ticket::create($ticketData);
            $ticket->load(['event:id,title']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified ticket
     * GET /api/tickets/{id}
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $ticket = Ticket::with(['event:id,title,organizer_id', 'transactions'])
                           ->findOrFail($id);
            
            // Check if user can view this ticket
            if ($user->role === 'organizer' && $ticket->event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view tickets for your own events'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found'
            ], 404);
        }
    }

    /**
     * Update the specified ticket
     * PUT /api/tickets/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $ticket = Ticket::with('event')->findOrFail($id);
            
            // Check if user can edit this ticket
            if ($user->role === 'organizer' && $ticket->event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit tickets for your own events'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'type' => 'sometimes|required|in:free,paid,early_bird',
                'price' => 'sometimes|required|numeric|min:0|max:999999.99',
                'quantity' => 'sometimes|required|integer|min:1|max:100000',
                'sale_start' => 'sometimes|required|date',
                'sale_end' => 'sometimes|required|date|after:sale_start',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if new quantity is not less than already sold tickets
            if ($request->has('quantity') && $request->quantity < $ticket->sold) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quantity cannot be less than already sold tickets (' . $ticket->sold . ')'
                ], 422);
            }

            $ticketData = $request->only([
                'name', 'type', 'price', 'quantity', 'sale_start', 'sale_end'
            ]);

            $ticket->update($ticketData);
            $ticket->load(['event:id,title']);

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified ticket
     * DELETE /api/tickets/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            $ticket = Ticket::with('event')->findOrFail($id);
            
            // Check if user can delete this ticket
            if ($user->role === 'organizer' && $ticket->event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete tickets for your own events'
                ], 403);
            }

            // Check if ticket has sales
            if ($ticket->sold > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete ticket that has already been sold'
                ], 422);
            }

            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all tickets for all events (Admin only)
     * GET /api/tickets
     */
    public function allTickets(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Only admin can view all tickets
            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only administrators can view all tickets'
                ], 403);
            }

            $query = Ticket::with(['event:id,title,organizer_id']);
            
            // Apply filters
            if ($request->has('event_id') && $request->event_id) {
                $query->where('event_id', $request->event_id);
            }
            
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }
            
            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('event', function($eq) use ($search) {
                          $eq->where('title', 'like', "%{$search}%");
                      });
                });
            }
            
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 15);
            $tickets = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $tickets->items(),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total()
                ],
                'message' => 'All tickets retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticket statistics
     * GET /api/tickets/{id}/statistics
     */
    public function statistics($id)
    {
        try {
            $user = Auth::user();
            $ticket = Ticket::with(['event', 'transactions'])->findOrFail($id);
            
            // Check permission
            if ($user->role === 'organizer' && $ticket->event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view statistics for your own event tickets'
                ], 403);
            }

            $stats = [
                'ticket_id' => $ticket->id,
                'ticket_name' => $ticket->name,
                'event_title' => $ticket->event->title,
                'total_quantity' => $ticket->quantity,
                'sold_quantity' => $ticket->sold,
                'remaining_quantity' => $ticket->quantity - $ticket->sold,
                'sold_percentage' => $ticket->quantity > 0 ? 
                    round(($ticket->sold / $ticket->quantity) * 100, 2) : 0,
                'total_revenue' => $ticket->sold * $ticket->price,
                'sale_status' => $this->getSaleStatus($ticket),
                'transactions_count' => $ticket->transactions->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Ticket statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a ticket
     * POST /api/tickets/{id}/duplicate
     */
    public function duplicate($id)
    {
        try {
            $user = Auth::user();
            $originalTicket = Ticket::with('event')->findOrFail($id);
            
            // Check permission
            if ($user->role === 'organizer' && $originalTicket->event->organizer_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only duplicate tickets for your own events'
                ], 403);
            }

            $newTicketData = $originalTicket->toArray();
            unset($newTicketData['id'], $newTicketData['created_at'], $newTicketData['updated_at']);
            
            $newTicketData['name'] = 'Copy of ' . $originalTicket->name;
            $newTicketData['sold'] = 0;
            $newTicketData['qr_code'] = Str::random(32);

            $newTicket = Ticket::create($newTicketData);
            $newTicket->load(['event:id,title']);

            return response()->json([
                'success' => true,
                'data' => $newTicket,
                'message' => 'Ticket duplicated successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error duplicating ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to determine sale status
     */
    private function getSaleStatus($ticket)
    {
        $now = now();
        
        if ($now < $ticket->sale_start) {
            return 'not_started';
        } elseif ($now > $ticket->sale_end) {
            return 'ended';
        } elseif ($ticket->sold >= $ticket->quantity) {
            return 'sold_out';
        } else {
            return 'active';
        }
    }
}