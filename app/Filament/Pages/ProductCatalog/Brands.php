<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Schemas\BrandForm;
use App\Filament\ProductCatalog\Support\BrandTableConfiguration;
use App\Models\Brand;
use App\Services\ProductCatalog\BrandLogoStorage;
use App\Services\ProductCatalog\BrandPersistenceService;
use App\Support\ProductCatalog\BrandAuthorization;
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

class Brands extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/brands';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'brands';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $logoStorage = app(BrandLogoStorage::class);

        return BrandTableConfiguration::applyListLayout(
            $table
                ->query(Brand::query())
                ->defaultSort('brand_number', 'asc')
                ->deferLoading()
                ->modelLabel('Brand')
                ->pluralModelLabel('Brands')
                ->headerActions(
                    BrandAuthorization::canCreate()
                        ? [$this->getCreateBrandAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('brand_number')
                        ->label('Brand Number')
                        ->sortable(),
                    ImageColumn::make('logo_path')
                        ->label('Logo')
                        ->getStateUsing(fn (Brand $record): ?string => $logoStorage->url($record->logo_path))
                        ->imageHeight(48)
                        ->extraImgAttributes([
                            'class' => 'agricart-brand-table-logo',
                        ]),
                    TextColumn::make('name_en')
                        ->label('English Name')
                        ->sortable(),
                    TextColumn::make('name_ur')
                        ->label('Urdu Name')
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('categories_count')
                        ->label('Categories')
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.brand_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            Brand::STATUS_ACTIVE => 'success',
                            Brand::STATUS_ARCHIVED => 'gray',
                            default => 'gray',
                        })
                        ->sortable(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->date()
                        ->sortable(),
                ])
                ->modifyQueryUsing(function (Builder $query, Table $table): Builder {
                    $search = trim((string) ($table->getLivewire()->tableSearch ?? ''));

                    if ($search === '') {
                        return $query;
                    }

                    $term = '%' . addcslashes($search, '%_\\') . '%';

                    return $query->where(function (Builder $query) use ($term): void {
                        $query
                            ->where('brand_number', 'like', $term)
                            ->orWhere('name_en', 'like', $term)
                            ->orWhere('name_ur', 'like', $term)
                            ->orWhereHas('categories', fn (Builder $query): Builder => $query->where('name_en', 'like', $term));
                    });
                })
                ->recordActions([
                    $this->getViewBrandAction(),
                    $this->getEditBrandAction(),
                    $this->getArchiveBrandAction(),
                    $this->getRestoreBrandAction(),
                ]),
        );
    }

    protected function getCreateBrandAction(): Action
    {
        return Action::make('createBrand')
            ->label('Add Brand')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Brand')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => BrandForm::defaultState())
            ->schema(fn (Schema $schema): Schema => BrandForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistBrand($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewBrandAction(): Action
    {
        return Action::make('viewBrand')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => BrandAuthorization::canView())
            ->modalHeading('View Brand')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Brand $record): array => BrandForm::fromModel($record))
            ->schema(fn (Schema $schema, Brand $record): Schema => BrandForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditBrandAction(): Action
    {
        return Action::make('editBrand')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Brand $record): bool => BrandAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Brand')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Brand $record): array => BrandForm::fromModel($record))
            ->schema(fn (Schema $schema, Brand $record): Schema => BrandForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, Brand $record): void {
                $this->persistBrand($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getArchiveBrandAction(): Action
    {
        return Action::make('archiveBrand')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Brand')
            ->modalDescription('This brand will be archived. Brand number, logo, category assignments, and all content will be preserved.')
            ->visible(fn (Brand $record): bool => BrandAuthorization::canArchive() && $record->isActive())
            ->action(function (Brand $record, BrandPersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Brand archived')
                    ->send();
            });
    }

    protected function getRestoreBrandAction(): Action
    {
        return Action::make('restoreBrand')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Brand')
            ->modalDescription('This brand will be restored to active status.')
            ->visible(fn (Brand $record): bool => BrandAuthorization::canRestore() && $record->isArchived())
            ->action(function (Brand $record, BrandPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Brand restored')
                    ->send();
            });
    }

    protected function persistBrand(Schema $schema, bool $another, ?Brand $brand = null): void
    {
        if ($brand !== null && ! BrandAuthorization::canEdit()) {
            abort(403);
        }

        if ($brand === null && ! BrandAuthorization::canCreate()) {
            abort(403);
        }

        $data = BrandForm::normalizeState($schema->getState());

        try {
            $persistence = app(BrandPersistenceService::class);

            if ($brand !== null) {
                $persistence->update($brand, $data);
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
            ->title('Brand saved')
            ->send();

        if ($another) {
            if ($brand !== null) {
                $this->unmountAction();
                $this->mountAction('createBrand');

                return;
            }

            $schema->fill(BrandForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
