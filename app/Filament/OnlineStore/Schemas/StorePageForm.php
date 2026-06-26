<?php

namespace App\Filament\OnlineStore\Schemas;

use App\Models\OnlineStore\StorePage;
use App\Services\OnlineStore\StorePageSlugService;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class StorePageForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?StorePage $record = null): Schema
    {
        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->components([
                Section::make('Page identity')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title_en')
                                ->label('Title (English)')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (?string $state, callable $set, callable $get) use ($record): void {
                                    if ($record !== null || filled($get('slug'))) {
                                        return;
                                    }

                                    if (blank($state)) {
                                        return;
                                    }

                                    $set('slug', app(StorePageSlugService::class)->generate($state));
                                }),
                            TextInput::make('title_ur')
                                ->label('Title (Urdu)')
                                ->required()
                                ->maxLength(255),
                        ]),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(120)
                            ->helperText('URL segment, e.g. about-us. Auto-generated from the English title on create.')
                            ->dehydrateStateUsing(fn (?string $state): string => Str::slug((string) $state)),
                        Toggle::make('is_published')
                            ->label('Published')
                            ->helperText('Off = Draft · On = Published')
                            ->default(false),
                    ]),
                Section::make('Page content')
                    ->schema([
                        Grid::make(2)->schema([
                            RichEditor::make('content_en')
                                ->label('Content (English)')
                                ->columnSpan(1),
                            RichEditor::make('content_ur')
                                ->label('Content (Urdu)')
                                ->extraAttributes(['class' => 'agricart-store-page-editor-rtl'])
                                ->columnSpan(1),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'title_en' => '',
            'title_ur' => '',
            'slug' => '',
            'content_en' => null,
            'content_ur' => null,
            'is_published' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(StorePage $page): array
    {
        return [
            'title_en' => $page->title_en,
            'title_ur' => $page->title_ur,
            'slug' => $page->slug,
            'content_en' => $page->content_en,
            'content_ur' => $page->content_ur,
            'is_published' => (bool) $page->is_published,
        ];
    }
}
