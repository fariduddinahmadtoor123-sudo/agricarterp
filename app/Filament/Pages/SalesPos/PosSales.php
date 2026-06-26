<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Support\SalesPos\PosSaleRepository;
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

class PosSales extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'sales-pos/pos-sales';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'pos-sales';
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
            ->records(fn (): Collection => $this->getSaleRecords())
            ->searchable()
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50])
            ->modelLabel('POS Sale')
            ->pluralModelLabel('POS Sales')
            ->recordTitleAttribute('sale_number')
            ->headerActions([
                $this->getOpenBySaleNumberAction(),
                $this->getCreateSaleAction(),
            ])
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Sale No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->limit(28)
                    ->toggleable(),
                TextColumn::make('store_name')
                    ->label('Store')
                    ->limit(22)
                    ->toggleable(),
                TextColumn::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn (?string $state): string => config('sales-pos.payment_methods')[$state] ?? ucfirst((string) $state))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('sales-pos.statuses')[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'held' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('held_label')
                    ->label('Hold Label')
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, array $record): string => PosSaleLineBuilder::formatAmount(
                        PosSaleLineBuilder::subtotal($record['rows'] ?? [])
                    ) ?: '—'),
                TextColumn::make('net_total')
                    ->label('Net')
                    ->formatStateUsing(fn ($state, array $record): string => ($record['status'] ?? '') === 'completed'
                        && PosSaleLineBuilder::numeric($record['return_total'] ?? '') > 0
                        ? (PosSaleLineBuilder::formatAmount(PosSaleLineBuilder::numeric($record['net_total'] ?? '')) ?: '—')
                        : '—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                $this->getOpenSaleAction(),
                $this->getDeleteSaleAction(),
            ]);
    }

    protected function getSaleRecords(): Collection
    {
        $records = collect(app(PosSaleRepository::class)->all())
            ->reject(fn (array $sheet): bool => ($sheet['status'] ?? '') === 'held')
            ->map(function (array $sheet): array {
                $sheet['lines_count'] = count($sheet['rows'] ?? []);

                return $sheet;
            });

        $search = trim((string) $this->getTableSearch());

        if ($search !== '') {
            $needle = Str::lower($search);

            $records = $records->filter(function (array $record) use ($needle): bool {
                return str_contains(Str::lower((string) $record['sale_number']), $needle)
                    || str_contains(Str::lower((string) ($record['customer_name'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['held_label'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['notes'] ?? '')), $needle);
            });
        }

        return $records->sortByDesc('updated_at')->values();
    }

    protected function getOpenBySaleNumberAction(): Action
    {
        return Action::make('openBySaleNumber')
            ->label('Open Sale')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->color('gray')
            ->modalHeading('Open POS Sale')
            ->modalDescription('Enter the full sale number, for example PS-20260621-0001.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('sale_number')
                    ->label('Sale Number')
                    ->placeholder('PS-YYYYMMDD-####')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(PosSaleRepository::class)->findBySaleNumber((string) ($data['sale_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()
                        ->warning()
                        ->title('POS sale not found')
                        ->send();

                    return;
                }

                $this->redirect(PosSaleWorksheet::getUrl(['saleId' => $sheet['id']]));
            });
    }

    protected function getCreateSaleAction(): Action
    {
        return Action::make('createSale')
            ->label('New Sale')
            ->icon(Heroicon::OutlinedPlus)
            ->action(function (): void {
                $sheet = app(PosSaleRepository::class)->create();

                $this->redirect(PosSaleWorksheet::getUrl(['saleId' => $sheet['id']]));
            });
    }

    protected function getOpenSaleAction(): Action
    {
        return Action::make('openSale')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (array $record): string => PosSaleWorksheet::getUrl(['saleId' => $record['id']]));
    }

    protected function getDeleteSaleAction(): Action
    {
        return Action::make('deleteSale')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (array $record): bool => ($record['status'] ?? '') !== 'completed')
            ->requiresConfirmation()
            ->modalHeading('Delete POS Sale')
            ->modalDescription('This draft or held sale will be permanently removed.')
            ->action(function (array $record): void {
                app(PosSaleRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('POS sale deleted')
                    ->send();
            });
    }
}
