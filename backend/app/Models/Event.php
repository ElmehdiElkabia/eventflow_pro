<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
		'images',
	];

	protected $casts = [
		'start_date' => 'datetime',
		'end_date' => 'datetime',
		'images' => 'array',
	];

	protected $appends = [
		'banner_url',
		'image_urls',
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
	public function getBannerUrlAttribute()
	{
		if ($this->banner) {
			return Storage::url($this->banner);
		}
		return null;
	}

	public function getImageUrlsAttribute()
	{
		if ($this->images && is_array($this->images)) {
			return array_map(function ($image) {
				return Storage::url($image);
			}, $this->images);
		}
		return [];
	}

	// Helper methods
	public function addImage($imagePath)
	{
		$images = $this->images ?? [];
		$images[] = $imagePath;
		$this->update(['images' => $images]);
	}

	public function removeImage($imagePath)
	{
		$images = $this->images ?? [];
		$images = array_filter($images, function ($image) use ($imagePath) {
			return $image !== $imagePath;
		});
		$this->update(['images' => array_values($images)]);

		// Delete the file
		if (Storage::disk('public')->exists($imagePath)) {
			Storage::disk('public')->delete($imagePath);
		}
	}

	public function clearAllImages()
	{
		if ($this->images) {
			foreach ($this->images as $image) {
				if (Storage::disk('public')->exists($image)) {
					Storage::disk('public')->delete($image);
				}
			}
		}

		if ($this->banner && Storage::disk('public')->exists($this->banner)) {
			Storage::disk('public')->delete($this->banner);
		}

		$this->update(['banner' => null, 'images' => null]);
	}
}
