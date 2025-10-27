<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
	use HasApiTokens, HasFactory, Notifiable;

	protected $fillable = [
		'name',
		'email',
		'password',
		'role',
		'phone',
		'avatar',
		'status',
	];

	protected $hidden = [
		'password',
		'remember_token',
	];

	protected $casts = [
		'email_verified_at' => 'datetime',
		'password' => 'hashed',
	];

	public function events()
	{
		return $this->hasMany(Event::class, 'organizer_id');
	}

	public function tickets()
	{
		return $this->hasMany(Transaction::class);
	}

	public function notifications()
	{
		return $this->hasMany(Notification::class);
	}

	public function comments()
	{
		return $this->hasMany(Comment::class);
	}

	public function reviews()
	{
		return $this->hasMany(Review::class);
	}

	public function transactions()
	{
		return $this->hasMany(Transaction::class);
	}
}
