<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Schemas\ProductControlForm;
use App\Filament\ProductCatalog\Schemas\ProductControlGroupForm;
use App\Filament\ProductCatalog\Support\ProductControlGroupTableConfiguration;
use App\Filament\ProductCatalog\Support\ProductControlTableConfiguration;
use App\Models\ProductControl;
use App\Models\ProductControlGroup;
use App\Services\ProductCatalog\ProductControlGroupPersistenceService;
use App\Services\ProductCatalog\ProductControlPersistenceService;
use App\Support\ProductCatalog\ProductControlAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class Controls extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/controls';

    protected static bool $shouldRegisterNavigation = false;

    public string $activeList = 'controls';

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'controls';
    }

    public function updatedActiveList(): void
    {
        $this->resetTable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.product-catalog.controls-tabs')
                ->viewData(fn (): array => [
                    'activeList' => $this->activeList,
                ]),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        if ($this->activeList === 'groups') {
            return $this->configureGroupsTable($table);
        }

        return $this->configureControlsTable($table);
    }

    protected function configureControlsTable(Table $table): Table
    {
        return ProductControlTableConfiguration::applyListLayout(
            $table
                ->query(ProductControl::query())
                ->defaultSort('control_number', 'asc')
                ->deferLoading()
                ->modelLabel('Control')
                ->pluralModelLabel('Controls')
                ->headerActions(
                    ProductControlAuthorization::canCreate()
                        ? [$this->getCreateControlAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('control_number')
                        ->label('Control Number')
                        ->sortable(),
                    TextColumn::make('name')
                        ->label('Control Name')
                        ->sortable()
                        ->wrap(),
                    TextColumn::make('control_type')
                        ->label('Control Type')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.control_types')[$state] ?? ucfirst(str_replace('_', ' ', (string) $state)))
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.control_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            ProductControl::STATUS_ACTIVE => 'success',
                            ProductControl::STATUS_ARCHIVED => 'gray',
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
                            ->where('control_number', 'like', $term)
                            ->orWhere('name', 'like', $term);
                    });
                })
                ->recordActions([
                    $this->getViewControlAction(),
                    $this->getEditControlAction(),
                    $this->getArchiveControlAction(),
                    $this->getRestoreControlAction(),
                ]),
        );
    }

    protected function configureGroupsTable(Table $table): Table
    {
        return ProductControlGroupTableConfiguration::applyListLayout(
            $table
                ->query(ProductControlGroup::query())
                ->defaultSort('group_number', 'asc')
                ->deferLoading()
                ->modelLabel('Control Group')
                ->pluralModelLabel('Control Groups')
                ->headerActions(
                    ProductControlAuthorization::canCreate()
                        ? [$this->getCreateGroupAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('group_number')
                        ->label('Group Number')
                        ->sortable(),
                    TextColumn::make('name')
                        ->label('Group Name')
                        ->sortable(),
                    TextColumn::make('controls_count')
                        ->label('Controls')
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.control_group_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            ProductControlGroup::STATUS_ACTIVE => 'success',
                            ProductControlGroup::STATUS_ARCHIVED => 'gray',
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
                            ->where('group_number', 'like', $term)
                            ->orWhere('name', 'like', $term)
                            ->orWhereHas('controls', fn (Builder $query): Builder => $query->where('name', 'like', $term));
                    });
                })
                ->recordActions([
                    $this->getViewGroupAction(),
                    $this->getEditGroupAction(),
                    $this->getArchiveGroupAction(),
                    $this->getRestoreGroupAction(),
                ]),
        );
    }

    protected function getCreateControlAction(): Action
    {
        return Action::make('createControl')
            ->label('Add Control')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Control')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => ProductControlForm::defaultState())
            ->schema(fn (Schema $schema): Schema => ProductControlForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistControl($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewControlAction(): Action
    {
        return Action::make('viewControl')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => ProductControlAuthorization::canView())
            ->modalHeading('View Control')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (ProductControl $record): array => ProductControlForm::fromModel($record))
            ->schema(fn (Schema $schema, ProductControl $record): Schema => ProductControlForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditControlAction(): Action
    {
        return Action::make('editControl')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (ProductControl $record): bool => ProductControlAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Control')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (ProductControl $record): array => ProductControlForm::fromModel($record))
            ->schema(fn (Schema $schema, ProductControl $record): Schema => ProductControlForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, ProductControl $record): void {
                $this->persistControl($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getArchiveControlAction(): Action
    {
        return Action::make('archiveControl')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Control')
            ->modalDescription('This control will be archived. Control number, name, and type will be preserved.')
            ->visible(fn (ProductControl $record): bool => ProductControlAuthorization::canArchive() && $record->isActive())
            ->action(function (ProductControl $record, ProductControlPersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Control archived')
                    ->send();
            });
    }

    protected function getRestoreControlAction(): Action
    {
        return Action::make('restoreControl')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Control')
            ->modalDescription('This control will be restored to active status.')
            ->visible(fn (ProductControl $record): bool => ProductControlAuthorization::canRestore() && $record->isArchived())
            ->action(function (ProductControl $record, ProductControlPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Control restored')
                    ->send();
            });
    }

    protected function getCreateGroupAction(): Action
    {
        return Action::make('createGroup')
            ->label('Add Group')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Control Group')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => ProductControlGroupForm::defaultState())
            ->schema(fn (Schema $schema): Schema => ProductControlGroupForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistGroup($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewGroupAction(): Action
    {
        return Action::make('viewGroup')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => ProductControlAuthorization::canView())
            ->modalHeading('View Control Group')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (ProductControlGroup $record): array => ProductControlGroupForm::fromModel($record))
            ->schema(fn (Schema $schema, ProductControlGroup $record): Schema => ProductControlGroupForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditGroupAction(): Action
    {
        return Action::make('editGroup')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (ProductControlGroup $record): bool => ProductControlAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Control Group')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (ProductControlGroup $record): array => ProductControlGroupForm::fromModel($record))
            ->schema(fn (Schema $schema, ProductControlGroup $record): Schema => ProductControlGroupForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, ProductControlGroup $record): void {
                $this->persistGroup($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getArchiveGroupAction(): Action
    {
        return Action::make('archiveGroup')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Control Group')
            ->modalDescription('This group will be archived. Group number, name, and control assignments will be preserved.')
            ->visible(fn (ProductControlGroup $record): bool => ProductControlAuthorization::canArchive() && $record->isActive())
            ->action(function (ProductControlGroup $record, ProductControlGroupPersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Control group archived')
                    ->send();
            });
    }

    protected function getRestoreGroupAction(): Action
    {
        return Action::make('restoreGroup')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Control Group')
            ->modalDescription('This group will be restored to active status.')
            ->visible(fn (ProductControlGroup $record): bool => ProductControlAuthorization::canRestore() && $record->isArchived())
            ->action(function (ProductControlGroup $record, ProductControlGroupPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Control group restored')
                    ->send();
            });
    }

    protected function persistControl(Schema $schema, bool $another, ?ProductControl $control = null): void
    {
        if ($control !== null && ! ProductControlAuthorization::canEdit()) {
            abort(403);
        }

        if ($control === null && ! ProductControlAuthorization::canCreate()) {
            abort(403);
        }

        $data = ProductControlForm::normalizeState($schema->getState());

        try {
            $persistence = app(ProductControlPersistenceService::class);

            if ($control !== null) {
                $persistence->update($control, $data);
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
            ->title('Control saved')
            ->send();

        if ($another) {
            if ($control !== null) {
                $this->unmountAction();
                $this->mountAction('createControl');

                return;
            }

            $schema->fill(ProductControlForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }

    protected function persistGroup(Schema $schema, bool $another, ?ProductControlGroup $group = null): void
    {
        if ($group !== null && ! ProductControlAuthorization::canEdit()) {
            abort(403);
        }

        if ($group === null && ! ProductControlAuthorization::canCreate()) {
            abort(403);
        }

        $data = ProductControlGroupForm::normalizeState($schema->getState());

        try {
            $persistence = app(ProductControlGroupPersistenceService::class);

            if ($group !== null) {
                $persistence->update($group, $data);
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
            ->title('Control group saved')
            ->send();

        if ($another) {
            if ($group !== null) {
                $this->unmountAction();
                $this->mountAction('createGroup');

                return;
            }

            $schema->fill(ProductControlGroupForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
