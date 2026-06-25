<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Settings\Schemas\PurchasePricingSettingForm;
use App\Filament\Settings\Support\PurchasePricingSettingTableConfiguration;
use App\Models\PurchasePricingSetting;
use App\Services\Settings\PurchasePricingSettingPersistenceService;
use App\Support\Settings\PurchasePricingSettingAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PurchasePricing extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/purchase-pricing';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'purchase-pricing';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return PurchasePricingSettingTableConfiguration::applyListLayout(
            $table
                ->query(PurchasePricingSetting::query())
                ->defaultSort('id', 'asc')
                ->modelLabel('Purchase Pricing')
                ->pluralModelLabel('Purchase Pricing Settings')
                ->emptyStateHeading('No purchase pricing settings yet')
                ->emptyStateDescription('Add purchase pricing defaults for markups and price tag wording.')
                ->headerActions(
                    PurchasePricingSettingAuthorization::canCreate() && ! PurchasePricingSetting::query()->exists()
                        ? [$this->getCreatePurchasePricingSettingAction()]
                        : [],
                )
                ->columns([
                    IconColumn::make('update_product_prices_from_purchases')
                        ->label('Update from PU')
                        ->boolean()
                        ->trueIcon(Heroicon::OutlinedCheckCircle)
                        ->falseIcon(Heroicon::OutlinedXCircle)
                        ->trueColor('success')
                        ->falseColor('gray'),
                    TextColumn::make('wholesale_markup_pct')
                        ->label('WS %')
                        ->formatStateUsing(fn (mixed $state): string => static::formatMarkupColumn($state))
                        ->sortable(),
                    TextColumn::make('super_wholesale_markup_pct')
                        ->label('SWS %')
                        ->formatStateUsing(fn (mixed $state): string => static::formatMarkupColumn($state))
                        ->sortable(),
                    TextColumn::make('distributor_markup_pct')
                        ->label('Distributor %')
                        ->formatStateUsing(fn (mixed $state): string => static::formatMarkupColumn($state))
                        ->sortable(),
                    TextColumn::make('price_code_wording')
                        ->label('Price Codes')
                        ->getStateUsing(fn (PurchasePricingSetting $record): string => collect($record->price_code_wording ?? [])
                            ->only(collect(range(0, 9))->map(fn (int $digit): string => (string) $digit)->all())
                            ->filter(fn (mixed $value): bool => filled($value))
                            ->isNotEmpty() ? '0–9 mapped' : '—'),
                    TextColumn::make('updated_at')
                        ->label('Updated')
                        ->dateTime()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getViewPurchasePricingSettingAction(),
                    $this->getEditPurchasePricingSettingAction(),
                ]),
        );
    }

    protected function getCreatePurchasePricingSettingAction(): Action
    {
        return Action::make('createPurchasePricingSetting')
            ->label('Add Purchase Pricing')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Purchase Pricing Settings')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (): array => PurchasePricingSettingForm::defaultState())
            ->schema(fn (Schema $schema): Schema => PurchasePricingSettingForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistPurchasePricingSetting($schema);
            });
    }

    protected function getViewPurchasePricingSettingAction(): Action
    {
        return Action::make('viewPurchasePricingSetting')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => PurchasePricingSettingAuthorization::canView())
            ->modalHeading('View Purchase Pricing Settings')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (PurchasePricingSetting $record): array => PurchasePricingSettingForm::fromModel($record))
            ->schema(fn (Schema $schema, PurchasePricingSetting $record): Schema => PurchasePricingSettingForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditPurchasePricingSettingAction(): Action
    {
        return Action::make('editPurchasePricingSetting')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => PurchasePricingSettingAuthorization::canEdit())
            ->modalHeading('Edit Purchase Pricing Settings')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (PurchasePricingSetting $record): array => PurchasePricingSettingForm::fromModel($record))
            ->schema(fn (Schema $schema, PurchasePricingSetting $record): Schema => PurchasePricingSettingForm::configure($schema, record: $record))
            ->action(function (Schema $schema, PurchasePricingSetting $record): void {
                $this->persistPurchasePricingSetting($schema, $record);
            });
    }

    protected function persistPurchasePricingSetting(Schema $schema, ?PurchasePricingSetting $setting = null): void
    {
        if ($setting !== null && ! PurchasePricingSettingAuthorization::canEdit()) {
            abort(403);
        }

        if ($setting === null && ! PurchasePricingSettingAuthorization::canCreate()) {
            abort(403);
        }

        $data = PurchasePricingSettingForm::normalizeState($schema->getState());

        try {
            $persistence = app(PurchasePricingSettingPersistenceService::class);

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
            ->title('Purchase pricing settings saved')
            ->send();

        $this->unmountAction();
    }

    protected static function formatMarkupColumn(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        $number = (float) str_replace(',', '', (string) $state);

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.') . '%';
    }
}
