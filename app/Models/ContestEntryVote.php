<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContestEntryVote extends Model
{
    protected $fillable = ['entry_id', 'user_id'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ContestEntry::class, 'entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
