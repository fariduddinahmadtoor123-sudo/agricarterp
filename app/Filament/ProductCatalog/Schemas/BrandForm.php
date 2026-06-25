<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\Brand;
use App\Rules\UniqueBrandEnglishName;
use App\Services\ProductCatalog\BrandCategoryQuery;
use App\Services\ProductCatalog\BrandLogoStorage;
use App\Support\ProductCatalog\BrandFilenameNameFormatter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BrandForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?Brand $record = null): Schema
    {
        $categories = app(BrandCategoryQuery::class);

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-brand-form' . ($readOnly ? ' agricart-brand-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        Group::make()
                            ->schema([
                                FileUpload::make('logo')
                                    ->hiddenLabel()
                                    ->disk(config('product-catalog.brand_logo_disk', 'local'))
                                    ->directory('brands')
                                    ->image()
                                    ->imagePreviewHeight('160')
                                    ->panelLayout('compact')
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, callable $set, callable $get) use ($record): void {
                                        $name = BrandFilenameNameFormatter::fromNewUpload($state);

                                        if ($name === null || $name === '') {
                                            return;
                                        }

                                        if ($record === null) {
                                            $set('name_en', $name);

                                            return;
                                        }

                                        if (blank($get('name_en'))) {
                                            $set('name_en', $name);
                                        }
                                    }),
                            ])
                            ->extraAttributes([
                                'class' => 'agricart-brand-logo-upload',
                            ]),

                        Group::make()
                            ->schema([
                                TextInput::make('name_en')
                                    ->label('English Brand Name')
                                    ->placeholder('Auto-filled from logo filename')
                                    ->required()
                                    ->maxLength(255)
                                    ->rules($readOnly ? [] : [new UniqueBrandEnglishName($record)])
                                    ->validationMessages([
                                        'required' => 'English brand name is required.',
                                    ])
                                    ->columnSpanFull(),

                                Textarea::make('short_note')
                                    ->label('Short Note')
                                    ->placeholder('Brief note about the brand for AI enrichment')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Select::make('category_ids')
                                    ->label('Assigned Categories')
                                    ->placeholder('Search categories...')
                                    ->multiple()
                                    ->searchable()
                                    ->native(false)
                                    ->options(fn (): array => $categories->activeCategoryOptions())
                                    ->getSearchResultsUsing(fn (string $search): array => $categories->searchActiveCategories($search))
                                    ->getOptionLabelsUsing(function (array $values) use ($categories): array {
                                        $labels = [];

                                        foreach ($values as $value) {
                                            $labels[$value] = $categories->categoryLabel($value) ?? (string) $value;
                                        }

                                        return $labels;
                                    })
                                    ->helperText('Classify what types of products this brand manufactures.')
                                    ->columnSpanFull(),
                            ])
                            ->extraAttributes([
                                'class' => 'agricart-brand-entry-fields',
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'agricart-brand-entry-row',
                    ]),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('brand_number_display')
                            ->label('Brand Number')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('categories_count_display')
                            ->label('Assigned Categories')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_status_display')
                            ->label('AI Status')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_generated_at_display')
                            ->label('AI Generated At')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_version_display')
                            ->label('AI Version')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columnSpanFull(),

                Section::make('Additional Information')
                    ->description('AI-generated and review content. Not required during fast brand entry.')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        Fieldset::make('Urdu Identity')
                            ->schema([
                                TextInput::make('name_ur')
                                    ->label('Urdu Brand Name')
                                    ->maxLength(255)
                                    ->helperText('Optional during entry. AI will generate Urdu content later.'),
                            ]),

                        Fieldset::make('Descriptions')
                            ->schema([
                                Textarea::make('short_description_en')->label('Short Description (English)')->rows(2),
                                Textarea::make('short_description_ur')->label('Short Description (Urdu)')->rows(2),
                                Textarea::make('description_en')->label('Long Description (English)')->rows(4),
                                Textarea::make('description_ur')->label('Long Description (Urdu)')->rows(4),
                                Textarea::make('brand_overview_en')->label('Brand Overview (English)')->rows(4),
                            ]),

                        Fieldset::make('SEO')
                            ->schema([
                                TextInput::make('seo_title')->label('SEO Title')->maxLength(255),
                                Textarea::make('seo_description')->label('SEO Description')->rows(3),
                                Textarea::make('seo_keywords')->label('SEO Keywords')->rows(2),
                            ]),

                        Fieldset::make('Company Info')
                            ->schema([
                                TextInput::make('country')->label('Country')->maxLength(100),
                                TextInput::make('website')->label('Website')->maxLength(500),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'logo' => null,
            'name_en' => null,
            'short_note' => null,
            'category_ids' => [],
            'name_ur' => null,
            'short_description_en' => null,
            'short_description_ur' => null,
            'description_en' => null,
            'description_ur' => null,
            'brand_overview_en' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'country' => null,
            'website' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Brand $brand): array
    {
        return [
            'logo' => $brand->logo_path,
            'name_en' => $brand->name_en,
            'short_note' => $brand->short_note,
            'category_ids' => $brand->categories()->pluck('categories.id')->all(),
            'name_ur' => $brand->name_ur,
            'short_description_en' => $brand->short_description_en,
            'short_description_ur' => $brand->short_description_ur,
            'description_en' => $brand->description_en,
            'description_ur' => $brand->description_ur,
            'brand_overview_en' => $brand->brand_overview_en,
            'seo_title' => $brand->seo_title,
            'seo_description' => $brand->seo_description,
            'seo_keywords' => $brand->seo_keywords,
            'country' => $brand->country,
            'website' => $brand->website,
            'brand_number_display' => $brand->brand_number,
            'categories_count_display' => (string) $brand->categories_count,
            'ai_status_display' => config('product-catalog.brand_ai_statuses')[$brand->ai_status] ?? ucfirst($brand->ai_status),
            'ai_generated_at_display' => $brand->ai_generated_at?->toDateTimeString(),
            'ai_version_display' => $brand->ai_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset(
            $state['brand_number_display'],
            $state['categories_count_display'],
            $state['ai_status_display'],
            $state['ai_generated_at_display'],
            $state['ai_version_display'],
        );

        return $state;
    }

    public static function logoPreviewHtml(?string $logoPath): HtmlString
    {
        $url = app(BrandLogoStorage::class)->url($logoPath);

        if (blank($url)) {
            return new HtmlString(
                '<div class="agricart-brand-logo-preview__placeholder">No logo</div>',
            );
        }

        return new HtmlString(
            '<div class="agricart-brand-logo-preview">'
            . '<img src="' . e($url) . '" alt="Brand logo" loading="lazy">'
            . '</div>',
        );
    }
}
