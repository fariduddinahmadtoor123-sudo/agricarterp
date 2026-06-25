<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Filament\Users\Schemas\RoleForm;
use App\Models\Role;
use App\Services\Users\RolePersistenceService;
use App\Support\Users\RoleAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
use Illuminate\Validation\ValidationException;

class RolesPermissions extends Page implements HasTable
{
    use InteractsWithModuleSubmenuPage;
    use InteractsWithTable;

    protected static ?string $slug = 'settings/roles-permissions';

    protected static bool $shouldRegisterNavigation = false;

    public static function moduleKey(): string
    {
        return 'settings';
    }

    public static function submenuKey(): string
    {
        return 'roles-permissions';
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
            ->extraAttributes(['class' => 'agricart-settings-roles-list'])
            ->query(Role::query()->withCount('permissions', 'users'))
            ->defaultSort('name')
            ->modelLabel('Role')
            ->pluralModelLabel('Roles & Permissions')
            ->emptyStateHeading('No custom roles yet')
            ->emptyStateDescription('Create roles and choose what each role can access.')
            ->headerActions(
                RoleAuthorization::canCreate()
                    ? [$this->getCreateRoleAction()]
                    : [],
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_system')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'System' : 'Custom')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->counts('permissions')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->recordActions([
                $this->getViewRoleAction(),
                $this->getEditRoleAction(),
                $this->getDeleteRoleAction(),
            ]);
    }

    protected function getCreateRoleAction(): Action
    {
        return Action::make('createRole')
            ->label('Add Role')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add Role')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (): array => RoleForm::defaultState())
            ->schema(fn (Schema $schema): Schema => RoleForm::configure($schema))
            ->action(function (Schema $schema): void {
                $this->persistRole($schema);
            });
    }

    protected function getViewRoleAction(): Action
    {
        return Action::make('viewRole')
            ->label('View')
            ->icon(Heroicon::OutlinedEye)
            ->visible(fn (): bool => RoleAuthorization::canView())
            ->modalHeading('View Role')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->fillForm(fn (Role $record): array => RoleForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => RoleForm::configure($schema, readOnly: true));
    }

    protected function getEditRoleAction(): Action
    {
        return Action::make('editRole')
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (Role $record): bool => RoleAuthorization::canEdit() && ! $record->is_system)
            ->modalHeading('Edit Role')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Save & Close')
            ->fillForm(fn (Role $record): array => RoleForm::fromModel($record))
            ->schema(fn (Schema $schema): Schema => RoleForm::configure($schema))
            ->action(function (Schema $schema, Role $record): void {
                $this->persistRole($schema, $record);
            });
    }

    protected function getDeleteRoleAction(): DeleteAction
    {
        return DeleteAction::make('deleteRole')
            ->label('Delete')
            ->icon(Heroicon::OutlinedTrash)
            ->visible(fn (Role $record): bool => RoleAuthorization::canDelete() && ! $record->is_system)
            ->modalHeading('Delete Role')
            ->modalDescription('Users assigned to this role must be reassigned before deletion.')
            ->successNotificationTitle('Role deleted')
            ->action(function (Role $record, RolePersistenceService $persistence): void {
                $persistence->delete($record);
            });
    }

    protected function persistRole(Schema $schema, ?Role $role = null): void
    {
        if ($role !== null && ! RoleAuthorization::canEdit()) {
            abort(403);
        }

        if ($role === null && ! RoleAuthorization::canCreate()) {
            abort(403);
        }

        $data = $schema->getState();

        try {
            $persistence = app(RolePersistenceService::class);

            if ($role !== null) {
                $persistence->update($role, $data);
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
            ->title('Role saved')
            ->send();

        $this->unmountAction();
    }
}
