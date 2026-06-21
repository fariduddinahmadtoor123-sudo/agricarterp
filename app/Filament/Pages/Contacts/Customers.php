<?php

namespace App\Filament\Pages\Contacts;

use App\Filament\Contacts\Schemas\CustomerForm;
use App\Filament\Contacts\Support\CustomerTableConfiguration;
use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\Customer;
use App\Services\Contacts\CustomerPersistenceService;
use App\Support\Contacts\CustomerAuthorization;
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

class Customers extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'contacts/customers';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, array<string, mixed>>
     *
     * @deprecated Kept for stale Livewire browser snapshot compatibility.
     */
    public array $customers = [];

    public static function moduleKey(): string
    {
        return 'contacts';
    }

    public static function submenuKey(): string
    {
        return 'customers';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return CustomerTableConfiguration::applyListLayout(
            $table
                ->query(Customer::query())
                ->defaultSort('created_at', 'desc')
                ->deferLoading()
                ->modelLabel('Customer')
                ->pluralModelLabel('Customers')
                ->headerActions(
                    CustomerAuthorization::canCreate()
                        ? [$this->getCreateCustomerAction()]
                        : [],
                )
                ->columns([
                TextColumn::make('customer_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mobile_number')
                    ->label('Mobile')
                    ->searchable(),
                TextColumn::make('country')
                    ->label('Country')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('City')
                    ->searchable(),
            ])
            ->recordActions([
                $this->getViewCustomerAction(),
                $this->getEditCustomerAction(),
                $this->getRestoreCustomerAction(),
                $this->getDeleteCustomerAction(),
            ]),
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getCreateCustomerAction(): Action
    {
        return Action::make('createCustomer')
            ->label('Add Customer')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Customer')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => CustomerForm::defaultState())
            ->schema(fn (Schema $schema): Schema => CustomerForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistCustomer($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewCustomerAction(): Action
    {
        return Action::make('viewCustomer')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => CustomerAuthorization::canView())
            ->modalHeading('View Customer')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Customer $record): array => CustomerForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => CustomerForm::configure($schema, readOnly: true));
    }

    protected function getEditCustomerAction(): Action
    {
        return Action::make('editCustomer')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Customer $record): bool => CustomerAuthorization::canEdit() && ! $record->trashed())
            ->modalHeading('Edit Customer')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Customer $record): array => CustomerForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => CustomerForm::configure($schema))
            ->action(function (array $arguments, Schema $schema, Customer $record): void {
                $this->persistCustomer($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getRestoreCustomerAction(): Action
    {
        return Action::make('restoreCustomer')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Customer')
            ->modalDescription('This customer will be restored with their original customer code and reserved mobile numbers.')
            ->visible(fn (Customer $record): bool => CustomerAuthorization::canRestore() && $record->trashed())
            ->action(function (Customer $record, CustomerPersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Customer restored')
                    ->send();
            });
    }

    protected function getDeleteCustomerAction(): DeleteAction
    {
        return DeleteAction::make('deleteCustomer')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->visible(fn (Customer $record): bool => CustomerAuthorization::canDelete() && ! $record->trashed())
            ->modalHeading('Delete Customer')
            ->modalDescription('This customer will be archived. Their customer code and mobile numbers will remain reserved in the system.')
            ->successNotificationTitle('Customer deleted')
            ->action(function (Customer $record, CustomerPersistenceService $persistence): void {
                $persistence->delete($record);
            });
    }

    protected function persistCustomer(Schema $schema, bool $another, ?Customer $customer = null): void
    {
        if ($customer !== null && ! CustomerAuthorization::canEdit()) {
            abort(403);
        }

        if ($customer === null && ! CustomerAuthorization::canCreate()) {
            abort(403);
        }

        $data = CustomerForm::normalizeState($schema->getState());

        try {
            $persistence = app(CustomerPersistenceService::class);

            if ($customer !== null) {
                $persistence->update($customer, $data);
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
            ->title('Customer saved')
            ->send();

        if ($another) {
            if ($customer !== null) {
                $this->unmountAction();
                $this->mountAction('createCustomer');

                return;
            }

            $schema->fill(CustomerForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
