<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdTag extends Model
{

    protected $fillable = ['tag_name', 'tag_description'];

    public function tagMappings(): HasMany
    {
        return $this->hasMany(AdTagMapping::class, 'tag_id');
    }

    public function ads(): BelongsToMany
    {
        return $this->belongsToMany(Ads::class, 'ad_tag_mappings', 'tag_id', 'ad_id');
    }
}
