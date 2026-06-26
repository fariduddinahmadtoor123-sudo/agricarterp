<?php

namespace App\Models\OnlineStore;

use Illuminate\Database\Eloquent\Model;

class StoreFrontSetting extends Model
{
    protected $fillable = [
        'top_bar_left',
        'top_bar_center',
        'top_bar_right',
        'ticker_en',
        'ticker_ur',
        'homepage_categories_per_row',
        'social_links',
        'header_navigation',
        'footer_logo_path',
        'footer_logo_removed',
        'footer_about_en',
        'footer_about_ur',
        'footer_quick_links',
        'footer_legal_links',
        'contact_email',
        'contact_phone',
        'map_embed_url',
        'copyright_line',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'homepage_categories_per_row' => 'integer',
            'social_links' => 'array',
            'header_navigation' => 'array',
            'footer_logo_removed' => 'boolean',
            'footer_quick_links' => 'array',
            'footer_legal_links' => 'array',
        ];
    }
}
