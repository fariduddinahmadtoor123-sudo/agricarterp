<?php

namespace App\Filament\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use App\Support\Users\UserAuthorization;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    /** @var array<string, int|string> */
    protected static array $gridColumns = [
        'default' => 1,
        'lg' => 12,
    ];

    public static function configure(Schema $schema, bool $readOnly = false, bool $includePassword = false): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-user-form' . ($readOnly ? ' agricart-user-form-readonly' : ''),
            ])
            ->components([
                Section::make('Staff Information')
                    ->compact()
                    ->columns(static::$gridColumns)
                    ->schema(static::staffInformationFields($readOnly, $includePassword))
                    ->columnSpanFull(),

                Section::make('Phone Numbers')
                    ->compact()
                    ->schema([
                        Repeater::make('phones')
                            ->label(null)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add Phone')
                            ->addable(! $readOnly)
                            ->deletable(! $readOnly)
                            ->reorderable(false)
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextInput::make('contact_person')
                                    ->label('Contact Person'),

                                TextInput::make('phone_number')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Bank Accounts')
                    ->compact()
                    ->schema([
                        Repeater::make('bank_accounts')
                            ->label(null)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Add Bank')
                            ->addable(! $readOnly)
                            ->deletable(! $readOnly)
                            ->reorderable(false)
                            ->columns(['default' => 1, 'lg' => 22])
                            ->schema(static::bankAccountFields())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Documents')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        static::documentUpload('documents.profile_photo', 'Profile Photo', $readOnly),
                        static::documentUpload('documents.card_front', 'CNIC Front', $readOnly),
                        static::documentUpload('documents.card_back', 'CNIC Back', $readOnly),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, TextInput|Select|Textarea>
     */
    protected static function staffInformationFields(bool $readOnly, bool $includePassword): array
    {
        $fields = [
            TextInput::make('user_number')
                ->label('User Number')
                ->disabled()
                ->dehydrated()
                ->placeholder('Auto Generated')
                ->columnSpan(['lg' => 3]),

            TextInput::make('name')
                ->label('Name')
                ->required()
                ->columnSpan(['lg' => 3]),

            Select::make('role_id')
                ->label('Role')
                ->options(fn (): array => Role::query()
                    ->where('slug', '!=', Role::SLUG_SUPER_ADMIN)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required()
                ->disabled($readOnly)
                ->columnSpan(['lg' => 3]),

            Select::make('status')
                ->label('Status')
                ->options(config('users.user_statuses', []))
                ->default(User::STATUS_ACTIVE)
                ->disabled($readOnly || ! UserAuthorization::canDeactivate())
                ->dehydrated()
                ->columnSpan(['lg' => 3]),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->columnSpan(['lg' => 4]),

            Textarea::make('full_address')
                ->label('Address')
                ->rows(2)
                ->columnSpan(['lg' => 8]),
        ];

        if ($includePassword) {
            $fields[] = TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->required()
                ->confirmed()
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                ->columnSpan(['lg' => 4]);

            $fields[] = TextInput::make('password_confirmation')
                ->label('Confirm Password')
                ->password()
                ->revealable()
                ->required()
                ->columnSpan(['lg' => 4]);
        } elseif (! $readOnly) {
            $fields[] = TextInput::make('password')
                ->label('New Password')
                ->password()
                ->revealable()
                ->confirmed()
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                ->columnSpan(['lg' => 4]);

            $fields[] = TextInput::make('password_confirmation')
                ->label('Confirm New Password')
                ->password()
                ->revealable()
                ->columnSpan(['lg' => 4]);
        }

        return $fields;
    }

    /**
     * @return array<int, TextInput>
     */
    protected static function bankAccountFields(): array
    {
        return [
            TextInput::make('bank_name')
                ->label('Bank Name')
                ->columnSpan(['lg' => 4]),

            TextInput::make('branch_name')
                ->label('Branch Name')
                ->columnSpan(['lg' => 5]),

            TextInput::make('account_title')
                ->label('Account Title')
                ->columnSpan(['lg' => 7]),

            TextInput::make('iban_account_number')
                ->label('IBAN / Account Number')
                ->columnSpan(['lg' => 6]),
        ];
    }

    protected static function documentUpload(string $name, string $label, bool $readOnly): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->acceptedFileTypes(config('users.document_types', []))
            ->directory(config('users.documents_directory', 'users/documents'))
            ->disk(config('users.document_disk', 'local'))
            ->imagePreviewHeight('150')
            ->downloadable($readOnly)
            ->openable($readOnly);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'user_number' => null,
            'name' => null,
            'role_id' => null,
            'status' => User::STATUS_ACTIVE,
            'email' => null,
            'full_address' => null,
            'password' => null,
            'password_confirmation' => null,
            'phones' => [
                ['contact_person' => null, 'phone_number' => null],
            ],
            'bank_accounts' => [
                [
                    'bank_name' => null,
                    'branch_name' => null,
                    'account_title' => null,
                    'iban_account_number' => null,
                ],
            ],
            'documents' => [
                'profile_photo' => null,
                'card_front' => null,
                'card_back' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        $state = array_replace_recursive(static::defaultState(), $state);

        if ($state['phones'] === []) {
            $state['phones'] = static::defaultState()['phones'];
        }

        if ($state['bank_accounts'] === []) {
            $state['bank_accounts'] = static::defaultState()['bank_accounts'];
        }

        return $state;
    }

    public static function fromModel(User $user): array
    {
        $user->loadMissing(['role', 'phones', 'bankAccounts', 'document']);

        return static::normalizeState([
            'user_number' => $user->user_number,
            'name' => $user->name,
            'role_id' => $user->role_id,
            'status' => $user->status,
            'email' => $user->email,
            'full_address' => $user->full_address,
            'phones' => $user->phones->map(fn ($phone): array => [
                'contact_person' => $phone->contact_person,
                'phone_number' => $phone->phone_number,
            ])->all(),
            'bank_accounts' => $user->bankAccounts->map(fn ($account): array => [
                'bank_name' => $account->bank_name,
                'branch_name' => $account->branch_name,
                'account_title' => $account->account_title,
                'iban_account_number' => $account->iban_account_number,
            ])->all(),
            'documents' => [
                'profile_photo' => $user->document?->profile_photo_path,
                'card_front' => $user->document?->card_front_path,
                'card_back' => $user->document?->card_back_path,
            ],
        ]);
    }

    public static function fromApplication(\App\Models\UserApplication $application): array
    {
        $application->loadMissing(['phones', 'bankAccounts', 'document']);

        return static::normalizeState([
            'user_number' => null,
            'name' => $application->name,
            'role_id' => null,
            'status' => User::STATUS_ACTIVE,
            'email' => $application->email,
            'full_address' => $application->full_address,
            'phones' => $application->phones->map(fn ($phone): array => [
                'contact_person' => $phone->contact_person,
                'phone_number' => $phone->phone_number,
            ])->all(),
            'bank_accounts' => $application->bankAccounts->map(fn ($account): array => [
                'bank_name' => $account->bank_name,
                'branch_name' => $account->branch_name,
                'account_title' => $account->account_title,
                'iban_account_number' => $account->iban_account_number,
            ])->all(),
            'documents' => [
                'profile_photo' => $application->document?->profile_photo_path,
                'card_front' => $application->document?->card_front_path,
                'card_back' => $application->document?->card_back_path,
            ],
        ]);
    }
}
