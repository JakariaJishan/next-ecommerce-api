<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';
    protected $fillable = ['user_id', 'email', 'sms', 'marketing', 'order_updates'];
    protected $casts = [
        'email' => 'boolean',
        'sms' => 'boolean',
        'marketing' => 'boolean',
        'order_updates' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
