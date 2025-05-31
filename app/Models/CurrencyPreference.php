<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyPreference extends Model
{
    protected $table = 'currency_preferences';
    protected $fillable = ['user_id', 'currency_code'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
