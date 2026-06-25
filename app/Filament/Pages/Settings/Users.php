<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Users\Schemas\UserForm;
use App\Models\User;
use App\Services\Users\UserPersistenceService;
use App\Support\Users\UserAuthorization;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class Users extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/users';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'users';
    }

    public static function canAccess(): bool
    {
        return UserAuthorization::canView();
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
            ->extraAttributes(['class' => 'agricart-settings-users-list'])
            ->query(User::query()->with('role'))
            ->defaultSort('created_at', 'desc')
            ->modelLabel('User')
            ->pluralModelLabel('Users')
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Add staff users and assign roles.')
            ->headerActions(
                UserAuthorization::canCreate()
                    ? [$this->getCreateUserAction()]
                    : [],
            )
            ->columns([
                TextColumn::make('user_number')
                    ->label('User #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => config('users.user_statuses')[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        User::STATUS_ACTIVE => 'success',
                        User::STATUS_INACTIVE => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(config('users.user_statuses', [])),
                SelectFilter::make('role_id')
                    ->label('Role')
                    ->relationship('role', 'name'),
            ])
            ->recordActions([
                $this->getViewUserAction(),
                $this->getEditUserAction(),
                $this->getDeactivateUserAction(),
                $this->getActivateUserAction(),
            ]);
    }

    protected function getCreateUserAction(): Action
    {
        return Action::make('createUser')
            ->label('Add User')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add User')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (): array => UserForm::defaultState())
            ->schema(fn (Schema $schema): Schema => UserForm::configure($schema, includePassword: true))
            ->action(function (Schema $schema): void {
                $this->persistUser($schema);
            });
    }

    protected function getViewUserAction(): Action
    {
        return Action::make('viewUser')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => UserAuthorization::canView())
            ->modalHeading('View User')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (User $record): array => UserForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => UserForm::configure($schema, readOnly: true));
    }

    protected function getEditUserAction(): Action
    {
        return Action::make('editUser')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (User $record): bool => UserAuthorization::canEdit() && $record->isActive())
            ->modalHeading('Edit User')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->modalCancelActionLabel('Cancel')
            ->fillForm(fn (User $record): array => UserForm::fromModel($record))
            ->schema(fn (Schema $schema, User $record): Schema => UserForm::configure(
                $schema,
                readOnly: $record->isSuperAdmin(),
            ))
            ->action(function (Schema $schema, User $record): void {
                $this->persistUser($schema, $record);
            });
    }

    protected function getDeactivateUserAction(): Action
    {
        return Action::make('deactivateUser')
            ->label('Deactivate')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Deactivate User')
            ->modalDescription('Inactive users cannot sign in to the admin panel.')
            ->visible(fn (User $record): bool => UserAuthorization::canDeactivate()
                && $record->isActive()
                && ! $record->isSuperAdmin())
            ->action(function (User $record, UserPersistenceService $persistence): void {
                $persistence->deactivate($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('User deactivated')
                    ->send();
            });
    }

    protected function getActivateUserAction(): Action
    {
        return Action::make('activateUser')
            ->label('Activate')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => UserAuthorization::canEdit()
                && ! $record->isActive()
                && ! $record->isSuperAdmin())
            ->action(function (User $record, UserPersistenceService $persistence): void {
                $persistence->activate($record);

                $this->flushCachedTableRecords();

                Notification::make()
                    ->success()
                    ->title('User activated')
                    ->send();
            });
    }

    protected function persistUser(Schema $schema, ?User $user = null): void
    {
        if ($user !== null && ! UserAuthorization::canEdit()) {
            abort(403);
        }

        if ($user === null && ! UserAuthorization::canCreate()) {
            abort(403);
        }

        $data = UserForm::normalizeState($schema->getState());

        try {
            $persistence = app(UserPersistenceService::class);

            if ($user !== null) {
                $persistence->update($user, $data);
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
            ->title('User saved')
            ->send();

        $this->unmountAction();
    }
}
