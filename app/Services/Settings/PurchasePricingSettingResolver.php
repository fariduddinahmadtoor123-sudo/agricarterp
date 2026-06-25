<?php

namespace App\Services\Settings;

use App\Models\PurchasePricingSetting;

class PurchasePricingSettingResolver
{
    /**
     * @var array{wholesale: string, super_wholesale: string, distributor: string}|null
     */
    protected ?array $markupCache = null;

    public function settings(): ?PurchasePricingSetting
    {
        return PurchasePricingSetting::query()->first();
    }

    public function shouldUpdateProductPricesFromPurchases(): bool
    {
        return (bool) ($this->settings()?->update_product_prices_from_purchases
            ?? config('settings.purchase_pricing.update_product_prices_from_purchases', false));
    }

    /**
     * @return array<string, string>
     */
    public function priceCodeWording(): array
    {
        $wording = $this->settings()?->price_code_wording
            ?? config('settings.purchase_pricing.default_price_code_wording', []);

        $normalized = [];

        foreach (range(0, 9) as $digit) {
            $key = (string) $digit;
            $normalized[$key] = (string) ($wording[$key] ?? $wording[$digit] ?? $key);
        }

        return $normalized;
    }

    /**
     * @return array{wholesale: string, super_wholesale: string, distributor: string}
     */
    public function markupPercentagesForRows(): array
    {
        if ($this->markupCache !== null) {
            return $this->markupCache;
        }

        $defaults = config('settings.purchase_pricing', []);
        $setting = $this->settings();

        $this->markupCache = [
            'wholesale' => $this->formatMarkup(
                $setting?->wholesale_markup_pct ?? $defaults['wholesale_markup_pct'] ?? '10',
            ),
            'super_wholesale' => $this->formatMarkup(
                $setting?->super_wholesale_markup_pct ?? $defaults['super_wholesale_markup_pct'] ?? '8',
            ),
            'distributor' => $this->formatMarkup(
                $setting?->distributor_markup_pct ?? $defaults['distributor_markup_pct'] ?? '12',
            ),
        ];

        return $this->markupCache;
    }

    /**
     * @return array<string, array{label: string, default_pct: string}>
     */
    public function tierLabels(): array
    {
        $markups = $this->markupPercentagesForRows();

        return [
            'wholesale' => [
                'label' => 'Wholesale',
                'default_pct' => $markups['wholesale'],
            ],
            'super_wholesale' => [
                'label' => 'Super Wholesale',
                'default_pct' => $markups['super_wholesale'],
            ],
            'distributor' => [
                'label' => 'Distributor',
                'default_pct' => $markups['distributor'],
            ],
        ];
    }

    protected function formatMarkup(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $number = (float) str_replace(',', '', (string) $value);

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
