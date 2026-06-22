<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductControlGroup extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'group_number',
        'name',
        'status',
        'controls_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'controls_count' => 'integer',
        ];
    }

    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(ProductControl::class, 'product_control_group_control', 'control_group_id', 'control_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeWhereNormalizedName($query, string $name)
    {
        return $query->whereRaw('LOWER(TRIM(name)) = ?', [self::normalizeName($name)]);
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
