<?php

namespace App\Filament\Pages\SalesPos;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Support\SalesPos\SalesQuotationRepository;
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

class SalesQuotations extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'sales-pos/sales-quotations';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'sales-pos';
    }

    public static function submenuKey(): string
    {
        return 'sales-quotations';
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
            ->records(fn (): Collection => $this->getQuotationRecords())
            ->searchable()
            ->defaultSort('updated_at', 'desc')
            ->paginated([10, 25, 50])
            ->modelLabel('Sales Quotation')
            ->pluralModelLabel('Sales Quotations')
            ->recordTitleAttribute('quotation_number')
            ->headerActions([
                $this->getOpenByQuotationNumberAction(),
                $this->getCreateQuotationAction(),
            ])
            ->columns([
                TextColumn::make('quotation_number')
                    ->label('Quotation No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->limit(28)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('sales-pos.quotation_statuses')[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'finalized' => 'success',
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
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                $this->getOpenQuotationAction(),
                $this->getDeleteQuotationAction(),
            ]);
    }

    protected function getQuotationRecords(): Collection
    {
        $records = collect(app(SalesQuotationRepository::class)->all())
            ->map(function (array $sheet): array {
                $sheet['lines_count'] = count($sheet['rows'] ?? []);

                return $sheet;
            });

        $search = trim((string) $this->getTableSearch());

        if ($search !== '') {
            $needle = Str::lower($search);

            $records = $records->filter(function (array $record) use ($needle): bool {
                return str_contains(Str::lower((string) $record['quotation_number']), $needle)
                    || str_contains(Str::lower((string) ($record['customer_name'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['held_label'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['notes'] ?? '')), $needle);
            });
        }

        return $records->sortByDesc('updated_at')->values();
    }

    protected function getOpenByQuotationNumberAction(): Action
    {
        return Action::make('openByQuotationNumber')
            ->label('Open Quotation')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->color('gray')
            ->modalHeading('Open Sales Quotation')
            ->modalDescription('Enter the full quotation number, for example SQ-20260621-0001.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('quotation_number')
                    ->label('Quotation Number')
                    ->placeholder('SQ-YYYYMMDD-####')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(SalesQuotationRepository::class)->findByQuotationNumber((string) ($data['quotation_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()
                        ->warning()
                        ->title('Quotation not found')
                        ->send();

                    return;
                }

                $this->redirect(SalesQuotationWorksheet::getUrl(['quotationId' => $sheet['id']]));
            });
    }

    protected function getCreateQuotationAction(): Action
    {
        return Action::make('createQuotation')
            ->label('New Quotation')
            ->icon(Heroicon::OutlinedPlus)
            ->action(function (): void {
                $sheet = app(SalesQuotationRepository::class)->create();

                $this->redirect(SalesQuotationWorksheet::getUrl(['quotationId' => $sheet['id']]));
            });
    }

    protected function getOpenQuotationAction(): Action
    {
        return Action::make('openQuotation')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (array $record): string => SalesQuotationWorksheet::getUrl(['quotationId' => $record['id']]));
    }

    protected function getDeleteQuotationAction(): Action
    {
        return Action::make('deleteQuotation')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (array $record): bool => ($record['status'] ?? '') !== 'finalized')
            ->requiresConfirmation()
            ->modalHeading('Delete Sales Quotation')
            ->modalDescription('This draft or held quotation will be permanently removed.')
            ->action(function (array $record): void {
                app(SalesQuotationRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('Quotation deleted')
                    ->send();
            });
    }
}
