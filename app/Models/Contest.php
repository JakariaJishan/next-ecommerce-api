<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contest extends Model
{
    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'status',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(ContestEntry::class, 'contest_id');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

}
