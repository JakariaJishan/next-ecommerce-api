<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ads extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Searchable;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'price',
        'currency',
        'status',
        'moderation_status',
        'expiration_date'
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (string) $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'moderation_status' => $this->moderation_status,
            'category_id' => (string) $this->category_id,
        ];
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tagMappings(): HasMany
    {
        return $this->hasMany(AdTagMapping::class, 'ad_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(AdTag::class, 'ad_tag_mappings', 'ad_id', 'tag_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

}
