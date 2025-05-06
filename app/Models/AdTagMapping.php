<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdTagMapping extends Model
{
    protected $fillable = ['ad_id', 'tag_id'];

    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ads::class, 'ad_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(AdTag::class, 'tag_id');
    }

}
