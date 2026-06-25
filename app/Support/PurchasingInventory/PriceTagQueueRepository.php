<?php

namespace App\Support\PurchasingInventory;

use App\Services\Settings\PrintingSettingResolver;

class PriceTagQueueRepository
{
    public const SESSION_KEY_QUEUE = 'price_tag_queue';

    public const SESSION_KEY_SETTINGS = 'price_tag_settings';

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        $lines = session(self::SESSION_KEY_QUEUE, []);

        return is_array($lines) ? array_values($lines) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function persistLines(array $lines): void
    {
        session([self::SESSION_KEY_QUEUE => array_values($lines)]);
    }

    public function clear(): void
    {
        session([self::SESSION_KEY_QUEUE => []]);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $printing = app(PrintingSettingResolver::class)->priceTagLabel();

        $defaults = [
            'scan_mode' => (string) config('purchasing-inventory.price_tag_default_scan_mode', 'barcode'),
            'fields' => config('purchasing-inventory.price_tag_default_fields', []),
            'label' => [
                'preset' => $printing['preset'],
                'width_mm' => $printing['width_mm'],
                'height_mm' => $printing['height_mm'],
                'gap_mm' => $printing['gap_mm'],
                'sheet_paper' => $printing['sheet_paper'],
            ],
        ];

        $stored = session(self::SESSION_KEY_SETTINGS, []);

        if (! is_array($stored)) {
            return $defaults;
        }

        $label = is_array($stored['label'] ?? null)
            ? array_merge($defaults['label'], $stored['label'])
            : $defaults['label'];

        return [
            'scan_mode' => (string) ($stored['scan_mode'] ?? $defaults['scan_mode']),
            'fields' => array_merge($defaults['fields'], is_array($stored['fields'] ?? null) ? $stored['fields'] : []),
            'label' => $label,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function persistSettings(array $settings): void
    {
        session([self::SESSION_KEY_SETTINGS => $settings]);
    }

    public function activeLineCount(): int
    {
        return collect($this->lines())
            ->filter(fn (array $line): bool => ! (bool) ($line['disabled'] ?? false))
            ->count();
    }

    public function stickerCount(): int
    {
        return (int) collect($this->lines())
            ->filter(fn (array $line): bool => ! (bool) ($line['disabled'] ?? false))
            ->sum(fn (array $line): int => max(0, (int) ($line['print_qty'] ?? 0)));
    }
}
