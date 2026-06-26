<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Support\LabelTableConfiguration;
use App\Models\Product;
use App\Support\ProductCatalog\ProductLabelPresenter;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class Labels extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/labels';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'labels';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('printLabels')
                ->label('Print')
                ->icon(Heroicon::OutlinedPrinter)
                ->action(fn () => $this->js('window.print()')),
        ];
    }

    public function table(Table $table): Table
    {
        return LabelTableConfiguration::applyListLayout(
            $table
                ->query(
                    Product::query()
                        ->active()
                        ->with([
                            'category',
                            'brand',
                            'baseUnit',
                            'packingUnit',
                            'images',
                            'attributeValues.attribute',
                            'controlGroups',
                            'individualControls',
                            'categoryTags',
                        ]),
                )
                ->defaultSort('product_number', 'asc')
                ->deferLoading()
                ->modelLabel('Label')
                ->pluralModelLabel('Labels')
                ->columns([
                    TextColumn::make('label_preview')
                        ->label('')
                        ->state(fn (Product $record): HtmlString => new HtmlString(
                            app(ProductLabelPresenter::class)->html($record),
                        )),
                ])
                ->recordActions([])
                ->toolbarActions([]),
        );
    }
}
