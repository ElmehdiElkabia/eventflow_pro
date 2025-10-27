<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
	use HasFactory;

	protected $fillable = [
		'organizer_id',
		'title',
		'description',
		'category',
		'location',
		'start_date',
		'end_date',
		'capacity',
		'status',
		'banner',
	];

	protected $casts = [
		'start_date' => 'datetime',
		'end_date' => 'datetime',
	];

	public function organizer()
	{
		return $this->belongsTo(User::class, 'organizer_id');
	}

	public function tickets()
	{
		return $this->hasMany(Ticket::class);
	}

	public function comments()
	{
		return $this->hasMany(Comment::class);
	}

	public function reviews()
	{
		return $this->hasMany(Review::class);
	}

	public function analytics()
	{
		return $this->hasOne(Analytics::class);
	}
}
