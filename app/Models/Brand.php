<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const AI_STATUS_PENDING = 'pending';

    public const AI_STATUS_PROCESSING = 'processing';

    public const AI_STATUS_COMPLETE = 'complete';

    public const AI_STATUS_REVIEW = 'review';

    public const AI_STATUS_FAILED = 'failed';

    protected $fillable = [
        'brand_number',
        'name_en',
        'name_ur',
        'short_note',
        'logo_path',
        'short_description_en',
        'short_description_ur',
        'description_en',
        'description_ur',
        'brand_overview_en',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'country',
        'website',
        'ai_status',
        'ai_generated_at',
        'ai_version',
        'status',
        'categories_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categories_count' => 'integer',
            'ai_generated_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'brand_category')
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

    public function scopeWhereNormalizedEnglishName($query, string $name)
    {
        return $query->whereRaw('LOWER(TRIM(name_en)) = ?', [self::normalizeEnglishName($name)]);
    }

    public static function normalizeEnglishName(string $name): string
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
