<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_WEIGHT = 'weight';

    public const TYPE_VOLUME = 'volume';

    public const TYPE_LENGTH = 'length';

    public const TYPE_AREA = 'area';

    public const TYPE_COUNT = 'count';

    public const TYPE_PACKAGING = 'packaging';

    public const AI_STATUS_PENDING = 'pending';

    public const AI_STATUS_PROCESSING = 'processing';

    public const AI_STATUS_COMPLETE = 'complete';

    public const AI_STATUS_REVIEW = 'review';

    public const AI_STATUS_FAILED = 'failed';

    protected $fillable = [
        'unit_number',
        'name_en',
        'abbreviation_en',
        'name_ur',
        'abbreviation_ur',
        'usage_notes',
        'unit_type',
        'ai_status',
        'ai_generated_at',
        'ai_version',
        'status',
        'is_standard',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_standard' => 'boolean',
            'sort_order' => 'integer',
            'ai_generated_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeWhereNormalizedEnglishName($query, string $name)
    {
        return $query->whereRaw('LOWER(TRIM(name_en)) = ?', [self::normalizeEnglishName($name)]);
    }

    public function scopeWhereNormalizedAbbreviation($query, string $abbreviation)
    {
        return $query->whereRaw('LOWER(TRIM(abbreviation_en)) = ?', [self::normalizeAbbreviation($abbreviation)]);
    }

    public static function normalizeEnglishName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public static function normalizeAbbreviation(string $abbreviation): string
    {
        return mb_strtolower(trim($abbreviation));
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
