<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\PurchasingInventory\Support\PurchaseTableConfiguration;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
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

class Purchases extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'purchasing-inventory/purchases';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchases';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return PurchaseTableConfiguration::applyListLayout(
            $table
                ->records(fn (): Collection => $this->getPurchaseRecords())
                ->searchable()
                ->defaultSort('updated_at', 'desc')
                ->paginated([10, 25, 50])
                ->modelLabel('Purchase Invoice')
                ->pluralModelLabel('Purchases')
                ->recordTitleAttribute('purchase_number')
                ->headerActions([
                    $this->getOpenByPurchaseNumberAction(),
                    $this->getCreatePurchaseAction(),
                ])
                ->columns([
                    TextColumn::make('purchase_number')
                        ->label('Invoice No.')
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('supplier_name')
                        ->label('Supplier')
                        ->limit(28)
                        ->toggleable(),
                    TextColumn::make('store_name')
                        ->label('Store')
                        ->limit(22)
                        ->toggleable(),
                    TextColumn::make('invoice_payment_status')
                        ->label('Payment')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('purchasing-inventory.purchase_invoice_payment_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            'paid' => 'success',
                            'partial' => 'warning',
                            default => 'gray',
                        }),
                    TextColumn::make('goods_receipt_status')
                        ->label('Goods')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('purchasing-inventory.purchase_goods_receipt_statuses')[$state] ?? ucfirst((string) $state))
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
                    TextColumn::make('invoice_total')
                        ->label('Total')
                        ->formatStateUsing(fn ($state, array $record): string => PurchaseLineBuilder::formatAmount(
                            PurchaseLineBuilder::invoiceTotal($record['rows'] ?? [])
                        ) ?: '—'),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getOpenPurchaseAction(),
                    $this->getDeletePurchaseAction(),
                ]),
        );
    }

    protected function getPurchaseRecords(): Collection
    {
        $records = collect(app(PurchaseSheetRepository::class)->all())
            ->map(function (array $sheet): array {
                $sheet['lines_count'] = count($sheet['rows'] ?? []);
                $sheet['invoice_total'] = PurchaseLineBuilder::invoiceTotal($sheet['rows'] ?? []);

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

        $paymentStatus = data_get($this->tableFilters, 'invoice_payment_status.value')
            ?? data_get($this->tableFilters, 'invoice_payment_status');

        if (is_array($paymentStatus)) {
            $paymentStatus = $paymentStatus['value'] ?? null;
        }

        if (filled($paymentStatus)) {
            $records = $records->where('invoice_payment_status', $paymentStatus);
        }

        $search = trim((string) $this->getTableSearch());

        if ($search !== '') {
            $needle = Str::lower($search);

            $records = $records->filter(function (array $record) use ($needle): bool {
                return str_contains(Str::lower((string) $record['purchase_number']), $needle)
                    || str_contains(Str::lower((string) ($record['title'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['supplier_name'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['notes'] ?? '')), $needle);
            });
        }

        return $records->sortByDesc('updated_at')->values();
    }

    protected function getOpenByPurchaseNumberAction(): Action
    {
        return Action::make('openByPurchaseNumber')
            ->label('Open Invoice')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->color('gray')
            ->modalHeading('Open Purchase Invoice')
            ->modalDescription('Enter the full invoice number, for example PU-20260623-ABCD.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('purchase_number')
                    ->label('Invoice Number')
                    ->placeholder('PU-YYYYMMDD-XXXX')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(PurchaseSheetRepository::class)->findByPurchaseNumber((string) ($data['purchase_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()
                        ->warning()
                        ->title('Purchase invoice not found')
                        ->send();

                    return;
                }

                $this->redirect(PurchaseWorksheet::getUrl(['purchaseId' => $sheet['id']]));
            });
    }

    protected function getCreatePurchaseAction(): Action
    {
        return Action::make('createPurchase')
            ->label('New Purchase')
            ->icon(Heroicon::OutlinedPlus)
            ->action(function (): void {
                $sheet = app(PurchaseSheetRepository::class)->create();

                $this->redirect(PurchaseWorksheet::getUrl(['purchaseId' => $sheet['id']]));
            });
    }

    protected function getOpenPurchaseAction(): Action
    {
        return Action::make('openPurchase')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (array $record): string => PurchaseWorksheet::getUrl(['purchaseId' => $record['id']]));
    }

    protected function getDeletePurchaseAction(): Action
    {
        return Action::make('deletePurchase')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Purchase Invoice')
            ->modalDescription('This preview invoice will be removed from session storage.')
            ->action(function (array $record): void {
                app(PurchaseSheetRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('Purchase invoice deleted')
                    ->send();
            });
    }
}
