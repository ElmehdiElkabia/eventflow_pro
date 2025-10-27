<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
	use HasFactory;

	protected $fillable = [
		'event_id',
		'name',
		'type',
		'price',
		'quantity',
		'sold',
		'qr_code',
		'sale_start',
		'sale_end',
	];

	protected $casts = [
		'price' => 'decimal:2',
		'sale_start' => 'datetime',
		'sale_end' => 'datetime',
	];

	public function event()
	{
		return $this->belongsTo(Event::class);
	}

	public function transactions()
	{
		return $this->hasMany(Transaction::class);
	}
}
