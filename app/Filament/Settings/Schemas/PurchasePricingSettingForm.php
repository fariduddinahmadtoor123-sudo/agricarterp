<?php

namespace App\Filament\Settings\Schemas;

use App\Models\PurchasePricingSetting;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchasePricingSettingForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?PurchasePricingSetting $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-purchase-pricing-setting-form' . ($readOnly ? ' agricart-purchase-pricing-setting-form-readonly' : ''),
            ])
            ->components([
                Section::make('Purchase Invoice Behaviour')
                    ->compact()
                    ->schema([
                        Toggle::make('update_product_prices_from_purchases')
                            ->label('Update product prices from purchase invoices')
                            ->helperText('When enabled, saved purchase invoices can push sale and tier prices back to the product catalog.')
                            ->default(false),
                    ]),

                Section::make('Tier Markup Defaults')
                    ->compact()
                    ->description('Default markup percentages applied on purchase rate when entering purchase invoice lines. Product-specific quantity thresholds are configured on each product.')
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        TextInput::make('wholesale_markup_pct')
                            ->label('WS Markup %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999.99)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('super_wholesale_markup_pct')
                            ->label('SWS Markup %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999.99)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('distributor_markup_pct')
                            ->label('Distributor Markup %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999.99)
                            ->step(0.01)
                            ->suffix('%')
                            ->required(),
                    ]),

                Section::make('Price Code Wording')
                    ->compact()
                    ->description('Map each digit 0–9 to the letter or word printed on price tags instead of the real digit.')
                    ->columns(['default' => 2, 'sm' => 5])
                    ->schema(static::priceCodeWordingFields()),

                Section::make('Summary')
                    ->compact()
                    ->visible($readOnly && $record !== null)
                    ->schema([
                        Placeholder::make('updated_at_display')
                            ->label('Last Updated')
                            ->content(fn (): string => (string) ($record?->updated_at?->toDateTimeString() ?? '—')),
                    ]),
            ]);
    }

    /**
     * @return array<int, TextInput>
     */
    protected static function priceCodeWordingFields(): array
    {
        return collect(range(0, 9))
            ->map(fn (int $digit): TextInput => TextInput::make('price_code_wording.' . $digit)
                ->label((string) $digit)
                ->maxLength(30)
                ->required())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        $defaults = config('settings.purchase_pricing', []);

        return [
            'update_product_prices_from_purchases' => (bool) ($defaults['update_product_prices_from_purchases'] ?? false),
            'wholesale_markup_pct' => (string) ($defaults['wholesale_markup_pct'] ?? '10'),
            'super_wholesale_markup_pct' => (string) ($defaults['super_wholesale_markup_pct'] ?? '8'),
            'distributor_markup_pct' => (string) ($defaults['distributor_markup_pct'] ?? '12'),
            'price_code_wording' => $defaults['default_price_code_wording'] ?? static::fallbackPriceCodeWording(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(PurchasePricingSetting $setting): array
    {
        return [
            'update_product_prices_from_purchases' => (bool) $setting->update_product_prices_from_purchases,
            'wholesale_markup_pct' => static::formatMarkupForForm($setting->wholesale_markup_pct),
            'super_wholesale_markup_pct' => static::formatMarkupForForm($setting->super_wholesale_markup_pct),
            'distributor_markup_pct' => static::formatMarkupForForm($setting->distributor_markup_pct),
            'price_code_wording' => static::priceCodeWordingForForm($setting->price_code_wording ?? []),
            'updated_at_display' => $setting->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset($state['updated_at_display']);

        return $state;
    }

    /**
     * @param  array<string|int, mixed>  $wording
     * @return array<string, string>
     */
    protected static function priceCodeWordingForForm(array $wording): array
    {
        $normalized = [];

        foreach (range(0, 9) as $digit) {
            $key = (string) $digit;
            $normalized[$key] = (string) ($wording[$key] ?? $wording[$digit] ?? '');
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    protected static function fallbackPriceCodeWording(): array
    {
        return [
            '0' => 'S',
            '1' => 'T',
            '2' => 'U',
            '3' => 'V',
            '4' => 'W',
            '5' => 'X',
            '6' => 'Y',
            '7' => 'Z',
            '8' => 'A',
            '9' => 'B',
        ];
    }

    protected static function formatMarkupForForm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) str_replace(',', '', (string) $value);

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
