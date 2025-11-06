<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'event_id',
        'quantity',
        'amount',
        'status',
        'payment_gateway',
        'transaction_ref',
        'payment_data',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_data' => 'json',
        'refunded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function canBeRefunded()
    {
        return $this->status === 'completed' && 
               $this->ticket && 
               $this->ticket->event && 
               $this->ticket->event->start_date > now();
    }

    public function isRefunded()
    {
        return $this->status === 'refunded';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }
}