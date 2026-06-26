<?php

namespace App\Filament\OnlineStore\Schemas;

use App\Services\OnlineStore\StorePageLinkResolver;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class StoreFrontSettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(self::components());
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Component>
     */
    public static function components(): array
    {
        return [
            Tabs::make('StoreFrontSettings')
                    ->tabs([
                        Tab::make('Top Bar & Ticker')
                            ->icon(Heroicon::OutlinedBars3BottomLeft)
                            ->schema([
                                TextInput::make('top_bar_left')->label('Top Bar Left')->maxLength(120),
                                TextInput::make('top_bar_center')->label('Top Bar Center')->maxLength(255),
                                TextInput::make('top_bar_right')->label('Top Bar Right')->maxLength(120),
                                Textarea::make('ticker_en')->label('Ticker (English)')->rows(2),
                                Textarea::make('ticker_ur')->label('Ticker (Urdu)')->rows(2)->extraAttributes(['dir' => 'rtl']),
                            ]),
                        Tab::make('Social & Access')
                            ->icon(Heroicon::OutlinedShare)
                            ->schema([
                                Repeater::make('social_links')
                                    ->label('Social Media Links')
                                    ->schema([
                                        Select::make('platform')
                                            ->label('Platform')
                                            ->options(config('online-store.social_platforms', []))
                                            ->required()
                                            ->native(false),
                                        TextInput::make('url')
                                            ->label('Profile URL')
                                            ->url()
                                            ->required()
                                            ->maxLength(500),
                                    ])
                                    ->addActionLabel('Add social link')
                                    ->reorderable()
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Homepage')
                            ->icon(Heroicon::OutlinedSquares2x2)
                            ->schema([
                                Select::make('homepage_categories_per_row')
                                    ->label('Homepage Categories Per Row')
                                    ->options(config('online-store.homepage_categories_per_row_options', []))
                                    ->required()
                                    ->native(false)
                                    ->helperText('Desktop layout only. Tablet stays at 2 columns; mobile stays at 1 column.'),
                            ]),
                        Tab::make('Header Navigation')
                            ->icon(Heroicon::OutlinedLink)
                            ->schema([
                                Repeater::make('header_navigation')
                                    ->label('Navigation Links')
                                    ->schema(self::pageLinkFields())
                                    ->addActionLabel('Add navigation link')
                                    ->reorderable()
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                                Placeholder::make('header_navigation_hint')
                                    ->hiddenLabel()
                                    ->content('Pick a page here. The menu uses the page title automatically. Draft pages are shown for selection but only appear on the storefront after publishing.'),
                            ]),
                        Tab::make('Footer & Contact')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Placeholder::make('footer_logo_preview')
                                    ->label('Current logo')
                                    ->content(fn ($get): HtmlString => filled($get('_footer_logo_preview'))
                                        ? new HtmlString('<img src="' . e($get('_footer_logo_preview')) . '" alt="" style="max-height:72px;border-radius:8px;" />')
                                        : new HtmlString('<span class="text-sm text-gray-500">No footer logo uploaded.</span>')),
                                FileUpload::make('footer_logo')
                                    ->label('Replace logo')
                                    ->disk(config('online-store.footer_logo_disk', 'local'))
                                    ->directory(config('online-store.footer_logo_directory', 'online-store/footer'))
                                    ->image()
                                    ->maxFiles(1)
                                    ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png'])
                                    ->helperText('Upload WebP, JPEG, or PNG. Save changes after selecting the file.')
                                    ->columnSpanFull(),
                                Toggle::make('footer_logo_removed')
                                    ->label('Remove current logo')
                                    ->helperText('When enabled, the public site uses a text fallback instead of the logo.'),
                                Textarea::make('footer_about_en')->label('About Store Text (English)')->rows(3)->required(),
                                Textarea::make('footer_about_ur')->label('About Store Text (Urdu)')->rows(3)->extraAttributes(['dir' => 'rtl']),
                                Repeater::make('footer_quick_links')
                                    ->label('Quick Links')
                                    ->schema(self::pageLinkFields())
                                    ->addActionLabel('Add quick link')
                                    ->reorderable()
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                                Repeater::make('footer_legal_links')
                                    ->label('Legal Links')
                                    ->schema(self::pageLinkFields())
                                    ->addActionLabel('Add legal link')
                                    ->reorderable()
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),
                                TextInput::make('contact_email')->label('Contact Email')->email()->maxLength(255),
                                TextInput::make('contact_phone')->label('Contact Phone')->maxLength(32),
                                Textarea::make('map_embed_url')
                                    ->label('Google Map Embed')
                                    ->rows(3)
                                    ->helperText('Paste the full Google Maps embed iframe code, or only the embed URL.'),
                                TextInput::make('copyright_line')->label('Copyright Line')->maxLength(255)->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    protected static function pageLinkFields(): array
    {
        return [
            Select::make('store_page_id')
                ->label('Page')
                ->options(fn (): array => app(StorePageLinkResolver::class)->pageOptionsForPicker())
                ->getSearchResultsUsing(fn (string $search): array => app(StorePageLinkResolver::class)->searchPagesForPicker($search))
                ->getOptionLabelUsing(fn ($value): ?string => app(StorePageLinkResolver::class)->pagePickerLabel($value))
                ->searchable()
                ->searchLabels(false)
                ->preload()
                ->required()
                ->native(false),
        ];
    }
}
