<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'name',
        'email',
        'password',
        'bio',
        'username',
        'phone',
        'avatar',
        'google_id',
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
        ];
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ads::class);
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
}
