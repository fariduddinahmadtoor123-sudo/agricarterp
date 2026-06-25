<?php

namespace App\Filament\Settings\Schemas;

use App\Models\PrintingSetting;
use App\Services\Settings\PrintingSettingResolver;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PrintingSettingForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?PrintingSetting $record = null): Schema
    {
        $resolver = app(PrintingSettingResolver::class);

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-printing-setting-form' . ($readOnly ? ' agricart-printing-setting-form-readonly' : ''),
            ])
            ->components([
                Section::make('Invoice & Document Paper')
                    ->compact()
                    ->description('Controls page size when you press Print in the browser (A4, Legal, etc.). Your physical printer is still chosen in the browser print dialog — this only sets the layout size.')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Select::make('default_document_paper')
                            ->label('Default Document Paper')
                            ->options($resolver->documentPaperOptions())
                            ->required()
                            ->native(false),
                        Select::make('default_purchase_invoice_paper')
                            ->label('Default Purchase Invoice Paper')
                            ->options($resolver->documentPaperOptions())
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Barcode / Price Tag Labels')
                    ->compact()
                    ->description('Sticker width and height in millimetres for barcode label layout. Match the roll loaded in your label printer. Browser print → you pick the label printer; ERP only sizes each sticker on screen.')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        Select::make('price_tag_label_preset')
                            ->label('Label Size Preset')
                            ->options($resolver->labelPresetOptions())
                            ->required()
                            ->live()
                            ->native(false),
                        Select::make('price_tag_sheet_paper')
                            ->label('Print Layout Sheet')
                            ->options(config('printing.label_sheet_papers', []))
                            ->required()
                            ->native(false),
                        TextInput::make('price_tag_width_mm')
                            ->label('Label Width (mm)')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(200)
                            ->step(0.1)
                            ->suffix('mm')
                            ->required()
                            ->visible(fn (callable $get): bool => $get('price_tag_label_preset') === 'custom'),
                        TextInput::make('price_tag_height_mm')
                            ->label('Label Height (mm)')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(200)
                            ->step(0.1)
                            ->suffix('mm')
                            ->required()
                            ->visible(fn (callable $get): bool => $get('price_tag_label_preset') === 'custom'),
                        TextInput::make('price_tag_gap_mm')
                            ->label('Gap Between Labels (mm)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(20)
                            ->step(0.1)
                            ->suffix('mm')
                            ->required()
                            ->visible(fn (callable $get): bool => $get('price_tag_label_preset') === 'custom'),
                        Textarea::make('barcode_printer_note')
                            ->label('Barcode Printer — Current Sticker')
                            ->placeholder('e.g. 38×25 mm thermal labels loaded in Zebra printer')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('POS / Thermal Receipt')
                    ->compact()
                    ->description('Receipt roll width (80 mm ≈ 3 inch). Used when POS receipt printing is built — browser print dialog still selects the thermal printer. ERP prepares a narrow receipt layout.')
                    ->schema([
                        Select::make('pos_receipt_profile')
                            ->label('Receipt Roll Width')
                            ->options($resolver->thermalReceiptOptions())
                            ->required()
                            ->native(false),
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
        $defaults = config('printing.defaults', []);

        return [
            'default_document_paper' => $defaults['default_document_paper'] ?? 'a4',
            'default_purchase_invoice_paper' => $defaults['default_purchase_invoice_paper'] ?? 'a4',
            'price_tag_label_preset' => $defaults['price_tag_label_preset'] ?? '38x25',
            'price_tag_width_mm' => $defaults['price_tag_width_mm'] ?? '38',
            'price_tag_height_mm' => $defaults['price_tag_height_mm'] ?? '25',
            'price_tag_gap_mm' => $defaults['price_tag_gap_mm'] ?? '3',
            'price_tag_sheet_paper' => $defaults['price_tag_sheet_paper'] ?? 'a4',
            'barcode_printer_note' => $defaults['barcode_printer_note'] ?? null,
            'pos_receipt_profile' => $defaults['pos_receipt_profile'] ?? '80mm',
        ];
    }

    public static function fromModel(PrintingSetting $setting): array
    {
        return [
            'default_document_paper' => $setting->default_document_paper,
            'default_purchase_invoice_paper' => $setting->default_purchase_invoice_paper,
            'price_tag_label_preset' => $setting->price_tag_label_preset,
            'price_tag_width_mm' => $setting->price_tag_width_mm,
            'price_tag_height_mm' => $setting->price_tag_height_mm,
            'price_tag_gap_mm' => $setting->price_tag_gap_mm,
            'price_tag_sheet_paper' => $setting->price_tag_sheet_paper,
            'barcode_printer_note' => $setting->barcode_printer_note,
            'pos_receipt_profile' => $setting->pos_receipt_profile,
        ];
    }
}
