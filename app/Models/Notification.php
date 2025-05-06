<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'type',
        'status',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the parent notifiable entity (e.g., Contest, Payment).
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who receives the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
