<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Schemas\CategoryForm;
use App\Filament\ProductCatalog\Support\CategoryTableConfiguration;
use App\Models\Category;
use App\Services\ProductCatalog\CategoryHierarchyService;
use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Support\ProductCatalog\CategoryAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class Categories extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/categories';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'categories';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return CategoryTableConfiguration::applyListLayout(
            $table
                ->query(Category::query())
                ->defaultSort('visual_mapping_code', 'asc')
                ->deferLoading()
                ->modelLabel('Category')
                ->pluralModelLabel('Categories')
                ->headerActions(
                    CategoryAuthorization::canCreate()
                        ? [$this->getCreateCategoryAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('category_number')
                        ->label('Category Number')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('name_en')
                        ->label('English Name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('name_ur')
                        ->label('Urdu Name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('visual_mapping_code')
                        ->label('Visual Mapping')
                        ->searchable()
                        ->sortable()
                        ->badge()
                        ->color('gray'),
                    TextColumn::make('level')
                        ->label('Level')
                        ->sortable(),
                    TextColumn::make('products_count')
                        ->label('Products')
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.category_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            Category::STATUS_ACTIVE => 'success',
                            Category::STATUS_ARCHIVED => 'gray',
                            default => 'gray',
                        })
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getViewCategoryAction(),
                    $this->getEditCategoryAction(),
                    $this->getMoveCategoryAction(),
                    $this->getArchiveCategoryAction(),
                    $this->getRestoreCategoryAction(),
                ]),
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getCreateCategoryAction(): Action
    {
        return Action::make('createCategory')
            ->label('Add Category')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Category')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => CategoryForm::defaultState())
            ->schema(fn (Schema $schema): Schema => CategoryForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistCategory($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewCategoryAction(): Action
    {
        return Action::make('viewCategory')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => CategoryAuthorization::canView())
            ->modalHeading('View Category')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Category $record): array => CategoryForm::fromModel($record))
            ->schema(fn (Schema $schema, Category $record): Schema => CategoryForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditCategoryAction(): Action
    {
        return Action::make('editCategory')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Category $record): bool => CategoryAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Category')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Category $record): array => CategoryForm::fromModel($record))
            ->schema(fn (Schema $schema, Category $record): Schema => CategoryForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, Category $record): void {
                $this->persistCategory($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getMoveCategoryAction(): Action
    {
        $hierarchy = app(CategoryHierarchyService::class);

        return Action::make('moveCategory')
            ->label('Move')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (Category $record): bool => CategoryAuthorization::canMove() && $record->isActive())
            ->modalHeading('Move Category')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Move Category')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (Category $record): array => [
                'parent_id' => $record->parent_id,
                'current_path' => $record->full_path,
                'current_visual_code' => $record->visual_mapping_code,
            ])
            ->schema([
                TextInput::make('current_path')
                    ->label('Current Path')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('current_visual_code')
                    ->label('Current Visual Mapping Code')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('parent_id')
                    ->label('New Parent Category')
                    ->placeholder('Root level (no parent)')
                    ->searchable()
                    ->native(false)
                    ->options(fn (Category $record): array => $hierarchy->parentOptionsShort($record))
                    ->getSearchResultsUsing(fn (string $search, Category $record): array => $hierarchy->searchParentOptions($search, $record))
                    ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? $hierarchy->parentShortLabel($value) : null),
            ])
            ->action(function (array $data, Category $record, CategoryPersistenceService $persistence): void {
                try {
                    $persistence->move(
                        $record,
                        filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                    );
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
                    ->title('Category moved')
                    ->send();
            });
    }

    protected function getArchiveCategoryAction(): Action
    {
        return Action::make('archiveCategory')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Category')
            ->modalDescription('This category will be archived. Category number, SEO data, descriptions, and product relationships will be preserved. Visual mapping codes will not change.')
            ->visible(fn (Category $record): bool => CategoryAuthorization::canArchive() && $record->isActive())
            ->action(function (Category $record, CategoryPersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Category archived')
                    ->send();
            });
    }

    protected function getRestoreCategoryAction(): Action
    {
        return Action::make('restoreCategory')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Category')
            ->modalDescription('This category will be restored to active status.')
            ->visible(fn (Category $record): bool => CategoryAuthorization::canRestore() && $record->isArchived())
            ->action(function (Category $record, CategoryPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Category restored')
                    ->send();
            });
    }

    protected function persistCategory(Schema $schema, bool $another, ?Category $category = null): void
    {
        if ($category !== null && ! CategoryAuthorization::canEdit()) {
            abort(403);
        }

        if ($category === null && ! CategoryAuthorization::canCreate()) {
            abort(403);
        }

        $data = CategoryForm::normalizeState($schema->getState());

        try {
            $persistence = app(CategoryPersistenceService::class);

            if ($category !== null) {
                $persistence->update($category, $data);
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
            ->title('Category saved')
            ->send();

        if ($another) {
            if ($category !== null) {
                $this->unmountAction();
                $this->mountAction('createCategory');

                return;
            }

            $schema->fill(array_merge(CategoryForm::defaultState(), [
                'parent_id' => $data['parent_id'] ?? null,
            ]));
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
