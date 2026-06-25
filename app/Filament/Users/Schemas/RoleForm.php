<?php

namespace App\Filament\Users\Schemas;

use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoleForm
{
    public static function configure(Schema $schema, bool $readOnly = false): Schema
    {
        $permissionOptions = [];

        foreach (config('users.permission_matrix', []) as $module => $actions) {
            $moduleLabel = Str::headline(str_replace('-', ' ', $module));

            foreach ($actions as $action => $label) {
                $permissionOptions[$module . '.' . $action] = $moduleLabel . ' — ' . $label;
            }
        }

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->components([
                Section::make('Role Details')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->label('Role Name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug((string) $state, '_'));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->alphaDash(),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Permissions')
                    ->compact()
                    ->schema([
                        CheckboxList::make('permission_keys')
                            ->label(null)
                            ->options($permissionOptions)
                            ->columns(3)
                            ->bulkToggleable(! $readOnly)
                            ->searchable(),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'name' => null,
            'slug' => null,
            'description' => null,
            'permission_keys' => [],
        ];
    }

    public static function fromModel(Role $role): array
    {
        $role->loadMissing('permissions');

        return [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permission_keys' => $role->permissions->pluck('key')->all(),
        ];
    }
}
