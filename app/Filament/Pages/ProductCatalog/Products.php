<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Schemas\ProductForm;
use App\Filament\ProductCatalog\Support\ProductCatalogTableSearch;
use App\Filament\ProductCatalog\Support\ProductTableConfiguration;
use App\Models\Product;
use App\Services\ProductCatalog\ProductImageStorage;
use App\Services\ProductCatalog\ProductPersistenceService;
use App\Support\ProductCatalog\ProductAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class Products extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/products';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'products';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $imageStorage = app(ProductImageStorage::class);

        return ProductCatalogTableSearch::apply(
            ProductTableConfiguration::applyListLayout(
            $table
                ->query(
                    Product::query()
                        ->with(['category', 'brand', 'baseUnit', 'packingUnit', 'images', 'categoryTags'])
                )
                ->defaultSort('product_number', 'asc')
                ->deferLoading()
                ->modelLabel('Product')
                ->pluralModelLabel('Products')
                ->headerActions(
                    ProductAuthorization::canCreate()
                        ? [$this->getCreateProductAction()]
                        : [],
                )
                ->columns([
                    ImageColumn::make('main_image')
                        ->label('Image')
                        ->getStateUsing(function (Product $record) use ($imageStorage): ?string {
                            $main = $record->images->firstWhere('is_main', true);

                            return $imageStorage->url($main?->image_path);
                        })
                        ->imageHeight(48)
                        ->extraImgAttributes([
                            'class' => 'agricart-product-table-image',
                        ]),
                    TextColumn::make('product_number')
                        ->label('Product Number')
                        ->sortable(),
                    TextColumn::make('name_en')
                        ->label('English Name')
                        ->sortable()
                        ->wrap(),
                    TextColumn::make('category.name_en')
                        ->label('Primary Category')
                        ->description(fn (Product $record): ?string => $record->category?->full_path)
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query
                                ->join('categories', 'categories.id', '=', 'products.category_id')
                                ->orderBy('categories.name_en', $direction)
                                ->select('products.*');
                        })
                        ->wrap(),
                    TextColumn::make('brand.name_en')
                        ->label('Brand')
                        ->sortable(),
                    TextColumn::make('packing_summary')
                        ->label('Packing')
                        ->state(function (Product $record): string {
                            $base = $record->baseUnit?->abbreviation_en ?? '';
                            $pack = $record->packingUnit?->abbreviation_en ?? '';

                            return trim($record->packing_value . ' ' . $base . ' / ' . $pack);
                        }),
                    TextColumn::make('display_category_tags')
                        ->label('Display Tags')
                        ->state(function (Product $record): string {
                            $names = $record->categoryTags->pluck('name_en')->filter()->values();

                            if ($names->isEmpty()) {
                                return '—';
                            }

                            return $names->take(2)->implode(', ') . ($names->count() > 2 ? ' +' . ($names->count() - 2) : '');
                        })
                        ->wrap(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.product_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            Product::STATUS_ACTIVE => 'success',
                            Product::STATUS_ARCHIVED => 'gray',
                            default => 'gray',
                        })
                        ->sortable(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->date()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getViewProductAction(),
                    $this->getEditProductAction(),
                    $this->getArchiveProductAction(),
                    $this->getRestoreProductAction(),
                ]),
            ),
            function (Builder $query, string $term): void {
                $query
                    ->where('product_number', 'like', $term)
                    ->orWhere('name_en', 'like', $term)
                    ->orWhereHas('brand', fn (Builder $query): Builder => $query->where('name_en', 'like', $term))
                    ->orWhereHas('category', fn (Builder $query): Builder => $query->where('full_path', 'like', $term))
                    ->orWhereHas('categoryTags', fn (Builder $query): Builder => $query->where('full_path', 'like', $term));
            },
        );
    }

    protected function getCreateProductAction(): Action
    {
        return Action::make('createProduct')
            ->label('Add Product')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Product')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => ProductForm::defaultState())
            ->schema(fn (Schema $schema): Schema => ProductForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistProduct($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewProductAction(): Action
    {
        return Action::make('viewProduct')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => ProductAuthorization::canView())
            ->modalHeading('View Product')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Product $record): array => ProductForm::fromModel($record))
            ->schema(fn (Schema $schema, Product $record): Schema => ProductForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditProductAction(): Action
    {
        return Action::make('editProduct')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Product $record): bool => ProductAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Product')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Product $record): array => ProductForm::fromModel($record))
            ->schema(fn (Schema $schema, Product $record): Schema => ProductForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, Product $record): void {
                $this->persistProduct($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getArchiveProductAction(): Action
    {
        return Action::make('archiveProduct')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Product')
            ->modalDescription('This product will be archived. Product number and all assignments will be preserved.')
            ->visible(fn (Product $record): bool => ProductAuthorization::canArchive() && $record->isActive())
            ->action(function (Product $record, ProductPersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Product archived')
                    ->send();
            });
    }

    protected function getRestoreProductAction(): Action
    {
        return Action::make('restoreProduct')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Product')
            ->modalDescription('This product will be restored to active status.')
            ->visible(fn (Product $record): bool => ProductAuthorization::canRestore() && $record->isArchived())
            ->action(function (Product $record, ProductPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Product restored')
                    ->send();
            });
    }

    protected function persistProduct(Schema $schema, bool $another, ?Product $product = null): void
    {
        if ($product !== null && ! ProductAuthorization::canEdit()) {
            abort(403);
        }

        if ($product === null && ! ProductAuthorization::canCreate()) {
            abort(403);
        }

        $data = ProductForm::normalizeState($schema->getState());

        try {
            $persistence = app(ProductPersistenceService::class);

            if ($product !== null) {
                $persistence->update($product, $data);
            } else {
                $persistence->create($data);
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Validation failed')
                ->send();

            throw $exception;
        }

        $this->flushCachedTableRecords();

        Notification::make()
            ->success()
            ->title('Product saved')
            ->send();

        if ($another) {
            if ($product !== null) {
                $this->unmountAction();
                $this->mountAction('createProduct');

                return;
            }

            $schema->fill(ProductForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
