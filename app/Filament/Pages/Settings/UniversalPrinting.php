<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Settings\Schemas\PrintingSettingForm;
use App\Filament\Settings\Support\PrintingSettingTableConfiguration;
use App\Models\PrintingSetting;
use App\Services\Settings\PrintingSettingPersistenceService;
use App\Services\Settings\PrintingSettingResolver;
use App\Support\Settings\PrintingSettingAuthorization;
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
use Illuminate\Validation\ValidationException;

class UniversalPrinting extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/universal-printing';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'universal-printing';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $resolver = app(PrintingSettingResolver::class);

        return PrintingSettingTableConfiguration::applyListLayout(
            $table
                ->query(PrintingSetting::query())
                ->defaultSort('id', 'asc')
                ->modelLabel('Printing Profile')
                ->pluralModelLabel('Universal Printing')
                ->emptyStateHeading('No printing settings yet')
                ->emptyStateDescription('Configure paper sizes, barcode labels, and POS receipt rolls for the whole ERP.')
                ->headerActions(
                    PrintingSettingAuthorization::canCreate() && ! PrintingSetting::query()->exists()
                        ? [$this->getCreatePrintingSettingAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('default_purchase_invoice_paper')
                        ->label('Invoice Paper')
                        ->formatStateUsing(fn (?string $state): string => $resolver->documentPaperOptions()[$state] ?? strtoupper((string) $state))
                        ->badge(),
                    TextColumn::make('price_tag_label_preset')
                        ->label('Barcode Label')
                        ->formatStateUsing(fn (?string $state): string => $resolver->labelPresetOptions()[$state] ?? (string) $state),
                    TextColumn::make('price_tag_width_mm')
                        ->label('Label Size')
                        ->formatStateUsing(fn (PrintingSetting $record): string => number_format((float) $record->price_tag_width_mm, 0)
                            . '×'
                            . number_format((float) $record->price_tag_height_mm, 0)
                            . ' mm'),
                    TextColumn::make('pos_receipt_profile')
                        ->label('POS Roll')
                        ->formatStateUsing(fn (?string $state): string => $resolver->thermalReceiptOptions()[$state] ?? (string) $state),
                    TextColumn::make('barcode_printer_note')
                        ->label('Printer Note')
                        ->limit(30)
                        ->placeholder('—'),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getViewPrintingSettingAction(),
                    $this->getEditPrintingSettingAction(),
                ]),
        );
    }

    protected function getCreatePrintingSettingAction(): Action
    {
        return Action::make('createPrintingSetting')
            ->label('Add Printing Settings')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Universal Printing')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (): array => PrintingSettingForm::defaultState())
            ->schema(fn (Schema $schema): Schema => PrintingSettingForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistPrintingSetting($schema);
            });
    }

    protected function getViewPrintingSettingAction(): Action
    {
        return Action::make('viewPrintingSetting')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => PrintingSettingAuthorization::canView())
            ->modalHeading('Universal Printing')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (PrintingSetting $record): array => PrintingSettingForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => PrintingSettingForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditPrintingSettingAction(): Action
    {
        return Action::make('editPrintingSetting')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => PrintingSettingAuthorization::canEdit())
            ->modalHeading('Edit Universal Printing')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (PrintingSetting $record): array => PrintingSettingForm::fromModel($record))
            ->schema(fn (Schema $schema, PrintingSetting $record): Schema => PrintingSettingForm::configure($schema, record: $record))
            ->action(function (Schema $schema, PrintingSetting $record): void {
                $this->persistPrintingSetting($schema, $record);
            });
    }

    protected function persistPrintingSetting(Schema $schema, ?PrintingSetting $setting = null): void
    {
        if ($setting !== null && ! PrintingSettingAuthorization::canEdit()) {
            abort(403);
        }

        if ($setting === null && ! PrintingSettingAuthorization::canCreate()) {
            abort(403);
        }

        $data = $schema->getState();

        try {
            $persistence = app(PrintingSettingPersistenceService::class);

            if ($setting !== null) {
                $persistence->update($setting, $data);
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
            ->title('Printing settings saved')
            ->send();

        $this->unmountAction();
    }
}
