<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const AI_STATUS_PENDING = 'pending';

    public const AI_STATUS_PROCESSING = 'processing';

    public const AI_STATUS_COMPLETE = 'complete';

    public const AI_STATUS_REVIEW = 'review';

    public const AI_STATUS_FAILED = 'failed';

    protected $fillable = [
        'product_number',
        'category_id',
        'brand_id',
        'base_unit_id',
        'packing_unit_id',
        'packing_value',
        'name_en',
        'name_ur',
        'required_quantity',
        'alert_quantity',
        'status',
        'short_description_en',
        'short_description_ur',
        'description_en',
        'description_ur',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_focus_keyword',
        'search_terms',
        'hs_code',
        'usage_en',
        'usage_ur',
        'ai_status',
        'ai_generated_at',
        'ai_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'packing_value' => 'decimal:4',
            'required_quantity' => 'decimal:4',
            'alert_quantity' => 'decimal:4',
            'search_terms' => 'array',
            'ai_generated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function packingUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'packing_unit_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->orderBy('sort_order');
    }

    public function categoryTags(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category_tags')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function controlGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductControlGroup::class, 'product_product_control_group', 'product_id', 'control_group_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function individualControls(): BelongsToMany
    {
        return $this->belongsToMany(ProductControl::class, 'product_product_control', 'product_id', 'control_id')
            ->withPivot(['assignment_source', 'sort_order'])
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
