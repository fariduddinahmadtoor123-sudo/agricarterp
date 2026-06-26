<?php

namespace App\Filament\Pages\OnlineStore;

use App\Filament\OnlineStore\Schemas\StorePageForm;
use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Models\OnlineStore\StorePage;
use App\Services\OnlineStore\StorePagePersistenceService;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class StorePages extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'online-store/pages';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'online-store';
    }

    public static function submenuKey(): string
    {
        return 'pages';
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
            ->query(StorePage::query())
            ->defaultSort('title_en')
            ->searchable(['title_en', 'title_ur', 'slug'])
            ->paginated([10, 25, 50])
            ->modelLabel('Page')
            ->pluralModelLabel('Pages')
            ->recordTitleAttribute('title_en')
            ->headerActions([
                $this->getCreatePageAction(),
            ])
            ->filters([
                SelectFilter::make('is_published')
                    ->label('Published')
                    ->options([
                        '1' => 'Published',
                        '0' => 'Draft',
                    ]),
            ])
            ->columns([
                TextColumn::make('title_en')
                    ->label('Title (EN)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title_ur')
                    ->label('Title (UR)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedMinusCircle)
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->recordActions([
                $this->getEditPageAction(),
                $this->getDeletePageAction(),
            ]);
    }

    protected function getCreatePageAction(): Action
    {
        return Action::make('createPage')
            ->label('Add Page')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Page')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (): array => StorePageForm::defaultState())
            ->schema(fn (Schema $schema): Schema => StorePageForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistPage($schema);
            });
    }

    protected function getEditPageAction(): Action
    {
        return Action::make('editPage')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Edit Page')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (StorePage $record): array => StorePageForm::fromModel($record))
            ->schema(fn (Schema $schema, StorePage $record): Schema => StorePageForm::configure($schema, record: $record))
            ->action(function (Schema $schema, StorePage $record): void {
                $this->persistPage($schema, $record);
            });
    }

    protected function getDeletePageAction(): Action
    {
        return Action::make('deletePage')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (StorePage $record): void {
                app(StorePagePersistenceService::class)->delete($record);

                Notification::make()
                    ->success()
                    ->title('Page deleted')
                    ->send();
            });
    }

    protected function persistPage(Schema $schema, ?StorePage $page = null): void
    {
        $data = $schema->getState();

        try {
            $service = app(StorePagePersistenceService::class);

            if ($page !== null) {
                $service->update($page, $data);
            } else {
                $service->create($data);
            }
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first() ?? 'Validation failed')
                ->send();

            throw $exception;
        }

        Notification::make()
            ->success()
            ->title('Page saved')
            ->send();

        $this->unmountAction();
    }
}
