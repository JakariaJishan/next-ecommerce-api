<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewer_id',
        'reviewed_user_id',
        'rating',
        'comment',
        'is_approved'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean'
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_user_id');
    }
}
