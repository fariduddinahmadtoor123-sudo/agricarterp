<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductControl extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_WARRANTY = 'warranty';

    public const TYPE_GUARANTEE = 'guarantee';

    public const TYPE_RETURN_POLICY = 'return_policy';

    public const TYPE_REPLACEMENT_POLICY = 'replacement_policy';

    public const TYPE_HANDLING_ALERT = 'handling_alert';

    public const TYPE_USAGE_NOTE = 'usage_note';

    public const TYPE_WARNING = 'warning';

    protected $fillable = [
        'control_number',
        'name',
        'control_type',
        'status',
    ];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ProductControlGroup::class, 'product_control_group_control', 'control_id', 'control_group_id')
            ->withPivot('sort_order')
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
