<?php

namespace App\Filament\Settings\Schemas;

use App\Models\CompanySetting;
use App\Services\Settings\CompanySettingLogoStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class CompanySettingForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?CompanySetting $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-company-setting-form' . ($readOnly ? ' agricart-company-setting-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->extraAttributes(['class' => 'agricart-company-setting-entry-row'])
                    ->schema([
                        Group::make()
                            ->schema([
                                FileUpload::make('logo')
                                    ->hiddenLabel()
                                    ->disk(config('settings.logo_disk', 'public'))
                                    ->directory('company-settings')
                                    ->image()
                                    ->imagePreviewHeight('160')
                                    ->panelLayout('compact')
                                    ->disabled($readOnly),
                            ])
                            ->extraAttributes(['class' => 'agricart-company-setting-logo-upload']),

                        Group::make()
                            ->schema([
                                TextInput::make('name_en')
                                    ->label('English Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('name_ur')
                                    ->label('Urdu Name')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Address')
                    ->compact()
                    ->columns(1)
                    ->schema([
                        Textarea::make('address_en')
                            ->label('English Address')
                            ->rows(2)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                        Textarea::make('address_ur')
                            ->label('Urdu Address')
                            ->rows(2)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contact')
                    ->compact()
                    ->columns(1)
                    ->schema([
                        Fieldset::make('Phones')
                            ->schema([
                                Repeater::make('phones')
                                    ->label(null)
                                    ->columns(['default' => 1, 'lg' => 2])
                                    ->addActionLabel('Add Phone')
                                    ->addable(! $readOnly)
                                    ->deletable(! $readOnly)
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('contact_person')
                                            ->label('Contact Person')
                                            ->maxLength(150),
                                        TextInput::make('phone_number')
                                            ->label('Phone')
                                            ->tel()
                                            ->maxLength(30),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Fieldset::make('WhatsApp Numbers')
                            ->schema([
                                Repeater::make('whatsapp_numbers')
                                    ->label(null)
                                    ->columns(['default' => 1, 'lg' => 2])
                                    ->addActionLabel('Add WhatsApp')
                                    ->addable(! $readOnly)
                                    ->deletable(! $readOnly)
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('contact_person')
                                            ->label('Contact Person')
                                            ->maxLength(150),
                                        TextInput::make('whatsapp_number')
                                            ->label('WhatsApp')
                                            ->tel()
                                            ->maxLength(30),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Fieldset::make('Email Addresses')
                            ->schema([
                                Repeater::make('emails')
                                    ->label(null)
                                    ->addActionLabel('Add Email')
                                    ->addable(! $readOnly)
                                    ->deletable(! $readOnly)
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make('Business')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('website_url')
                            ->label('Website')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('ntn')
                            ->label('NTN')
                            ->maxLength(30),
                        TextInput::make('strn')
                            ->label('STRN')
                            ->maxLength(30),
                    ]),

                Section::make('Regional')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 3])
                    ->schema([
                        Select::make('currency')
                            ->label('Currency')
                            ->options(config('settings.currencies', []))
                            ->default('PKR')
                            ->native(false)
                            ->required(),
                        Select::make('decimal_places')
                            ->label('Decimal Places')
                            ->options(config('settings.decimal_places', []))
                            ->default(0)
                            ->native(false)
                            ->required(),
                        Select::make('timezone')
                            ->label('Timezone')
                            ->options(config('settings.timezones', []))
                            ->default('Asia/Karachi')
                            ->native(false)
                            ->required(),
                    ]),

                Section::make('Summary')
                    ->compact()
                    ->visible($readOnly && $record !== null)
                    ->schema([
                        Placeholder::make('updated_at_display')
                            ->label('Last Updated')
                            ->content(fn (): string => (string) ($record?->updated_at?->toDateTimeString() ?? '—')),
                        Placeholder::make('logo_preview')
                            ->label('Logo Preview')
                            ->content(fn (): HtmlString => static::logoPreviewHtml($record?->logo_path)),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'logo' => null,
            'name_en' => (string) config('agricart.brand.name', 'Agricart ERP'),
            'name_ur' => '',
            'address_en' => null,
            'address_ur' => null,
            'phones' => [],
            'whatsapp_numbers' => [],
            'emails' => [],
            'website_url' => null,
            'ntn' => null,
            'strn' => null,
            'currency' => 'PKR',
            'decimal_places' => 0,
            'timezone' => 'Asia/Karachi',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(CompanySetting $setting): array
    {
        return [
            'logo' => $setting->logo_path,
            'name_en' => $setting->name_en,
            'name_ur' => $setting->name_ur,
            'address_en' => $setting->address_en,
            'address_ur' => $setting->address_ur,
            'phones' => static::repeaterFromPhoneEntries($setting->phones ?? []),
            'whatsapp_numbers' => static::repeaterFromWhatsAppEntries($setting->whatsapp_numbers ?? []),
            'emails' => static::repeaterFromList($setting->emails ?? [], 'email'),
            'website_url' => $setting->website_url,
            'ntn' => $setting->ntn,
            'strn' => $setting->strn,
            'currency' => $setting->currency,
            'decimal_places' => $setting->decimal_places,
            'timezone' => $setting->timezone,
            'updated_at_display' => $setting->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset($state['updated_at_display'], $state['logo_preview']);

        return $state;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<array<string, string>>
     */
    protected static function repeaterFromPhoneEntries(array $values): array
    {
        return collect($values)
            ->map(function (mixed $value): array {
                if (is_string($value)) {
                    return ['contact_person' => '', 'phone_number' => $value];
                }

                if (is_array($value)) {
                    return [
                        'contact_person' => (string) ($value['contact_person'] ?? ''),
                        'phone_number' => (string) ($value['phone_number'] ?? $value['number'] ?? ''),
                    ];
                }

                return ['contact_person' => '', 'phone_number' => ''];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<mixed>  $values
     * @return list<array<string, string>>
     */
    protected static function repeaterFromWhatsAppEntries(array $values): array
    {
        return collect($values)
            ->map(function (mixed $value): array {
                if (is_string($value)) {
                    return ['contact_person' => '', 'whatsapp_number' => $value];
                }

                if (is_array($value)) {
                    return [
                        'contact_person' => (string) ($value['contact_person'] ?? ''),
                        'whatsapp_number' => (string) ($value['whatsapp_number'] ?? $value['number'] ?? ''),
                    ];
                }

                return ['contact_person' => '', 'whatsapp_number' => ''];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $values
     * @return list<array<string, string>>
     */
    protected static function repeaterFromList(array $values, string $key): array
    {
        return collect($values)
            ->map(fn (mixed $value): array => [$key => (string) $value])
            ->values()
            ->all();
    }

    public static function logoPreviewHtml(?string $logoPath): HtmlString
    {
        $url = app(CompanySettingLogoStorage::class)->url($logoPath);

        if (blank($url)) {
            return new HtmlString('<div class="agricart-company-setting-logo-preview__placeholder">No logo</div>');
        }

        return new HtmlString(
            '<div class="agricart-company-setting-logo-preview">'
            . '<img src="' . e($url) . '" alt="Company logo" loading="lazy">'
            . '</div>',
        );
    }
}
