<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\SalesPos\SalesReturn;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\SalesPos\PosSaleReturnLineBuilder;
use App\Support\SalesPos\SalesReturnRepository;
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
use Illuminate\Validation\ValidationException;

class SalesReturns extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'sales-pos/sales-returns';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'sales-returns';
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
            ->records(fn (): Collection => $this->getReturnRecords())
            ->searchable()
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50])
            ->modelLabel('Sales Return')
            ->pluralModelLabel('Sales Returns')
            ->recordTitleAttribute('return_number')
            ->headerActions([
                $this->getOpenByReturnNumberAction(),
                $this->getCreateReturnAction(),
            ])
            ->columns([
                TextColumn::make('return_number')
                    ->label('Return No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('sale_number')
                    ->label('Sale No.')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->limit(24),
                TextColumn::make('return_total')
                    ->label('Return Total')
                    ->formatStateUsing(fn ($state, array $record): string => PosSaleReturnLineBuilder::subtotal($record['rows'] ?? []) > 0
                        ? PosSaleLineBuilder::formatAmount(PosSaleReturnLineBuilder::subtotal($record['rows'] ?? []))
                        : ((string) ($record['return_total'] ?? '—'))),
                TextColumn::make('refund_method')
                    ->label('Refund')
                    ->formatStateUsing(fn (?string $state): string => config('sales-pos.return_refund_methods')[$state] ?? ucfirst((string) $state)),
                TextColumn::make('refund_status')
                    ->label('Settlement')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('sales-pos.return_refund_statuses')[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'paid', 'credited' => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('sales-pos.return_statuses')[$state] ?? ucfirst((string) $state)),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                $this->getOpenReturnAction(),
                $this->getDeleteReturnAction(),
            ]);
    }

    protected function getReturnRecords(): Collection
    {
        $records = collect(app(SalesReturnRepository::class)->all());

        $search = trim((string) $this->getTableSearch());

        if ($search !== '') {
            $needle = Str::lower($search);

            $records = $records->filter(function (array $record) use ($needle): bool {
                return str_contains(Str::lower((string) $record['return_number']), $needle)
                    || str_contains(Str::lower((string) ($record['sale_number'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['customer_name'] ?? '')), $needle);
            });
        }

        return $records->sortByDesc('updated_at')->values();
    }

    protected function getOpenByReturnNumberAction(): Action
    {
        return Action::make('openByReturnNumber')
            ->label('Open Return')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->color('gray')
            ->modalHeading('Open Sales Return')
            ->modalDescription('Enter the full return number, for example SR-20260621-0001.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('return_number')
                    ->label('Return Number')
                    ->placeholder('SR-YYYYMMDD-####')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(SalesReturnRepository::class)->findByReturnNumber((string) ($data['return_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()->warning()->title('Return not found')->send();

                    return;
                }

                $this->redirect(SalesReturnWorksheet::getUrl(['returnId' => $sheet['id']]));
            });
    }

    protected function getCreateReturnAction(): Action
    {
        return Action::make('createReturn')
            ->label('New Return')
            ->icon(Heroicon::OutlinedPlus)
            ->action(function (): void {
                $sheet = app(SalesReturnRepository::class)->create();

                $this->redirect(SalesReturnWorksheet::getUrl(['returnId' => $sheet['id']]));
            });
    }

    protected function getOpenReturnAction(): Action
    {
        return Action::make('openReturn')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (array $record): string => SalesReturnWorksheet::getUrl(['returnId' => $record['id']]));
    }

    protected function getDeleteReturnAction(): Action
    {
        return Action::make('deleteReturn')
            ->label('Discard')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (array $record): bool => ($record['status'] ?? '') !== SalesReturn::STATUS_COMPLETED)
            ->requiresConfirmation()
            ->action(function (array $record): void {
                app(SalesReturnRepository::class)->delete((string) $record['id']);

                Notification::make()->success()->title('Return deleted')->send();
            });
    }
}
