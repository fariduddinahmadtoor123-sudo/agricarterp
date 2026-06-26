<?php

namespace App\Services\OnlineStore;

use App\Models\OnlineStore\StoreFrontSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreFrontSettingsResolver
{
    public function model(): StoreFrontSetting
    {
        return StoreFrontSetting::query()->firstOrCreate([], $this->defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public function forStorefront(): array
    {
        $settings = $this->model();
        $pages = app(StorePageLinkResolver::class);

        return [
            'top_bar_left' => (string) $settings->top_bar_left,
            'top_bar_center' => (string) $settings->top_bar_center,
            'top_bar_right' => (string) $settings->top_bar_right,
            'ticker_en' => (string) ($settings->ticker_en ?? ''),
            'ticker_ur' => (string) ($settings->ticker_ur ?? ''),
            'homepage_categories_per_row' => (int) $settings->homepage_categories_per_row,
            'tablet_categories_per_row' => (int) config('online-store.tablet_categories_per_row', 2),
            'mobile_categories_per_row' => (int) config('online-store.mobile_categories_per_row', 1),
            'social_links' => $this->socialLinksForStorefront($settings),
            'header_navigation' => $pages->resolveLinks($settings->header_navigation ?? []),
            'footer_logo_url' => app(StoreFooterLogoStorage::class)->url(
                $settings->footer_logo_removed ? null : $settings->footer_logo_path,
            ),
            'footer_about_en' => (string) ($settings->footer_about_en ?? ''),
            'footer_about_ur' => (string) ($settings->footer_about_ur ?? ''),
            'footer_quick_links' => $pages->resolveLinks($settings->footer_quick_links ?? []),
            'footer_legal_links' => $pages->resolveLinks($settings->footer_legal_links ?? []),
            'contact_email' => (string) ($settings->contact_email ?? ''),
            'contact_phone' => (string) ($settings->contact_phone ?? ''),
            'map_embed_url' => (string) ($settings->map_embed_url ?? ''),
            'map_embed_src' => app(StoreMapEmbedNormalizer::class)->normalize($settings->map_embed_url),
            'copyright_line' => (string) ($settings->copyright_line ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formState(): array
    {
        $settings = $this->model();
        $logoStorage = app(StoreFooterLogoStorage::class);

        return [
            'top_bar_left' => $settings->top_bar_left,
            'top_bar_center' => $settings->top_bar_center,
            'top_bar_right' => $settings->top_bar_right,
            'ticker_en' => $settings->ticker_en,
            'ticker_ur' => $settings->ticker_ur,
            'homepage_categories_per_row' => (string) $settings->homepage_categories_per_row,
            'social_links' => $this->normalizeRepeater($settings->social_links),
            'header_navigation' => $this->normalizeRepeater($settings->header_navigation),
            'footer_logo' => filled($settings->footer_logo_path) ? [$settings->footer_logo_path] : [],
            'footer_logo_removed' => (bool) $settings->footer_logo_removed,
            'footer_about_en' => $settings->footer_about_en,
            'footer_about_ur' => $settings->footer_about_ur,
            'footer_quick_links' => $this->normalizeRepeater($settings->footer_quick_links),
            'footer_legal_links' => $this->normalizeRepeater($settings->footer_legal_links),
            'contact_email' => $settings->contact_email,
            'contact_phone' => $settings->contact_phone,
            'map_embed_url' => $settings->map_embed_url,
            'copyright_line' => $settings->copyright_line,
            '_footer_logo_preview' => $logoStorage->url($settings->footer_logo_path),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'top_bar_left' => 'یا حی',
            'top_bar_center' => 'بسم اللہ الرحمن الرحیم',
            'top_bar_right' => 'یا قیوم',
            'ticker_en' => 'Catalog only — Online ordering coming soon inshallah',
            'ticker_ur' => 'فی الحال کیٹلاگ — آن لائن آرڈرنگ ان شاء اللہ جلد',
            'homepage_categories_per_row' => (int) config('online-store.default_homepage_categories_per_row', 5),
            'social_links' => [],
            'header_navigation' => [],
            'footer_about_en' => "Pakistan's Trusted Agriculture Catalog",
            'footer_about_ur' => 'پاکستان کا قابل اعتماد زرعی کیٹلاگ',
            'footer_quick_links' => [],
            'footer_legal_links' => [],
            'copyright_line' => '© ' . now()->year . ' Agricart.pk. All Rights Reserved.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeRepeater(?array $items): array
    {
        return array_values($items ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function socialLinksForStorefront(StoreFrontSetting $settings): array
    {
        $platforms = config('online-store.social_platforms', []);

        return collect($settings->social_links ?? [])
            ->filter(fn (array $row): bool => filled($row['url'] ?? null))
            ->map(fn (array $row): array => [
                'platform' => (string) ($row['platform'] ?? ''),
                'label' => $platforms[$row['platform'] ?? ''] ?? (string) ($row['platform'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
            ])
            ->values()
            ->all();
    }
}
