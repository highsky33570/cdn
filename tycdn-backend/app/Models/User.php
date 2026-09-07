<?php

namespace App\Models;

use App\Casts\CdnflyEncrypted;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'cdnfly_user_id', 'cdnfly_api_key', 'cdnfly_api_secret', 'cdnfly_synced_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'cdnfly_api_key', 'cdnfly_api_secret'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'cdnfly_synced_at' => 'datetime',
            'cdnfly_api_key' => CdnflyEncrypted::class,
            'cdnfly_api_secret' => CdnflyEncrypted::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasCdnflyApiKey(): bool
    {
        return $this->cdnfly_api_key !== null && $this->cdnfly_api_secret !== null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function serviceInstances(): HasMany
    {
        return $this->hasMany(ServiceInstance::class);
    }
}
