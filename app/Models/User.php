<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'user_number',
        'name',
        'full_address',
        'email',
        'password',
        'role_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function phones(): HasMany
    {
        return $this->hasMany(UserPhone::class)->orderBy('sort_order');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(UserBankAccount::class)->orderBy('sort_order');
    }

    public function document(): HasOne
    {
        return $this->hasOne(UserDocument::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! Schema::hasColumn($this->getTable(), 'role_id')) {
            return true;
        }

        if (($this->status ?? self::STATUS_ACTIVE) !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->role_id !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SLUG_SUPER_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasPermission(string $module, string $action): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role?->hasPermission($module . '.' . $action) ?? false;
    }
}
