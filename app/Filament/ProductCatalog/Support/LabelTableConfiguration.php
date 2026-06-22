<?php

namespace App\Filament\ProductCatalog\Support;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LabelTableConfiguration
{
    public static function applyListLayout(Table $table): Table
    {
        return ProductCatalogTableSearch::apply(
            $table
                ->extraAttributes([
                    'class' => 'agricart-contacts-list agricart-contacts-list-labels',
                ])
                ->contentGrid([
                    'default' => 1,
                    'lg' => 2,
                    '2xl' => 3,
                ])
                ->paginated([12, 24, 48]),
            function (Builder $query, string $term): void {
                $query
                    ->where('product_number', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhereHas('brand', fn (Builder $query): Builder => $query->where('name_en', 'like', $term))
                    ->orWhereHas('category', fn (Builder $query): Builder => $query->where('full_path', 'like', $term))
                    ->orWhereHas('attributeValues', fn (Builder $query): Builder => $query->where('value', 'like', $term));
            },
        );
    }
}
