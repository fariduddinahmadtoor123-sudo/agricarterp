<?php

namespace App\Filament\Pages\Contacts;

use App\Filament\Contacts\Schemas\SupplierForm;
use App\Filament\Contacts\Support\SupplierTableConfiguration;
use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Supplier;
use App\Services\Contacts\SupplierPersistenceService;
use App\Support\Contacts\SupplierAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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

class Suppliers extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'contacts/suppliers';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, array<string, mixed>>
     *
     * @deprecated Suppliers are persisted in the database. This property remains so
     *             stale Livewire browser snapshots can hydrate without error.
     */
    public array $suppliers = [];

    public static function moduleKey(): string
    {
        return 'contacts';
    }

    public static function submenuKey(): string
    {
        return 'suppliers';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return SupplierTableConfiguration::applyListLayout(
            $table
                ->query(Supplier::query())
                ->defaultSort('created_at', 'desc')
                ->deferLoading()
                ->modelLabel('Supplier')
                ->pluralModelLabel('Suppliers')
                ->headerActions(
                    SupplierAuthorization::canCreate()
                        ? [$this->getCreateSupplierAction()]
                        : [],
                )
                ->columns([
                TextColumn::make('supplier_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business_name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label('Contact Name')
                    ->searchable(),
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('contacts.supplier_statuses')[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        Supplier::STATUS_ACTIVE => 'success',
                        Supplier::STATUS_INACTIVE => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('country')
                    ->label('Country')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
                TextColumn::make('supplier_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        'local' => 'Local Supplier',
                        'importer' => 'Importer',
                        'manufacturer' => 'Manufacturer',
                        'distributor' => 'Distributor',
                        default => $state,
                    }),
            ])
            ->recordActions([
                $this->getViewSupplierAction(),
                $this->getEditSupplierAction(),
                $this->getSetInactiveSupplierAction(),
                $this->getRestoreSupplierAction(),
                $this->getDeleteSupplierAction(),
            ]),
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getCreateSupplierAction(): Action
    {
        return Action::make('createSupplier')
            ->label('Add Supplier')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Supplier')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => SupplierForm::defaultState())
            ->schema(fn (Schema $schema): Schema => SupplierForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistSupplier($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewSupplierAction(): Action
    {
        return Action::make('viewSupplier')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => SupplierAuthorization::canView())
            ->modalHeading('View Supplier')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Supplier $record): array => SupplierForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => SupplierForm::configure($schema, readOnly: true));
    }

    protected function getEditSupplierAction(): Action
    {
        return Action::make('editSupplier')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Supplier $record): bool => SupplierAuthorization::canEdit() && ! $record->trashed())
            ->modalHeading('Edit Supplier')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Supplier $record): array => SupplierForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => SupplierForm::configure($schema))
            ->action(function (array $arguments, Schema $schema, Supplier $record): void {
                $this->persistSupplier($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getSetInactiveSupplierAction(): Action
    {
        return Action::make('setInactiveSupplier')
            ->label('Set Inactive')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Set Supplier Inactive')
            ->modalDescription('Inactive suppliers remain in history and reports but will not appear in future purchase workflows.')
            ->visible(fn (Supplier $record): bool => SupplierAuthorization::canInactivate()
                && ! $record->trashed()
                && $record->isActive())
            ->action(function (Supplier $record, SupplierPersistenceService $persistence): void {
                $persistence->setInactive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Supplier set to inactive')
                    ->send();
            });
    }

    protected function getRestoreSupplierAction(): Action
    {
        return Action::make('restoreSupplier')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Supplier')
            ->modalDescription('This supplier will be restored with their original supplier code and reserved mobile numbers.')
            ->visible(fn (Supplier $record): bool => SupplierAuthorization::canRestore() && $record->trashed())
            ->action(function (Supplier $record, SupplierPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Supplier restored')
                    ->send();
            });
    }

    protected function getDeleteSupplierAction(): DeleteAction
    {
        return DeleteAction::make('deleteSupplier')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->visible(fn (Supplier $record): bool => SupplierAuthorization::canDelete() && ! $record->trashed())
            ->modalHeading('Delete Supplier')
            ->modalDescription('This supplier will be archived. Their supplier code and mobile numbers will remain reserved in the system.')
            ->successNotificationTitle('Supplier deleted')
            ->action(function (Supplier $record, SupplierPersistenceService $persistence): void {
                $persistence->delete($record);
            });
    }

    protected function persistSupplier(Schema $schema, bool $another, ?Supplier $supplier = null): void
    {
        if ($supplier !== null && ! SupplierAuthorization::canEdit()) {
            abort(403);
        }

        if ($supplier === null && ! SupplierAuthorization::canCreate()) {
            abort(403);
        }

        $data = SupplierForm::normalizeState($schema->getState());

        try {
            $persistence = app(SupplierPersistenceService::class);

            if ($supplier !== null) {
                $persistence->update($supplier, $data);
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
            ->title('Supplier saved')
            ->send();

        if ($another) {
            if ($supplier !== null) {
                $this->unmountAction();
                $this->mountAction('createSupplier');

                return;
            }

            $schema->fill(SupplierForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
