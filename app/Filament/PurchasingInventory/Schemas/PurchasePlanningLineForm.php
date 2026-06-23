<?php

namespace App\Filament\PurchasingInventory\Schemas;

use App\Services\PurchasingInventory\PurchasePlanningProductSearch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchasePlanningLineForm
{
    public static function configure(Schema $schema, bool $readOnly = false): Schema
    {
        return $schema->components([
            Section::make('Product')
                ->schema([
                    Select::make('product_id')
                        ->label('Product')
                        ->searchable()
                        ->required()
                        ->disabled($readOnly)
                        ->getSearchResultsUsing(function (string $search): array {
                            $results = app(PurchasePlanningProductSearch::class)->search($search, 20);

                            return collect($results)
                                ->mapWithKeys(fn (array $product): array => [
                                    $product['id'] => $product['display_name'] . ' (' . $product['barcode'] . ')',
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            $product = app(PurchasePlanningProductSearch::class)->findById((int) $value);

                            return $product['display_name'] ?? null;
                        })
                        ->native(false),
                ]),
            Section::make('Planning Values')
                ->columns(2)
                ->schema([
                    TextInput::make('required_qty')
                        ->label('Required Qty')
                        ->disabled($readOnly),
                    TextInput::make('low_stock')
                        ->label('Low Stock')
                        ->disabled($readOnly),
                    TextInput::make('purchase_price')
                        ->label('Purchase Price')
                        ->disabled($readOnly),
                    TextInput::make('landing_cost')
                        ->label('Landing Cost')
                        ->disabled($readOnly),
                    TextInput::make('sale_price')
                        ->label('Sale Price')
                        ->disabled($readOnly),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'product_id' => null,
            'required_qty' => '',
            'low_stock' => '',
            'purchase_price' => '',
            'landing_cost' => '',
            'sale_price' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function fromRow(array $row): array
    {
        return [
            'product_id' => $row['product_id'] ?? null,
            'required_qty' => (string) ($row['required_qty'] ?? ''),
            'low_stock' => (string) ($row['low_stock'] ?? ''),
            'purchase_price' => (string) ($row['purchase_price'] ?? ''),
            'landing_cost' => (string) ($row['landing_cost'] ?? ''),
            'sale_price' => (string) ($row['sale_price'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        return [
            'product_id' => filled($state['product_id'] ?? null) ? (int) $state['product_id'] : null,
            'required_qty' => trim((string) ($state['required_qty'] ?? '')),
            'low_stock' => trim((string) ($state['low_stock'] ?? '')),
            'purchase_price' => trim((string) ($state['purchase_price'] ?? '')),
            'landing_cost' => trim((string) ($state['landing_cost'] ?? '')),
            'sale_price' => trim((string) ($state['sale_price'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function toRow(array $state, ?array $existing = null): array
    {
        $product = app(PurchasePlanningProductSearch::class)->findById((int) $state['product_id']);

        if ($product === null) {
            throw new \InvalidArgumentException('Product is required.');
        }

        return [
            'line_id' => $existing['line_id'] ?? (string) \Illuminate\Support\Str::uuid(),
            'product_id' => (int) $product['id'],
            'thumbnail_url' => $product['thumbnail_url'],
            'barcode' => $product['barcode'],
            'sku' => $product['sku'],
            'product_name' => $product['display_name'],
            'required_qty' => $state['required_qty'],
            'low_stock' => $state['low_stock'],
            'purchase_price' => $state['purchase_price'],
            'landing_cost' => $state['landing_cost'],
            'sale_price' => $state['sale_price'],
        ];
    }
}
