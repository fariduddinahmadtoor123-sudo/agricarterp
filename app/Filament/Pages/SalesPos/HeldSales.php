<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\SalesPos\PosSale;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Support\SalesPos\PosSaleRepository;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HeldSales extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'sales-pos/held-sales';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'held-sales';
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
            ->records(fn (): Collection => $this->getHeldRecords())
            ->searchable()
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50])
            ->modelLabel('Held Sale')
            ->pluralModelLabel('Held Sales')
            ->recordTitleAttribute('sale_number')
            ->emptyStateHeading('No held sales')
            ->emptyStateDescription('When you place a POS sale on hold, it will appear here so you can resume it later.')
            ->columns([
                TextColumn::make('sale_number')
                    ->label('Sale No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('held_label')
                    ->label('Hold Label')
                    ->limit(32)
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->limit(28)
                    ->searchable(),
                TextColumn::make('store_name')
                    ->label('Store')
                    ->limit(22)
                    ->toggleable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, array $record): string => PosSaleLineBuilder::formatAmount(
                        PosSaleLineBuilder::subtotal($record['rows'] ?? [])
                    ) ?: '—'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                $this->getResumeSaleAction(),
                $this->getDeleteHeldSaleAction(),
            ]);
    }

    protected function getHeldRecords(): Collection
    {
        $records = collect(app(PosSaleRepository::class)->held())
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
                    || str_contains(Str::lower((string) ($record['held_label'] ?? '')), $needle);
            });
        }

        return $records->sortByDesc('updated_at')->values();
    }

    protected function getResumeSaleAction(): Action
    {
        return Action::make('resumeSale')
            ->label('Resume')
            ->icon(Heroicon::OutlinedPlay)
            ->color('success')
            ->url(fn (array $record): string => PosSaleWorksheet::getUrl(['saleId' => $record['id']]));
    }

    protected function getDeleteHeldSaleAction(): Action
    {
        return Action::make('deleteHeldSale')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Held Sale')
            ->modalDescription('This held sale will be permanently removed.')
            ->action(function (array $record): void {
                if (($record['status'] ?? '') !== PosSale::STATUS_HELD) {
                    return;
                }

                app(PosSaleRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('Held sale deleted')
                    ->send();
            });
    }
}
