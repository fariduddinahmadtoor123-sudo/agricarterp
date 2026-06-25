<?php

namespace App\Services\Settings;

use App\Models\PrintingSetting;

class PrintingSettingResolver
{
    protected ?PrintingSetting $cached = null;

    public function settings(): ?PrintingSetting
    {
        return $this->cached ??= PrintingSetting::query()->first();
    }

    public function defaultDocumentPaper(): string
    {
        return (string) ($this->settings()?->default_document_paper
            ?? config('printing.defaults.default_document_paper', 'a4'));
    }

    public function defaultPurchaseInvoicePaper(): string
    {
        return (string) ($this->settings()?->default_purchase_invoice_paper
            ?? config('printing.defaults.default_purchase_invoice_paper', 'a4'));
    }

    /**
     * @return array{width_mm: float, height_mm: float, gap_mm: float, preset: string, sheet_paper: string, printer_note: ?string}
     */
    public function priceTagLabel(): array
    {
        $setting = $this->settings();
        $preset = (string) ($setting?->price_tag_label_preset
            ?? config('printing.defaults.price_tag_label_preset', '38x25'));

        $presetConfig = config("printing.label_presets.{$preset}", []);

        if ($preset === 'custom' && $setting !== null) {
            return [
                'preset' => $preset,
                'width_mm' => (float) $setting->price_tag_width_mm,
                'height_mm' => (float) $setting->price_tag_height_mm,
                'gap_mm' => (float) $setting->price_tag_gap_mm,
                'sheet_paper' => (string) ($setting->price_tag_sheet_paper ?? 'a4'),
                'printer_note' => $setting->barcode_printer_note,
            ];
        }

        return [
            'preset' => $preset,
            'width_mm' => (float) ($setting?->price_tag_width_mm ?? $presetConfig['width_mm'] ?? 38),
            'height_mm' => (float) ($setting?->price_tag_height_mm ?? $presetConfig['height_mm'] ?? 25),
            'gap_mm' => (float) ($setting?->price_tag_gap_mm ?? $presetConfig['gap_mm'] ?? 3),
            'sheet_paper' => (string) ($setting?->price_tag_sheet_paper
                ?? config('printing.defaults.price_tag_sheet_paper', 'a4')),
            'printer_note' => $setting?->barcode_printer_note,
        ];
    }

    public function priceTagSheetCssPage(): string
    {
        $sheet = $this->priceTagLabel()['sheet_paper'];

        return (string) config("printing.document_paper_sizes.{$sheet}.css_page", 'A4 portrait');
    }

    /**
     * @return array{profile: string, width_mm: float, css_page: string}
     */
    public function posReceiptProfile(): array
    {
        $profile = (string) ($this->settings()?->pos_receipt_profile
            ?? config('printing.defaults.pos_receipt_profile', '80mm'));

        $config = config("printing.thermal_receipt_profiles.{$profile}", []);

        return [
            'profile' => $profile,
            'width_mm' => (float) ($config['width_mm'] ?? 80),
            'css_page' => (string) ($config['css_page'] ?? '80mm auto'),
        ];
    }

    public function documentCssPage(string $paperKey): string
    {
        return (string) config("printing.document_paper_sizes.{$paperKey}.css_page", 'A4 portrait');
    }

    /**
     * Maps universal printing paper keys to purchase worksheet print keys.
     */
    public function purchaseSheetPaperKey(): string
    {
        return match ($this->defaultPurchaseInvoicePaper()) {
            'a5' => 'compact',
            default => 'a4',
        };
    }

    /**
     * @return array<string, string>
     */
    public function documentPaperOptions(): array
    {
        return collect(config('printing.document_paper_sizes', []))
            ->mapWithKeys(fn (array $paper, string $key): array => [$key => $paper['label']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function labelPresetOptions(): array
    {
        return collect(config('printing.label_presets', []))
            ->mapWithKeys(fn (array $preset, string $key): array => [$key => $preset['label']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function thermalReceiptOptions(): array
    {
        return collect(config('printing.thermal_receipt_profiles', []))
            ->mapWithKeys(fn (array $profile, string $key): array => [$key => $profile['label']])
            ->all();
    }
}
