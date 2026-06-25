<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\PurchasingInventory\Support\PurchaseQuotationTableConfiguration;
use App\Support\PurchasingInventory\PurchaseQuotationSheetRepository;
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

class PurchaseQuotations extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'purchasing-inventory/purchase-quotations';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchase-quotations';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return PurchaseQuotationTableConfiguration::applyListLayout(
            $table
                ->records(fn (): Collection => $this->getQuotationRecords())
                ->searchable()
                ->defaultSort('updated_at', 'desc')
                ->paginated([10, 25, 50])
                ->modelLabel('Quotation')
                ->pluralModelLabel('Purchase Quotations')
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
                    TextColumn::make('supplier_name')
                        ->label('Supplier')
                        ->limit(30)
                        ->toggleable(),
                    TextColumn::make('store_name')
                        ->label('Store')
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
                    TextColumn::make('lines_count')
                        ->label('Lines')
                        ->sortable(),
                    TextColumn::make('notes')
                        ->label('Notes')
                        ->limit(40)
                        ->wrap()
                        ->toggleable(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->dateTime()
                        ->sortable(),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getOpenQuotationAction(),
                    $this->getDeleteQuotationAction(),
                ]),
        );
    }

    protected function getQuotationRecords(): Collection
    {
        $records = collect(app(PurchaseQuotationSheetRepository::class)->all())
            ->map(function (array $sheet): array {
                $sheet['lines_count'] = count($sheet['rows'] ?? []);

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
                return str_contains(Str::lower((string) $record['quotation_number']), $needle)
                    || str_contains(Str::lower((string) ($record['title'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['supplier_name'] ?? '')), $needle)
                    || str_contains(Str::lower((string) ($record['store_name'] ?? '')), $needle)
                    || str_contains(Str::lower((string) $record['notes']), $needle);
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
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canView())
            ->modalHeading('Open Purchase Quotation')
            ->modalDescription('Enter the full quotation number, for example PQ-20260623-ABCD.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('quotation_number')
                    ->label('Quotation Number')
                    ->placeholder('PQ-YYYYMMDD-XXXX')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(PurchaseQuotationSheetRepository::class)->findByQuotationNumber((string) ($data['quotation_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()
                        ->warning()
                        ->title('Quotation not found')
                        ->body('Check the quotation number and try again.')
                        ->send();

                    return;
                }

                $this->redirect(PurchaseQuotationWorksheet::getUrl(['quotationId' => $sheet['id']]));
            });
    }

    protected function getCreateQuotationAction(): Action
    {
        return Action::make('createQuotation')
            ->label('New Quotation')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canCreate())
            ->action(function (): void {
                $sheet = app(PurchaseQuotationSheetRepository::class)->create();

                $this->redirect(PurchaseQuotationWorksheet::getUrl(['quotationId' => $sheet['id']]));
            });
    }

    protected function getOpenQuotationAction(): Action
    {
        return Action::make('openQuotation')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canView())
            ->url(fn (array $record): string => PurchaseQuotationWorksheet::getUrl(['quotationId' => $record['id']]));
    }

    protected function getDeleteQuotationAction(): Action
    {
        return Action::make('deleteQuotation')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canDelete())
            ->requiresConfirmation()
            ->modalHeading('Delete Quotation')
            ->modalDescription('This preview quotation will be removed from session storage.')
            ->action(function (array $record): void {
                app(PurchaseQuotationSheetRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('Quotation deleted')
                    ->send();
            });
    }
}
