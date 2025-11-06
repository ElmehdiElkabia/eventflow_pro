<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    /**
     * Display user's transactions
     * GET /api/transactions
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Transaction::with(['ticket:id,name,price,type', 'event:id,title,start_date']);
            
            // Users see only their transactions, admins see all
            if ($user->role !== 'super_admin') {
                $query->where('user_id', $user->id);
            }
            
            // Apply filters
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('event_id') && $request->event_id) {
                $query->where('event_id', $request->event_id);
            }
            
            if ($request->has('payment_gateway') && $request->payment_gateway) {
                $query->where('payment_gateway', $request->payment_gateway);
            }
            
            // Date range filter
            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $perPage = $request->get('per_page', 15);
            $transactions = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total()
                ],
                'message' => 'Transactions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Purchase tickets (Create transaction)
     * POST /api/transactions/purchase
     */
    public function purchase(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'ticket_id' => 'required|exists:tickets,id',
                'quantity' => 'required|integer|min:1|max:10',
                'payment_gateway' => 'required|in:stripe,paypal,bank_transfer',
                'payment_data' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticket = Ticket::with('event')->findOrFail($request->ticket_id);
            $quantity = $request->quantity;

            // Business validations
            if ($ticket->event->status !== 'published') {
                return response()->json([
                    'success' => false,
                    'message' => 'Event is not available for ticket purchase'
                ], 422);
            }

            if ($ticket->event->start_date <= now()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot purchase tickets for past events'
                ], 422);
            }

            if (now() < $ticket->sale_start || now() > $ticket->sale_end) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket sales are not active for this ticket'
                ], 422);
            }

            if (($ticket->sold + $quantity) > $ticket->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough tickets available. Only ' . ($ticket->quantity - $ticket->sold) . ' tickets remaining.'
                ], 422);
            }

            // Calculate total amount
            $totalAmount = $ticket->price * $quantity;

            // Start database transaction
            DB::beginTransaction();

            try {
                // Create transaction record
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'ticket_id' => $ticket->id,
                    'event_id' => $ticket->event_id,
                    'quantity' => $quantity,
                    'amount' => $totalAmount,
                    'status' => 'pending',
                    'payment_gateway' => $request->payment_gateway,
                    'transaction_ref' => 'TXN_' . strtoupper(Str::random(12)),
                    'payment_data' => $request->payment_data ?? null,
                ]);

                // Update ticket sold count
                $ticket->increment('sold', $quantity);

                // Process payment based on gateway
                $paymentResult = $this->processPayment($transaction, $request);

                if ($paymentResult['success']) {
                    $transaction->update([
                        'status' => 'completed',
                        'transaction_ref' => $paymentResult['transaction_ref'] ?? $transaction->transaction_ref
                    ]);

                    DB::commit();

                    $transaction->load(['ticket:id,name,price,type', 'event:id,title,start_date']);

                    return response()->json([
                        'success' => true,
                        'data' => $transaction,
                        'message' => 'Ticket purchase completed successfully'
                    ], 201);
                } else {
                    $transaction->update(['status' => 'failed']);
                    $ticket->decrement('sold', $quantity); // Rollback ticket count
                    
                    DB::commit();

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment failed: ' . $paymentResult['message']
                    ], 422);
                }

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific transaction
     * GET /api/transactions/{id}
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $transaction = Transaction::with([
                'ticket:id,name,price,type',
                'event:id,title,start_date,end_date',
                'user:id,name,email'
            ])->findOrFail($id);
            
            // Check access permissions
            if ($user->role !== 'super_admin' && $transaction->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view your own transactions'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => 'Transaction retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    /**
     * Request refund
     * POST /api/transactions/{id}/refund
     */
    public function requestRefund(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $transaction = Transaction::with(['ticket', 'event'])->findOrFail($id);
            
            // Check access permissions
            if ($user->role !== 'super_admin' && $transaction->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only refund your own transactions'
                ], 403);
            }

            // Validate refund eligibility
            if (!$transaction->canBeRefunded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This transaction cannot be refunded'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Process refund
                $refundResult = $this->processRefund($transaction, $request->reason);

                if ($refundResult['success']) {
                    $transaction->update([
                        'status' => 'refunded',
                        'refunded_at' => now(),
                        'payment_data' => array_merge(
                            $transaction->payment_data ?? [],
                            ['refund_reason' => $request->reason, 'refund_date' => now()]
                        )
                    ]);

                    // Return tickets to available pool
                    $transaction->ticket->decrement('sold', $transaction->quantity);

                    DB::commit();

                    return response()->json([
                        'success' => true,
                        'data' => $transaction,
                        'message' => 'Refund processed successfully'
                    ]);
                } else {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'Refund failed: ' . $refundResult['message']
                    ], 422);
                }

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing refund: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction statistics (Admin/Organizer)
     * GET /api/transactions/statistics
     */
    public function statistics(Request $request)
    {
        try {
            $user = Auth::user();
            
            if ($user->role === 'user') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view transaction statistics'
                ], 403);
            }

            $query = Transaction::query();

            // Organizers see only their events' transactions
            if ($user->role === 'organizer') {
                $eventIds = Event::where('organizer_id', $user->id)->pluck('id');
                $query->whereIn('event_id', $eventIds);
            }

            $timeframe = $request->get('timeframe', 30); // days
            $startDate = now()->subDays($timeframe);

            $stats = [
                'overview' => [
                    'total_transactions' => $query->count(),
                    'completed_transactions' => $query->where('status', 'completed')->count(),
                    'pending_transactions' => $query->where('status', 'pending')->count(),
                    'failed_transactions' => $query->where('status', 'failed')->count(),
                    'refunded_transactions' => $query->where('status', 'refunded')->count(),
                    'total_revenue' => $query->where('status', 'completed')->sum('amount'),
                ],
                'recent_performance' => [
                    'recent_transactions' => $query->where('created_at', '>=', $startDate)->count(),
                    'recent_revenue' => $query->where('created_at', '>=', $startDate)
                        ->where('status', 'completed')->sum('amount'),
                    'success_rate' => $this->calculateSuccessRate($query, $startDate),
                ],
                'payment_gateways' => $query->where('status', 'completed')
                    ->selectRaw('payment_gateway, COUNT(*) as count, SUM(amount) as revenue')
                    ->groupBy('payment_gateway')
                    ->get(),
                'daily_revenue' => $query->where('status', 'completed')
                    ->where('created_at', '>=', $startDate)
                    ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Transaction statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's purchase history with tickets
     * GET /api/my-tickets
     */
    public function myTickets(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Transaction::where('user_id', $user->id)
                ->where('status', 'completed')
                ->with(['ticket:id,name,price,type', 'event:id,title,start_date,end_date,location']);
            
            // Filter by upcoming/past events
            if ($request->has('filter') && $request->filter === 'upcoming') {
                $query->whereHas('event', function ($q) {
                    $q->where('start_date', '>', now());
                });
            } elseif ($request->has('filter') && $request->filter === 'past') {
                $query->whereHas('event', function ($q) {
                    $q->where('end_date', '<', now());
                });
            }
            
            $tickets = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $tickets,
                'message' => 'Your tickets retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process payment based on gateway
     */
    private function processPayment($transaction, $request)
    {
        // Mock payment processing - replace with actual payment gateway integration
        switch ($request->payment_gateway) {
            case 'stripe':
                return $this->processStripePayment($transaction, $request);
            case 'paypal':
                return $this->processPaypalPayment($transaction, $request);
            case 'bank_transfer':
                return $this->processBankTransfer($transaction, $request);
            default:
                return ['success' => false, 'message' => 'Unsupported payment gateway'];
        }
    }

    private function processStripePayment($transaction, $request)
    {
        // Mock Stripe payment - replace with actual Stripe integration
        return [
            'success' => true,
            'transaction_ref' => 'STRIPE_' . strtoupper(Str::random(10)),
            'message' => 'Payment processed successfully'
        ];
    }

    private function processPaypalPayment($transaction, $request)
    {
        // Mock PayPal payment - replace with actual PayPal integration
        return [
            'success' => true,
            'transaction_ref' => 'PAYPAL_' . strtoupper(Str::random(10)),
            'message' => 'Payment processed successfully'
        ];
    }

    private function processBankTransfer($transaction, $request)
    {
        // Bank transfer requires manual verification
        return [
            'success' => false,
            'message' => 'Bank transfer requires manual verification'
        ];
    }

    /**
     * Process refund
     */
    private function processRefund($transaction, $reason)
    {
        // Mock refund processing - replace with actual payment gateway integration
        return [
            'success' => true,
            'message' => 'Refund processed successfully'
        ];
    }

    /**
     * Calculate success rate
     */
    private function calculateSuccessRate($query, $startDate)
    {
        $total = $query->where('created_at', '>=', $startDate)->count();
        $completed = $query->where('created_at', '>=', $startDate)
            ->where('status', 'completed')->count();
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }
}