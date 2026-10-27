<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar_url', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, TwoFactorAuthenticatable;

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
            'is_active' => 'boolean',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class, 'owner_id');
    }

    public function hasAdminPrivileges(): bool
    {
        return $this->hasAnyRole(['superadmin', 'manager']);
    }

    public function tokenWallet(): HasOne
    {
        return $this->hasOne(SearchTokenWallet::class, 'user_id');
    }

    public function tokenTransactions(): HasMany
    {
        return $this->hasMany(SearchTokenTransaction::class);
    }

    public function searchAdViews(): HasMany
    {
        return $this->hasMany(SearchAdView::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function agenciesOwned(): HasMany
    {
        return $this->hasMany(Agency::class, 'owner_id');
    }

    public function agencyMemberships(): HasMany
    {
        return $this->hasMany(AgencyMember::class);
    }

    public function memberAgencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_members')
            ->withPivot(['role', 'status', 'accepted_at'])
            ->wherePivot('status', 'active')
            ->withTimestamps();
    }

    /**
     * @return Collection<int, Agency>
     */
    public function accessibleAgencies(): Collection
    {
        return $this->agenciesOwned()
            ->get()
            ->merge($this->memberAgencies()->get())
            ->unique('id')
            ->values();
    }

    public function belongsToAgency(Agency $agency): bool
    {
        if ($agency->owner_id === $this->getKey()) {
            return true;
        }

        return $this->agencyMemberships()
            ->where('agency_id', $agency->getKey())
            ->where('status', 'active')
            ->exists();
    }

    public function agencyRole(Agency $agency): ?string
    {
        if ($agency->owner_id === $this->getKey()) {
            return 'owner';
        }

        return $this->agencyMemberships()
            ->where('agency_id', $agency->getKey())
            ->where('status', 'active')
            ->value('role');
    }
}
