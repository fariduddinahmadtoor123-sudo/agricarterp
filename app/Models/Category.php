<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

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
        'image_path',
        'description_en',
        'description_ur',
        'short_description_en',
        'short_description_ur',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'hs_code',
        'usage_en',
        'usage_ur',
        'benefits_en',
        'benefits_ur',
        'warnings_en',
        'warnings_ur',
        'import_export_notes_en',
        'import_export_notes_ur',
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
