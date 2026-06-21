<?php

namespace App\Filament\Contacts\Schemas;

use App\Models\Supplier;
use App\Support\Contacts\SupplierAuthorization;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
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
                'class' => 'agricart-supplier-form' . ($readOnly ? ' agricart-supplier-form-readonly' : ''),
            ])
            ->components([
                Section::make('Business Information')
                    ->compact()
                    ->extraAttributes(['class' => 'agricart-supplier-grid-section'])
                    ->columns(static::$gridColumns)
                    ->schema(static::businessInformationFields($readOnly))
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
                    ->extraAttributes(['class' => 'agricart-supplier-grid-section'])
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
                            ->columns(['default' => 1, 'lg' => 3])
                            ->schema([
                                static::documentUpload('documents.profile_photo', 'Profile Photo', $readOnly),
                                static::documentUpload('documents.card_front', 'Card Front Side', $readOnly),
                                static::documentUpload('documents.card_back', 'Card Back Side', $readOnly),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, TextInput|Select|Textarea>
     */
    protected static function businessInformationFields(bool $readOnly = false): array
    {
        return [
            TextInput::make('supplier_code')
                ->label('Supplier Code')
                ->disabled()
                ->dehydrated()
                ->placeholder('Auto Generated')
                ->columnSpan(['lg' => 2]),

            Select::make('supplier_type')
                ->label('Supplier Type')
                ->options([
                    'local' => 'Local Supplier',
                    'importer' => 'Importer',
                    'manufacturer' => 'Manufacturer',
                    'distributor' => 'Distributor',
                ])
                ->columnSpan(['lg' => 2]),

            Select::make('status')
                ->label('Status')
                ->options(config('contacts.supplier_statuses', []))
                ->default(Supplier::STATUS_ACTIVE)
                ->disabled($readOnly || ! SupplierAuthorization::canInactivate())
                ->dehydrated()
                ->columnSpan(['lg' => 2]),

            Select::make('country')
                ->label('Country')
                ->options(config('contacts.countries', []))
                ->searchable()
                ->required()
                ->columnSpan(['lg' => 2]),

            TextInput::make('city')
                ->label('City')
                ->required()
                ->columnSpan(['lg' => 2]),

            Textarea::make('full_address')
                ->label('Full Address')
                ->required()
                ->rows(1)
                ->extraAttributes(['class' => 'agricart-supplier-address-inline'])
                ->columnSpan(['lg' => 4]),

            TextInput::make('business_name')
                ->label('Business Name')
                ->required()
                ->columnSpan(['lg' => 4]),

            TextInput::make('contact_name')
                ->label('Contact Name')
                ->required()
                ->columnSpan(['lg' => 2]),

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
            ->acceptedFileTypes(config('contacts.supplier_document_types', [
                'image/jpeg',
                'image/png',
                'image/webp',
            ]))
            ->directory(config('contacts.supplier_documents_directory', 'contacts/suppliers/documents'))
            ->disk(config('contacts.supplier_documents_disk', 'local'))
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
            TextInput::make('urdu.business_name')
                ->label('Business Name Urdu')
                ->columnSpan(['lg' => 3]),

            TextInput::make('urdu.contact_name')
                ->label('Contact Name Urdu')
                ->columnSpan(['lg' => 3]),

            TextInput::make('urdu.city')
                ->label('City Urdu')
                ->columnSpan(['lg' => 2]),

            TextInput::make('urdu.account_title')
                ->label('Account Title Urdu')
                ->columnSpan(['lg' => 4]),

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
            'supplier_code' => null,
            'supplier_type' => null,
            'status' => Supplier::STATUS_ACTIVE,
            'country' => null,
            'city' => null,
            'business_name' => null,
            'contact_name' => null,
            'mobile_number' => null,
            'credit_limit' => null,
            'opening_balance' => null,
            'bank_accounts' => [
                [
                    'bank_name' => null,
                    'branch_name' => null,
                    'account_title' => null,
                    'iban_account_number' => null,
                ],
            ],
            'full_address' => null,
            'urdu' => [
                'business_name' => null,
                'contact_name' => null,
                'city' => null,
                'account_title' => null,
                'address' => null,
            ],
            'additional_contacts' => [],
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

        if ($state['bank_accounts'] === []) {
            $state['bank_accounts'] = static::defaultState()['bank_accounts'];
        }

        return $state;
    }

    public static function fromModel(Supplier $supplier): array
    {
        $supplier->loadMissing(['bankAccounts', 'additionalContacts', 'document']);

        return static::normalizeState([
            'supplier_code' => $supplier->supplier_code,
            'supplier_type' => $supplier->supplier_type,
            'status' => $supplier->status,
            'country' => $supplier->country,
            'city' => $supplier->city,
            'full_address' => $supplier->full_address,
            'business_name' => $supplier->business_name,
            'contact_name' => $supplier->contact_name,
            'mobile_number' => $supplier->mobile_number,
            'credit_limit' => $supplier->credit_limit,
            'opening_balance' => $supplier->opening_balance,
            'bank_accounts' => $supplier->bankAccounts->map(fn ($account): array => [
                'bank_name' => $account->bank_name,
                'branch_name' => $account->branch_name,
                'account_title' => $account->account_title,
                'iban_account_number' => $account->iban_account_number,
            ])->all(),
            'urdu' => [
                'business_name' => $supplier->urdu_business_name,
                'contact_name' => $supplier->urdu_contact_name,
                'city' => $supplier->urdu_city,
                'account_title' => $supplier->urdu_account_title,
                'address' => $supplier->urdu_address,
            ],
            'additional_contacts' => $supplier->additionalContacts->map(fn ($contact): array => [
                'contact_person' => $contact->contact_person,
                'mobile_number' => $contact->mobile_number,
            ])->all(),
            'documents' => [
                'profile_photo' => $supplier->document?->profile_photo_path,
                'card_front' => $supplier->document?->card_front_path,
                'card_back' => $supplier->document?->card_back_path,
            ],
        ]);
    }
}
