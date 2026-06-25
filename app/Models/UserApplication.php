<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserApplication extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'application_number',
        'name',
        'email',
        'password',
        'full_address',
        'status',
        'rejection_reason',
        'assigned_role_id',
        'reviewed_by',
        'reviewed_at',
        'approved_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function phones(): HasMany
    {
        return $this->hasMany(UserApplicationPhone::class)->orderBy('sort_order');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(UserApplicationBankAccount::class)->orderBy('sort_order');
    }

    public function document(): HasOne
    {
        return $this->hasOne(UserApplicationDocument::class);
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
