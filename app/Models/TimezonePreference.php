<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimezonePreference extends Model
{
    protected $table = 'timezone_preferences';
    protected $fillable = ['user_id', 'timezone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
