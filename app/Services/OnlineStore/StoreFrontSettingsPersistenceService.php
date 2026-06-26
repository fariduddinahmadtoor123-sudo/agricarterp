<?php

namespace App\Services\OnlineStore;

use App\Models\OnlineStore\StoreFrontSetting;
use App\Models\OnlineStore\StorePage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StoreFrontSettingsPersistenceService
{
    public function __construct(
        protected StoreFooterLogoStorage $logoStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): StoreFrontSetting
    {
        $settings = app(StoreFrontSettingsResolver::class)->model();
        $prepared = $this->prepare($data, $settings);

        validator($prepared, [
            'homepage_categories_per_row' => ['required', 'integer', 'in:3,4,5,6'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'map_embed_url' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($settings, $prepared): StoreFrontSetting {
            $settings->update($prepared);

            return $settings->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, StoreFrontSetting $settings): array
    {
        $logoRemoved = (bool) ($data['footer_logo_removed'] ?? false);
        $existingLogoPath = $settings->footer_logo_path;

        if (filled($existingLogoPath) && $this->logoStorage->locate($existingLogoPath) === null) {
            $existingLogoPath = null;
        }

        if ($logoRemoved) {
            $this->logoStorage->cleanupIfReplaced($existingLogoPath, null);
            $footerLogoPath = null;
        } else {
            $newLogoPath = $this->logoStorage->persistFromFormValue(
                $data['footer_logo'] ?? null,
                $existingLogoPath,
            );

            if ($newLogoPath !== $existingLogoPath) {
                $this->logoStorage->cleanupIfReplaced($existingLogoPath, $newLogoPath);
            }

            $footerLogoPath = $newLogoPath;
        }

        $mapEmbedUrl = app(StoreMapEmbedNormalizer::class)->normalize($data['map_embed_url'] ?? null);

        return [
            'top_bar_left' => trim((string) ($data['top_bar_left'] ?? '')),
            'top_bar_center' => trim((string) ($data['top_bar_center'] ?? '')),
            'top_bar_right' => trim((string) ($data['top_bar_right'] ?? '')),
            'ticker_en' => trim((string) ($data['ticker_en'] ?? '')),
            'ticker_ur' => trim((string) ($data['ticker_ur'] ?? '')),
            'homepage_categories_per_row' => (int) ($data['homepage_categories_per_row'] ?? config('online-store.default_homepage_categories_per_row', 5)),
            'social_links' => $this->normalizeSocialLinks($data['social_links'] ?? []),
            'header_navigation' => $this->normalizePageLinks($data['header_navigation'] ?? []),
            'footer_logo_path' => $footerLogoPath,
            'footer_logo_removed' => $logoRemoved,
            'footer_about_en' => trim((string) ($data['footer_about_en'] ?? '')),
            'footer_about_ur' => trim((string) ($data['footer_about_ur'] ?? '')),
            'footer_quick_links' => $this->normalizePageLinks($data['footer_quick_links'] ?? []),
            'footer_legal_links' => $this->normalizePageLinks($data['footer_legal_links'] ?? []),
            'contact_email' => trim((string) ($data['contact_email'] ?? '')) ?: null,
            'contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
            'map_embed_url' => $mapEmbedUrl,
            'copyright_line' => trim((string) ($data['copyright_line'] ?? '')) ?: null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function normalizeSocialLinks(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): array => [
                'platform' => (string) ($row['platform'] ?? ''),
                'url' => trim((string) ($row['url'] ?? '')),
            ])
            ->filter(fn (array $row): bool => filled($row['url']))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function normalizePageLinks(array $rows): array
    {
        return collect($rows)
            ->map(function (array $row): array {
                $pageId = $row['store_page_id'] ?? $row['page_id'] ?? null;

                return [
                    'store_page_id' => filled($pageId) ? (int) $pageId : null,
                ];
            })
            ->filter(fn (array $row): bool => filled($row['store_page_id']))
            ->values()
            ->all();
    }
}
