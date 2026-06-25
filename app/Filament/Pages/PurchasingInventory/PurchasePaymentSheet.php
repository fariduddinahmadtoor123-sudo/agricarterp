<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\PurchasingInventory\Support\PurchasePaymentSheetTableConfiguration;
use App\Services\PurchasingInventory\PurchasePaymentSheetBuilder;
use App\Support\PurchasingInventory\PurchasePaymentSheetRepository;
use App\Support\PurchasingInventory\PurchasingInventoryAuthorization;
use Filament\Actions\Action;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PurchasePaymentSheet extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'purchasing-inventory/purchase-payment-sheet';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchase-payment-sheet';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return PurchasePaymentSheetTableConfiguration::applyListLayout(
            $table
                ->records(fn (): Collection => $this->getSheetRecords())
                ->searchable()
                ->defaultSort('updated_at', 'desc')
                ->paginated([10, 25, 50])
                ->modelLabel('Payment Sheet')
                ->pluralModelLabel('Purchase Payment Sheets')
                ->recordTitleAttribute('sheet_number')
                ->headerActions([
                    $this->getOpenBySheetNumberAction(),
                    $this->getCreateSheetAction(),
                ])
                ->columns([
                    TextColumn::make('sheet_number')
                        ->label('Sheet No.')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('sheet_date')
                        ->label('Date')
                        ->date()
                        ->sortable(),
                    TextColumn::make('purchaser_name')
                        ->label('Purchaser')
                        ->limit(24)
                        ->toggleable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('purchasing-inventory.sheet_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            'saved' => 'success',
                            'draft' => 'warning',
                            default => 'gray',
                        })
                        ->sortable(),
                    TextColumn::make('vendor_count')
                        ->label('Bills')
                        ->sortable(),
                    TextColumn::make('vendor_total')
                        ->label('Vendor Total')
                        ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                        ->sortable(),
                    TextColumn::make('source_total')
                        ->label('Sources Total')
                        ->formatStateUsing(fn ($state): string => number_format((float) $state, 2))
                        ->toggleable(),
                    TextColumn::make('notes')
                        ->label('Notes')
                        ->limit(40)
                        ->wrap()
                        ->toggleable(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getOpenSheetAction(),
                    $this->getDeleteSheetAction(),
                ]),
        );
    }

    protected function getSheetRecords(): Collection
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        $records = collect(app(PurchasePaymentSheetRepository::class)->all())
            ->map(function (array $sheet) use ($builder): array {
                $vendorLines = $sheet['vendor_lines'] ?? [];
                $paymentSources = $sheet['payment_sources'] ?? [];

                $sheet['vendor_count'] = $builder->filledVendorCount($vendorLines);
                $sheet['vendor_total'] = $builder->vendorPaymentsTotal($vendorLines);
                $sheet['source_total'] = $builder->paymentSourcesTotal($paymentSources);

                return $sheet;
            });

        $status = data_get($this->tableFilters, 'status.value')
            ?? data_get($this->tableFilters, 'status');

        if (is_array($status)) {
            $status = $status['value'] ?? null;
        }

        if (filled($status)) {
            $records = $records->where('status', $status);
        }

        $search = trim((string) $this->getTableSearch());

        if ($search !== '') {
            $needle = Str::lower($search);

            $records = $records->filter(function (array $record) use ($needle): bool {
                return str_contains(Str::lower((string) $record['sheet_number']), $needle)
                    || str_contains(Str::lower((string) ($record['title'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['purchaser_name'] ?? '')), $needle)
                    || str_contains(Str::lower((string) $record['notes']), $needle);
            });
        }

        return $records->sortByDesc('updated_at')->values();
    }

    protected function getOpenBySheetNumberAction(): Action
    {
        return Action::make('openBySheetNumber')
            ->label('Open Sheet')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->color('gray')
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canView())
            ->modalHeading('Open Purchase Payment Sheet')
            ->modalDescription('Enter the full sheet number, for example PPS-20260621-ABCD.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('sheet_number')
                    ->label('Sheet Number')
                    ->placeholder('PPS-YYYYMMDD-XXXX')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(PurchasePaymentSheetRepository::class)->findBySheetNumber((string) ($data['sheet_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()
                        ->warning()
                        ->title('Sheet not found')
                        ->body('Check the sheet number and try again.')
                        ->send();

                    return;
                }

                $this->redirect(PurchasePaymentSheetWorksheet::getUrl(['sheetId' => $sheet['id']]));
            });
    }

    protected function getCreateSheetAction(): Action
    {
        return Action::make('createSheet')
            ->label('New Payment Sheet')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canCreate())
            ->action(function (): void {
                $sheet = app(PurchasePaymentSheetRepository::class)->create();

                $this->redirect(PurchasePaymentSheetWorksheet::getUrl(['sheetId' => $sheet['id']]));
            });
    }

    protected function getOpenSheetAction(): Action
    {
        return Action::make('openSheet')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canView())
            ->url(fn (array $record): string => PurchasePaymentSheetWorksheet::getUrl(['sheetId' => $record['id']]));
    }

    protected function getDeleteSheetAction(): Action
    {
        return Action::make('deleteSheet')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canDelete())
            ->requiresConfirmation()
            ->modalHeading('Delete Payment Sheet')
            ->modalDescription('This preview sheet will be removed from session storage.')
            ->action(function (array $record): void {
                app(PurchasePaymentSheetRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('Payment sheet deleted')
                    ->send();
            });
    }
}
