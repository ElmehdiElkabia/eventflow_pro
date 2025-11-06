<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
	use HasFactory;

	protected $fillable = [
		'user_id',
		'event_id',
		'parent_id',
		'content',
	];

	public function user()
	{
		return $this->belongsTo(User::class);
	}

	public function event()
	{
		return $this->belongsTo(Event::class);
	}

	public function parent()
	{
		return $this->belongsTo(Comment::class, 'parent_id');
	}

	public function replies()
	{
		return $this->hasMany(Comment::class, 'parent_id');
	}

	    // Get only approved replies
    public function approvedReplies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
                   ->where('status', 'approved')
                   ->with('user:id,name,avatar');
    }

    // Accessor for replies count
    public function getRepliesCountAttribute()
    {
        return $this->replies()->count();
    }

    // Accessor to check if comment is a reply
    public function getIsReplyAttribute()
    {
        return !is_null($this->parent_id);
    }

    // Scope for top-level comments (not replies)
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    // Scope for approved comments
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Scope for pending comments
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
