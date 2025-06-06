<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles,
        InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'bio',
        'username',
        'phone',
        'avatar',
        'google_id',
        'gender',
        'date_of_birth',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'datetime',
        ];
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ads::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'author_id', 'id');
    }

    public function searchHistories(): HasMany
    {
        return $this->hasMany(SearchHistory::class, 'user_id', 'id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')->singleFile(); // Ensures only one avatar is stored
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function contestEntry(): HasOne
    {
        return $this->hasOne(ContestEntry::class);
    }

    public function contests(): HasManyThrough
    {
        return $this->hasManyThrough(Contest::class, ContestEntry::class, 'user_id', 'id', 'id', 'contest_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ContestEntryVote::class, 'user_id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishLists(): HasMany
    {
        return $this->hasMany(WishList::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function userNotifications(): Hasone
    {
        return $this->hasOne(UserNotification::class);
    }

    public function languagePreferences(): HasOne
    {
        return $this->hasOne(LanguagePreference::class);
    }

    public function currencyPreferences(): HasOne
    {
        return $this->hasOne(CurrencyPreference::class);
    }

    public function timezonePreferences(): HasOne
    {
        return $this->hasOne(TimezonePreference::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(UserReview::class, 'reviewer_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(UserReview::class, 'reviewed_user_id');
    }

    public function getAverageRatingAttribute()
    {
        return $this->reviewsReceived()->where('is_approved', true)->avg('rating') ?: 0;
    }

    public function getRatingCountAttribute()
    {
        return $this->reviewsReceived()->where('is_approved', true)->count();
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_user')
            ->withPivot('order_id', 'discount_amount')
            ->withTimestamps();
    }
}
