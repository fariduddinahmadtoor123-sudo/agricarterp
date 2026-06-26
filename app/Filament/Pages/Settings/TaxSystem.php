<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Settings\Schemas\TaxForm;
use App\Models\Tax;
use App\Services\Settings\TaxPersistenceService;
use App\Support\Settings\TaxAuthorization;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class TaxSystem extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/tax-system';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'tax-system';
    }

    public static function canAccess(): bool
    {
        return TaxAuthorization::canView();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'agricart-settings-taxes-list'])
            ->query(Tax::query())
            ->defaultSort('name')
            ->modelLabel('Tax')
            ->pluralModelLabel('Taxes')
            ->emptyStateHeading('No taxes yet')
            ->emptyStateDescription('Create tax definitions such as GST, Sales Tax, Withholding Tax, or Import Duty.')
            ->headerActions(
                TaxAuthorization::canCreate()
                    ? [$this->getCreateTaxAction()]
                    : [],
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Tax Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => config('tax.types.' . $state, ucfirst($state)))
                    ->color('gray'),
                TextColumn::make('rate_value')
                    ->label('Rate / Value')
                    ->formatStateUsing(fn (Tax $record): string => $record->formattedRate())
                    ->sortable(),
                TextColumn::make('apply_on')
                    ->label('Apply On')
                    ->badge()
                    ->formatStateUsing(fn (Tax $record): string => implode(', ', $record->applyOnLabels()))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => config('tax.statuses.' . $state, ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        Tax::STATUS_ACTIVE => 'success',
                        Tax::STATUS_INACTIVE => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(config('tax.statuses', [])),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(config('tax.types', [])),
            ])
            ->recordActions([
                $this->getViewTaxAction(),
                $this->getEditTaxAction(),
                $this->getDeleteTaxAction(),
            ]);
    }

    protected function getCreateTaxAction(): Action
    {
        return Action::make('createTax')
            ->label('Add Tax')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Tax')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (): array => TaxForm::defaultState())
            ->schema(fn (Schema $schema): Schema => TaxForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistTax($schema);
            });
    }

    protected function getViewTaxAction(): Action
    {
        return Action::make('viewTax')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => TaxAuthorization::canView())
            ->modalHeading('View Tax')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Tax $record): array => TaxForm::fromModel($record))
            ->schema(fn (Schema $schema, Tax $record): Schema => TaxForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditTaxAction(): Action
    {
        return Action::make('editTax')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => TaxAuthorization::canEdit())
            ->modalHeading('Edit Tax')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (Tax $record): array => TaxForm::fromModel($record))
            ->schema(fn (Schema $schema, Tax $record): Schema => TaxForm::configure($schema, record: $record))
            ->action(function (Schema $schema, Tax $record): void {
                $this->persistTax($schema, $record);
            });
    }

    protected function getDeleteTaxAction(): DeleteAction
    {
        return DeleteAction::make('deleteTax')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->visible(fn (): bool => TaxAuthorization::canDelete())
            ->modalHeading('Delete Tax')
            ->successNotificationTitle('Tax deleted')
            ->action(function (Tax $record, TaxPersistenceService $persistence): void {
                $persistence->delete($record);
            });
    }

    protected function persistTax(Schema $schema, ?Tax $tax = null): void
    {
        if ($tax !== null && ! TaxAuthorization::canEdit()) {
            abort(403);
        }

        if ($tax === null && ! TaxAuthorization::canCreate()) {
            abort(403);
        }

        $data = TaxForm::normalizeState($schema->getState());

        try {
            $persistence = app(TaxPersistenceService::class);

            if ($tax !== null) {
                $persistence->update($tax, $data);
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
            ->title('Tax saved')
            ->send();

        $this->unmountAction();
    }
}
