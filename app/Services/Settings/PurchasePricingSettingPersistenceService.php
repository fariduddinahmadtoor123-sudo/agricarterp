<?php

namespace App\Services\Settings;

use App\Models\PurchasePricingSetting;
use App\Support\Authorization\PermissionChecker;
use App\Support\Settings\PurchasePricingSettingAuthorization;
use Illuminate\Support\Facades\DB;

class PurchasePricingSettingPersistenceService
{
    public function __construct(
        protected PurchasePricingSettingDataValidator $dataValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchasePricingSetting
    {
        PermissionChecker::authorizeAbility(fn (): bool => PurchasePricingSettingAuthorization::canCreate());

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data);

        return DB::transaction(function () use ($data): PurchasePricingSetting {
            return PurchasePricingSetting::query()->create($this->contentAttributes($data));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PurchasePricingSetting $setting, array $data): PurchasePricingSetting
    {
        PermissionChecker::authorizeAbility(fn (): bool => PurchasePricingSettingAuthorization::canEdit());

        $data = $this->prepareData($data);

        $this->dataValidator->validate($data, $setting);

        return DB::transaction(function () use ($setting, $data): PurchasePricingSetting {
            $setting->update($this->contentAttributes($data));

            return $setting->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareData(array $data): array
    {
        $data['update_product_prices_from_purchases'] = (bool) ($data['update_product_prices_from_purchases'] ?? false);
        $data['wholesale_markup_pct'] = $this->normalizeMarkupPct($data['wholesale_markup_pct'] ?? 0);
        $data['super_wholesale_markup_pct'] = $this->normalizeMarkupPct($data['super_wholesale_markup_pct'] ?? 0);
        $data['distributor_markup_pct'] = $this->normalizeMarkupPct($data['distributor_markup_pct'] ?? 0);
        $data['price_code_wording'] = $this->normalizePriceCodeWording($data['price_code_wording'] ?? []);

        return $data;
    }

    protected function normalizeMarkupPct(mixed $value): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return '0.00';
        }

        if (preg_match('/^\d+,\d+$/', $raw)) {
            $raw = str_replace(',', '.', $raw);
        } else {
            $raw = str_replace(',', '', $raw);
        }

        $number = is_numeric($raw) ? (float) $raw : 0.0;
        $number = max(0, min(999.99, $number));

        return number_format($number, 2, '.', '');
    }

    /**
     * @param  array<string|int, mixed>  $wording
     * @return array<string, string>
     */
    protected function normalizePriceCodeWording(array $wording): array
    {
        $defaults = config('settings.purchase_pricing.default_price_code_wording', []);
        $normalized = [];

        foreach (range(0, 9) as $digit) {
            $key = (string) $digit;
            $value = trim((string) ($wording[$key] ?? $wording[$digit] ?? ''));

            if ($value === '') {
                $value = trim((string) ($defaults[$key] ?? $key));
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentAttributes(array $data): array
    {
        return [
            'update_product_prices_from_purchases' => (bool) $data['update_product_prices_from_purchases'],
            'wholesale_markup_pct' => $data['wholesale_markup_pct'],
            'super_wholesale_markup_pct' => $data['super_wholesale_markup_pct'],
            'distributor_markup_pct' => $data['distributor_markup_pct'],
            'price_code_wording' => $data['price_code_wording'],
        ];
    }
}
