<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Settings\Schemas\CompanySettingForm;
use App\Filament\Settings\Support\CompanySettingTableConfiguration;
use App\Models\CompanySetting;
use App\Services\Settings\CompanySettingLogoStorage;
use App\Services\Settings\CompanySettingPersistenceService;
use App\Support\Settings\CompanySettingAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class GeneralSettings extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/general-settings';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'general-settings';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $logoStorage = app(CompanySettingLogoStorage::class);

        return CompanySettingTableConfiguration::applyListLayout(
            $table
                ->query(CompanySetting::query())
                ->defaultSort('id', 'asc')
                ->modelLabel('Company / Main Store')
                ->pluralModelLabel('Company / Main Store Settings')
                ->emptyStateHeading('No company settings yet')
                ->emptyStateDescription('Add your company / main store profile to use across the ERP.')
                ->headerActions(
                    CompanySettingAuthorization::canCreate() && ! CompanySetting::query()->exists()
                        ? [$this->getCreateCompanySettingAction()]
                        : [],
                )
                ->columns([
                    ImageColumn::make('logo_path')
                        ->label('Logo')
                        ->getStateUsing(fn (CompanySetting $record): ?string => $logoStorage->url($record->logo_path))
                        ->imageHeight(48)
                        ->extraImgAttributes([
                            'class' => 'agricart-company-setting-table-logo',
                        ]),
                    TextColumn::make('name_en')
                        ->label('English Name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('name_ur')
                        ->label('Urdu Name')
                        ->placeholder('—')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('currency')
                        ->label('Currency')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('timezone')
                        ->label('Timezone')
                        ->sortable(),
                    TextColumn::make('phones')
                        ->label('Phones')
                        ->getStateUsing(function (CompanySetting $record): string {
                            $phones = $record->phones;

                            if (is_string($phones)) {
                                $phones = json_decode($phones, true);
                            }

                            $phones = is_array($phones) ? $phones : [];

                            return $phones === [] ? '—' : (string) count($phones);
                        }),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getViewCompanySettingAction(),
                    $this->getEditCompanySettingAction(),
                ]),
        );
    }

    protected function getCreateCompanySettingAction(): Action
    {
        return Action::make('createCompanySetting')
            ->label('Add Company / Main Store')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Company / Main Store')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (): array => CompanySettingForm::defaultState())
            ->schema(fn (Schema $schema): Schema => CompanySettingForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistCompanySetting($schema);
            });
    }

    protected function getViewCompanySettingAction(): Action
    {
        return Action::make('viewCompanySetting')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => CompanySettingAuthorization::canView())
            ->modalHeading('View Company / Main Store')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (CompanySetting $record): array => CompanySettingForm::fromModel($record))
            ->schema(fn (Schema $schema, CompanySetting $record): Schema => CompanySettingForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditCompanySettingAction(): Action
    {
        return Action::make('editCompanySetting')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => CompanySettingAuthorization::canEdit())
            ->modalHeading('Edit Company / Main Store')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (CompanySetting $record): array => CompanySettingForm::fromModel($record))
            ->schema(fn (Schema $schema, CompanySetting $record): Schema => CompanySettingForm::configure($schema, record: $record))
            ->action(function (Schema $schema, CompanySetting $record): void {
                $this->persistCompanySetting($schema, $record);
            });
    }

    protected function persistCompanySetting(Schema $schema, ?CompanySetting $setting = null): void
    {
        if ($setting !== null && ! CompanySettingAuthorization::canEdit()) {
            abort(403);
        }

        if ($setting === null && ! CompanySettingAuthorization::canCreate()) {
            abort(403);
        }

        $data = CompanySettingForm::normalizeState($schema->getState());

        try {
            $persistence = app(CompanySettingPersistenceService::class);

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
            ->title('Company / main store settings saved')
            ->send();

        $this->unmountAction();
    }
}
