<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const AI_STATUS_PENDING = 'pending';

    public const AI_STATUS_PROCESSING = 'processing';

    public const AI_STATUS_COMPLETE = 'complete';

    public const AI_STATUS_REVIEW = 'review';

    public const AI_STATUS_FAILED = 'failed';

    protected $fillable = [
        'parent_id',
        'category_number',
        'visual_mapping_code',
        'full_path',
        'level',
        'is_leaf',
        'sort_order',
        'name_en',
        'name_ur',
        'slug',
        'image_path',
        'description_en',
        'description_ur',
        'short_description_en',
        'short_description_ur',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_focus_keyword',
        'search_terms',
        'hs_code',
        'usage_en',
        'usage_ur',
        'benefits_en',
        'benefits_ur',
        'warnings_en',
        'warnings_ur',
        'import_export_notes_en',
        'import_export_notes_ur',
        'faqs_en',
        'faqs_ur',
        'buying_guide_en',
        'buying_guide_ur',
        'common_applications_en',
        'common_applications_ur',
        'ai_status',
        'ai_generated_at',
        'ai_version',
        'customs_notes_en',
        'customs_notes_ur',
        'import_notes_en',
        'import_notes_ur',
        'export_notes_en',
        'export_notes_ur',
        'status',
        'products_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_leaf' => 'boolean',
            'level' => 'integer',
            'sort_order' => 'integer',
            'products_count' => 'integer',
            'search_terms' => 'array',
            'faqs_en' => 'array',
            'faqs_ur' => 'array',
            'ai_generated_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
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
