<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagMapping extends Model
{
    protected $table = 'tag_mappings';

    protected $fillable = ['taggable_id', 'taggable_type', 'tag_id', 'created_at', 'updated_at'];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public function taggable()
    {
        return $this->morphTo();
    }
}
