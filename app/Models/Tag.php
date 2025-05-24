<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'tags';

    protected $fillable = ['tag_name', 'tag_description'];

    public function products()
    {
        return $this->morphedByMany(Product::class, 'taggable', 'tag_mappings');
    }
}
