<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguagePreference extends Model
{
    protected $table = 'language_preferences';
    protected $fillable = ['user_id', 'language_code'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
