<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/** Stores account data and connects existing content ownership and permission relationships. */
#[Fillable(['name', 'email', 'password', 'phone', 'age'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Return this account's WorshipIdentity records. */
    public function worshipIdentities(): HasMany
    {
        return $this->hasMany(WorshipIdentity::class);
    }

    /** Return this account's PrayerSetting records. */
    public function prayerSetting(): HasOne
    {
        return $this->hasOne(PrayerSetting::class);
    }

    /** Return this account's QuranBookmark records. */
    public function quranBookmarks(): HasMany
    {
        return $this->hasMany(QuranBookmark::class);
    }

    /** Return this account's QuranFavorite records. */
    public function quranFavorites(): HasMany
    {
        return $this->hasMany(QuranFavorite::class);
    }

    /** Return this account's QuranReadingProgress records. */
    public function quranReadingProgress(): HasOne
    {
        return $this->hasOne(QuranReadingProgress::class);
    }

    /** Return this account's QuranPreference records. */
    public function quranPreference(): HasOne
    {
        return $this->hasOne(QuranPreference::class);
    }

    /** Return Wird owned through registered or claimed guest identities. */
    public function personalWirds(): HasManyThrough
    {
        return $this->hasManyThrough(PersonalWird::class, WorshipIdentity::class);
    }

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
            'age' => 'integer',
            'account_status' => AccountStatus::class,
        ];
    }
}
