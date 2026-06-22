<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Schemas\UnitForm;
use App\Filament\ProductCatalog\Support\UnitTableConfiguration;
use App\Models\Unit;
use App\Services\ProductCatalog\UnitPersistenceService;
use App\Support\ProductCatalog\UnitAuthorization;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class Units extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/units';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'units';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return UnitTableConfiguration::applyListLayout(
            $table
                ->query(Unit::query())
                ->defaultSort('unit_number', 'asc')
                ->deferLoading()
                ->modelLabel('Unit')
                ->pluralModelLabel('Units')
                ->headerActions(
                    UnitAuthorization::canCreate()
                        ? [$this->getCreateUnitAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('unit_number')
                        ->label('Unit Number')
                        ->sortable(),
                    TextColumn::make('name_en')
                        ->label('English Name')
                        ->sortable(),
                    TextColumn::make('abbreviation_en')
                        ->label('Abbreviation')
                        ->sortable(),
                    TextColumn::make('name_ur')
                        ->label('Urdu Name')
                        ->placeholder('—')
                        ->sortable(),
                    TextColumn::make('unit_type')
                        ->label('Unit Type')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.unit_types')[$state] ?? ucfirst((string) $state))
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.unit_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            Unit::STATUS_ACTIVE => 'success',
                            Unit::STATUS_ARCHIVED => 'gray',
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
                            ->where('unit_number', 'like', $term)
                            ->orWhere('name_en', 'like', $term)
                            ->orWhere('name_ur', 'like', $term)
                            ->orWhere('abbreviation_en', 'like', $term)
                            ->orWhere('abbreviation_ur', 'like', $term);
                    });
                })
                ->recordActions([
                    $this->getViewUnitAction(),
                    $this->getEditUnitAction(),
                    $this->getArchiveUnitAction(),
                    $this->getRestoreUnitAction(),
                ]),
        );
    }

    protected function getCreateUnitAction(): Action
    {
        return Action::make('createUnit')
            ->label('Add Unit')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Unit')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => UnitForm::defaultState())
            ->schema(fn (Schema $schema): Schema => UnitForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistUnit($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewUnitAction(): Action
    {
        return Action::make('viewUnit')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => UnitAuthorization::canView())
            ->modalHeading('View Unit')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Unit $record): array => UnitForm::fromModel($record))
            ->schema(fn (Schema $schema, Unit $record): Schema => UnitForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditUnitAction(): Action
    {
        return Action::make('editUnit')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Unit $record): bool => UnitAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Unit')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Unit $record): array => UnitForm::fromModel($record))
            ->schema(fn (Schema $schema, Unit $record): Schema => UnitForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, Unit $record): void {
                $this->persistUnit($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getArchiveUnitAction(): Action
    {
        return Action::make('archiveUnit')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Unit')
            ->modalDescription('This unit will be archived. Unit number and all content will be preserved.')
            ->visible(fn (Unit $record): bool => UnitAuthorization::canArchive() && $record->isActive())
            ->action(function (Unit $record, UnitPersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Unit archived')
                    ->send();
            });
    }

    protected function getRestoreUnitAction(): Action
    {
        return Action::make('restoreUnit')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Unit')
            ->modalDescription('This unit will be restored to active status.')
            ->visible(fn (Unit $record): bool => UnitAuthorization::canRestore() && $record->isArchived())
            ->action(function (Unit $record, UnitPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Unit restored')
                    ->send();
            });
    }

    protected function persistUnit(Schema $schema, bool $another, ?Unit $unit = null): void
    {
        if ($unit !== null && ! UnitAuthorization::canEdit()) {
            abort(403);
        }

        if ($unit === null && ! UnitAuthorization::canCreate()) {
            abort(403);
        }

        $data = UnitForm::normalizeState($schema->getState());

        try {
            $persistence = app(UnitPersistenceService::class);

            if ($unit !== null) {
                $persistence->update($unit, $data);
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
            ->title('Unit saved')
            ->send();

        if ($another) {
            if ($unit !== null) {
                $this->unmountAction();
                $this->mountAction('createUnit');

                return;
            }

            $schema->fill(UnitForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
