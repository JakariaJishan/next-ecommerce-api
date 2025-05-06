<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContestEntry extends Model
{
    protected $fillable = ['contest_id', 'user_id', 'contest_url', 'votes_count'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestEntryVote::class, 'entry_id');
    }

}
