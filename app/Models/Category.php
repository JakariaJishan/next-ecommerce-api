<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'description',
        'parent_category_id',
    ];

    public function ads(): HasMany
    {
        return $this->hasMany(Ads::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
