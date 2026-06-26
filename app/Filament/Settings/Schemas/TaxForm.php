<?php

namespace App\Filament\Settings\Schemas;

use App\Models\Tax;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaxForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?Tax $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-tax-form' . ($readOnly ? ' agricart-tax-form-readonly' : ''),
            ])
            ->components([
                Section::make('Tax Details')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->label('Tax Name')
                            ->placeholder('e.g. GST, Sales Tax, Withholding Tax')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        TextInput::make('code')
                            ->label('Tax Code')
                            ->placeholder('Optional short code')
                            ->maxLength(50),
                        Select::make('status')
                            ->label('Status')
                            ->options(config('tax.statuses', []))
                            ->default(Tax::STATUS_ACTIVE)
                            ->native(false)
                            ->required(),
                    ]),

                Section::make('Rate')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Select::make('type')
                            ->label('Tax Type')
                            ->options(config('tax.types', []))
                            ->default(Tax::TYPE_PERCENTAGE)
                            ->native(false)
                            ->required()
                            ->live(),
                        TextInput::make('rate_value')
                            ->label(fn (callable $get): string => $get('type') === Tax::TYPE_FIXED_AMOUNT
                                ? 'Fixed Amount'
                                : 'Tax Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->required()
                            ->suffix(fn (callable $get): ?string => $get('type') === Tax::TYPE_PERCENTAGE ? '%' : null),
                    ]),

                Section::make('Apply On')
                    ->compact()
                    ->description('Choose where this tax definition may be used in future modules.')
                    ->schema([
                        CheckboxList::make('apply_on')
                            ->label(null)
                            ->options(config('tax.apply_on', []))
                            ->columns(2)
                            ->bulkToggleable(! $readOnly)
                            ->required(),
                    ]),

                Section::make('Notes')
                    ->compact()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Summary')
                    ->compact()
                    ->visible($readOnly && $record !== null)
                    ->schema([
                        Placeholder::make('updated_at_display')
                            ->label('Last Updated')
                            ->content(fn (): string => (string) ($record?->updated_at?->toDateTimeString() ?? '—')),
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
            'code' => null,
            'type' => Tax::TYPE_PERCENTAGE,
            'rate_value' => null,
            'apply_on' => [],
            'status' => Tax::STATUS_ACTIVE,
            'notes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Tax $tax): array
    {
        return [
            'name' => $tax->name,
            'code' => $tax->code,
            'type' => $tax->type,
            'rate_value' => static::formatRateForForm($tax->rate_value),
            'apply_on' => $tax->apply_on ?? [],
            'status' => $tax->status,
            'notes' => $tax->notes,
            'updated_at_display' => $tax->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset($state['updated_at_display']);

        return $state;
    }

    protected static function formatRateForForm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) str_replace(',', '', (string) $value);

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
