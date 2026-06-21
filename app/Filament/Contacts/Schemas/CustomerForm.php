<?php

namespace App\Filament\Contacts\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    /** @var array<string, int|string> */
    protected static array $gridColumns = [
        'default' => 1,
        'lg' => 12,
    ];

    public static function configure(Schema $schema, bool $readOnly = false): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-customer-form' . ($readOnly ? ' agricart-customer-form-readonly' : ''),
            ])
            ->components([
                Section::make('Customer Information')
                    ->compact()
                    ->extraAttributes(['class' => 'agricart-customer-grid-section'])
                    ->columns(static::$gridColumns)
                    ->schema(static::customerInformationFields())
                    ->columnSpanFull(),

                Section::make('Bank Accounts')
                    ->compact()
                    ->schema([
                        Repeater::make('bank_accounts')
                            ->label(null)
                            ->defaultItems(0)
                            ->minItems(0)
                            ->addActionLabel('Add Bank')
                            ->addable(! $readOnly)
                            ->deletable(! $readOnly)
                            ->reorderable(false)
                            ->columns(['default' => 1, 'lg' => 22])
                            ->extraFieldWrapperAttributes([
                                'class' => 'agricart-bank-repeater',
                            ])
                            ->schema(static::bankAccountFields())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Urdu Information')
                    ->compact()
                    ->collapsed(! $readOnly)
                    ->extraAttributes(['class' => 'agricart-customer-grid-section'])
                    ->columns(static::$gridColumns)
                    ->schema(static::urduFields())
                    ->columnSpanFull(),

                Section::make('Additional Information')
                    ->compact()
                    ->collapsed(! $readOnly)
                    ->columns(1)
                    ->schema([
                        Fieldset::make('Additional Contacts')
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('additional_contacts')
                                    ->label(null)
                                    ->columns(['default' => 1, 'lg' => 2])
                                    ->addActionLabel('Add Contact')
                                    ->addable(! $readOnly)
                                    ->deletable(! $readOnly)
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('contact_person')
                                            ->label('Contact Person'),

                                        TextInput::make('mobile_number')
                                            ->label('Mobile Number')
                                            ->tel(),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Fieldset::make('Documents')
                            ->columnSpanFull()
                            ->schema([
                                static::documentUpload('documents.profile_photo', 'Profile Photo', $readOnly),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, TextInput|Select|Textarea>
     */
    protected static function customerInformationFields(): array
    {
        return [
            TextInput::make('customer_code')
                ->label('Customer Code')
                ->disabled()
                ->dehydrated()
                ->placeholder('Auto Generated')
                ->columnSpan(['lg' => 2]),

            Select::make('country')
                ->label('Country')
                ->options(config('contacts.countries', []))
                ->searchable()
                ->columnSpan(['lg' => 2]),

            TextInput::make('city')
                ->label('City')
                ->columnSpan(['lg' => 2]),

            Textarea::make('full_address')
                ->label('Full Address')
                ->rows(1)
                ->extraAttributes(['class' => 'agricart-customer-address-inline'])
                ->columnSpan(['lg' => 4]),

            TextInput::make('customer_name')
                ->label('Customer Name')
                ->required()
                ->columnSpan(['lg' => 4]),

            TextInput::make('mobile_number')
                ->label('Mobile Number')
                ->tel()
                ->required()
                ->columnSpan(['lg' => 2]),

            TextInput::make('credit_limit')
                ->label('Credit Limit')
                ->numeric()
                ->columnSpan(['lg' => 2]),

            TextInput::make('opening_balance')
                ->label('Opening Balance')
                ->numeric()
                ->columnSpan(['lg' => 2]),
        ];
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
            ->acceptedFileTypes(config('contacts.customer_document_types', [
                'image/jpeg',
                'image/png',
                'image/webp',
            ]))
            ->directory(config('contacts.customer_documents_directory', 'contacts/customers/documents'))
            ->disk(config('contacts.customer_documents_disk', 'local'))
            ->imagePreviewHeight('150')
            ->downloadable($readOnly)
            ->openable($readOnly);
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    protected static function urduFields(): array
    {
        return [
            TextInput::make('urdu.customer_name')
                ->label('Customer Name Urdu')
                ->columnSpan(['lg' => 4]),

            TextInput::make('urdu.city')
                ->label('City Urdu')
                ->columnSpan(['lg' => 2]),

            Textarea::make('urdu.address')
                ->label('Address Urdu')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'customer_code' => null,
            'customer_name' => null,
            'mobile_number' => null,
            'country' => null,
            'city' => null,
            'full_address' => null,
            'credit_limit' => null,
            'opening_balance' => null,
            'bank_accounts' => [],
            'urdu' => [
                'customer_name' => null,
                'city' => null,
                'address' => null,
            ],
            'additional_contacts' => [],
            'documents' => [
                'profile_photo' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        return array_replace_recursive(static::defaultState(), $state);
    }

    public static function fromModel(Customer $customer): array
    {
        $customer->loadMissing(['bankAccounts', 'additionalContacts', 'document']);

        return static::normalizeState([
            'customer_code' => $customer->customer_code,
            'customer_name' => $customer->customer_name,
            'mobile_number' => $customer->mobile_number,
            'country' => $customer->country,
            'city' => $customer->city,
            'full_address' => $customer->full_address,
            'credit_limit' => $customer->credit_limit,
            'opening_balance' => $customer->opening_balance,
            'bank_accounts' => $customer->bankAccounts->map(fn ($account): array => [
                'bank_name' => $account->bank_name,
                'branch_name' => $account->branch_name,
                'account_title' => $account->account_title,
                'iban_account_number' => $account->iban_account_number,
            ])->all(),
            'urdu' => [
                'customer_name' => $customer->urdu_customer_name,
                'city' => $customer->urdu_city,
                'address' => $customer->urdu_address,
            ],
            'additional_contacts' => $customer->additionalContacts->map(fn ($contact): array => [
                'contact_person' => $contact->contact_person,
                'mobile_number' => $contact->mobile_number,
            ])->all(),
            'documents' => [
                'profile_photo' => $customer->document?->profile_photo_path,
            ],
        ]);
    }
}
