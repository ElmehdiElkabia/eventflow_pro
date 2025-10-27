<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
	use HasFactory;

	public $timestamps = false;

	protected $fillable = [
		'event_id',
		'views',
		'ticket_sales',
		'revenue',
	];

	protected $casts = [
		'revenue' => 'decimal:2',
		'updated_at' => 'datetime',
	];

	public function event()
	{
		return $this->belongsTo(Event::class);
	}
}
