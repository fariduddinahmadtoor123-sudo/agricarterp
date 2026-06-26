<?php

namespace App\Models\OnlineStore;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePage extends Model
{
    protected $fillable = [
        'title_en',
        'title_ur',
        'slug',
        'content_en',
        'content_ur',
        'is_published',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function titleForLocale(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        if ($locale === 'ur' && filled($this->title_ur)) {
            return $this->title_ur;
        }

        return $this->title_en;
    }
}
