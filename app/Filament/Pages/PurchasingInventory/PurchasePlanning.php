<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\PurchasingInventory\Support\PurchasePlanningTableConfiguration;
use App\Support\PurchasingInventory\PurchasePlanningSheetRepository;
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

class PurchasePlanning extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'purchasing-inventory/purchase-planning';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 'purchase-planning';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return PurchasePlanningTableConfiguration::applyListLayout(
            $table
                ->records(fn (): Collection => $this->getSheetRecords())
                ->searchable()
                ->defaultSort('updated_at', 'desc')
                    ->paginated([10, 25, 50])
                    ->modelLabel('Planning Sheet')
                    ->pluralModelLabel('Planning Sheets')
                    ->recordTitleAttribute('sheet_number')
                    ->headerActions([
                        $this->getOpenBySheetNumberAction(),
                        $this->getCreateSheetAction(),
                    ])
                    ->columns([
                        TextColumn::make('sheet_number')
                            ->label('Sheet Number')
                            ->sortable()
                            ->searchable(),
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
                        $this->getOpenSheetAction(),
                        $this->getDeleteSheetAction(),
                    ]),
        );
    }

    protected function getSheetRecords(): Collection
    {
        $records = collect(app(PurchasePlanningSheetRepository::class)->all())
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
                return str_contains(Str::lower((string) $record['sheet_number']), $needle)
                    || str_contains(Str::lower((string) ($record['title'] ?? '')), $needle)
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
            ->modalHeading('Open Planning Sheet')
            ->modalDescription('Enter the full sheet number, for example PP-20260623-ABCD.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Open')
            ->schema([
                TextInput::make('sheet_number')
                    ->label('Sheet Number')
                    ->placeholder('PP-YYYYMMDD-XXXX')
                    ->required()
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $sheet = app(PurchasePlanningSheetRepository::class)->findBySheetNumber((string) ($data['sheet_number'] ?? ''));

                if ($sheet === null) {
                    Notification::make()
                        ->warning()
                        ->title('Planning sheet not found')
                        ->body('Check the sheet number and try again.')
                        ->send();

                    return;
                }

                $this->redirect(PurchasePlanningWorksheet::getUrl(['sheetId' => $sheet['id']]));
            });
    }

    protected function getCreateSheetAction(): Action
    {
        return Action::make('createPlanningSheet')
            ->label('New Planning Sheet')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canCreate())
            ->action(function (): void {
                $sheet = app(PurchasePlanningSheetRepository::class)->create();

                $this->redirect(PurchasePlanningWorksheet::getUrl(['sheetId' => $sheet['id']]));
            });
    }

    protected function getOpenSheetAction(): Action
    {
        return Action::make('openPlanningSheet')
            ->label('Open')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canView())
            ->url(fn (array $record): string => PurchasePlanningWorksheet::getUrl(['sheetId' => $record['id']]));
    }

    protected function getDeleteSheetAction(): Action
    {
        return Action::make('deletePlanningSheet')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => PurchasingInventoryAuthorization::canDelete())
            ->requiresConfirmation()
            ->modalHeading('Delete Planning Sheet')
            ->modalDescription('This preview sheet will be removed from session storage.')
            ->action(function (array $record): void {
                app(PurchasePlanningSheetRepository::class)->delete((string) $record['id']);

                Notification::make()
                    ->success()
                    ->title('Planning sheet deleted')
                    ->send();
            });
    }
}
