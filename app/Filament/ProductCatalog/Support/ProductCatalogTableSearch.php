<?php

namespace App\Filament\ProductCatalog\Support;

use Closure;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductCatalogTableSearch
{
    /**
     * Enable the global table search bar with a custom query callback.
     *
     * @param  Closure(Builder, string): void  $searchUsing  Receives the escaped LIKE term (e.g. %term%).
     */
    public static function apply(Table $table, Closure $searchUsing): Table
    {
        return $table
            ->searchable()
            ->searchUsing(function (Builder $query, string $search) use ($searchUsing): void {
                $search = trim($search);

                if ($search === '') {
                    return;
                }

                $term = '%' . addcslashes($search, '%_\\') . '%';

                $query->where(function (Builder $query) use ($searchUsing, $term): void {
                    $searchUsing($query, $term);
                });
            });
    }
}
