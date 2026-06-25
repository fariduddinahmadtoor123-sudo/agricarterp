<?php

namespace App\Filament\Pages\ProductCatalog;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\ProductCatalog\Schemas\AttributeForm;
use App\Filament\ProductCatalog\Support\AttributeTableConfiguration;
use App\Filament\ProductCatalog\Support\ProductCatalogTableSearch;
use App\Models\Attribute;
use App\Services\ProductCatalog\AttributePersistenceService;
use App\Support\ProductCatalog\AttributeAuthorization;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class Attributes extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'product-catalog/attributes';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'product-catalog';
    }

    public static function submenuKey(): string
    {
        return 'attributes';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return ProductCatalogTableSearch::apply(
            AttributeTableConfiguration::applyListLayout(
            $table
                ->query(Attribute::query())
                ->defaultSort('attribute_number', 'asc')
                ->deferLoading()
                ->modelLabel('Attribute')
                ->pluralModelLabel('Attributes')
                ->headerActions(
                    AttributeAuthorization::canCreate()
                        ? [$this->getCreateAttributeAction()]
                        : [],
                )
                ->columns([
                    TextColumn::make('attribute_number')
                        ->label('Attribute Number')
                        ->sortable(),
                    TextColumn::make('name')
                        ->label('Attribute Name')
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => config('product-catalog.attribute_statuses')[$state] ?? ucfirst((string) $state))
                        ->color(fn (?string $state): string => match ($state) {
                            Attribute::STATUS_ACTIVE => 'success',
                            Attribute::STATUS_ARCHIVED => 'gray',
                            default => 'gray',
                        })
                        ->sortable(),
                    TextColumn::make('created_at')
                        ->label('Created')
                        ->date()
                        ->sortable(),
                ])
                ->recordActions([
                    $this->getViewAttributeAction(),
                    $this->getEditAttributeAction(),
                    $this->getArchiveAttributeAction(),
                    $this->getRestoreAttributeAction(),
                ]),
            ),
            function (Builder $query, string $term): void {
                $query
                    ->where('attribute_number', 'like', $term)
                    ->orWhere('name', 'like', $term);
            },
        );
    }

    protected function getCreateAttributeAction(): Action
    {
        return Action::make('createAttribute')
            ->label('Add Attribute')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Attribute')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (): array => AttributeForm::defaultState())
            ->schema(fn (Schema $schema): Schema => AttributeForm::configure($schema))
            ->action(function (array $arguments, Schema $schema): void {
                $this->persistAttribute($schema, $arguments['another'] ?? false);
            });
    }

    protected function getViewAttributeAction(): Action
    {
        return Action::make('viewAttribute')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => AttributeAuthorization::canView())
            ->modalHeading('View Attribute')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Attribute $record): array => AttributeForm::fromModel($record))
            ->schema(fn (Schema $schema, Attribute $record): Schema => AttributeForm::configure($schema, readOnly: true, record: $record));
    }

    protected function getEditAttributeAction(): Action
    {
        return Action::make('editAttribute')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Attribute $record): bool => AttributeAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit Attribute')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->extraModalFooterActions(function (Action $action): array {
                return [
                    $action->makeModalSubmitAction('saveAndAddNext', ['another' => true])
                        ->label('Save & Add Next'),
                ];
            })
            ->fillForm(fn (Attribute $record): array => AttributeForm::fromModel($record))
            ->schema(fn (Schema $schema, Attribute $record): Schema => AttributeForm::configure($schema, record: $record))
            ->action(function (array $arguments, Schema $schema, Attribute $record): void {
                $this->persistAttribute($schema, $arguments['another'] ?? false, $record);
            });
    }

    protected function getArchiveAttributeAction(): Action
    {
        return Action::make('archiveAttribute')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Archive Attribute')
            ->modalDescription('This attribute will be archived. Attribute number and name will be preserved.')
            ->visible(fn (Attribute $record): bool => AttributeAuthorization::canArchive() && $record->isActive())
            ->action(function (Attribute $record, AttributePersistenceService $persistence): void {
                $persistence->archive($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Attribute archived')
                    ->send();
            });
    }

    protected function getRestoreAttributeAction(): Action
    {
        return Action::make('restoreAttribute')
            ->label('Restore')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Restore Attribute')
            ->modalDescription('This attribute will be restored to active status.')
            ->visible(fn (Attribute $record): bool => AttributeAuthorization::canRestore() && $record->isArchived())
            ->action(function (Attribute $record, AttributePersistenceService $persistence): void {
                $persistence->restore($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('Attribute restored')
                    ->send();
            });
    }

    protected function persistAttribute(Schema $schema, bool $another, ?Attribute $attribute = null): void
    {
        if ($attribute !== null && ! AttributeAuthorization::canEdit()) {
            abort(403);
        }

        if ($attribute === null && ! AttributeAuthorization::canCreate()) {
            abort(403);
        }

        $data = AttributeForm::normalizeState($schema->getState());

        try {
            $persistence = app(AttributePersistenceService::class);

            if ($attribute !== null) {
                $persistence->update($attribute, $data);
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
            ->title('Attribute saved')
            ->send();

        if ($another) {
            if ($attribute !== null) {
                $this->unmountAction();
                $this->mountAction('createAttribute');

                return;
            }

            $schema->fill(AttributeForm::defaultState());
            $schema->dispatchClientSideStateReset();
            $this->halt();

            return;
        }

        $this->unmountAction();
    }
}
