<?php

// app/Models/Coupon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
        'conditions',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'conditions' => 'json',
    ];

    // Relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_user')
            ->withPivot('order_id', 'discount_amount')
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    // Check if coupon is valid for use
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        // Check dates
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check usage limit
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
