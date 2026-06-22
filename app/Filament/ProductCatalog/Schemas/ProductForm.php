<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Rules\UniqueProductEnglishNamePerBrand;
use App\Services\ProductCatalog\CategoryImageStorage;
use App\Services\ProductCatalog\ProductCatalogMasterQuery;
use App\Services\ProductCatalog\ProductCategoryQuery;
use App\Services\ProductCatalog\ProductControlAssignmentService;
use App\Services\ProductCatalog\ProductControlGroupQuery;
use App\Services\ProductCatalog\ProductControlQuery;
use App\Support\ProductCatalog\ProductFilenameNameFormatter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?Product $record = null): Schema
    {
        $categories = app(ProductCategoryQuery::class);
        $masters = app(ProductCatalogMasterQuery::class);
        $controls = app(ProductControlQuery::class);
        $groups = app(ProductControlGroupQuery::class);
        $assignment = app(ProductControlAssignmentService::class);

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-product-form' . ($readOnly ? ' agricart-product-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        Group::make()
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Select::make('category_id')
                                    ->label('Primary Category')
                                    ->placeholder('Search categories...')
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->options(fn (): array => $categories->activeLeafCategoryOptions())
                                    ->getSearchResultsUsing(fn (string $search): array => $categories->searchActiveLeafCategories($search))
                                    ->getOptionLabelUsing(fn ($value): ?string => $categories->categoryNameLabel($value))
                                    ->validationMessages([
                                        'required' => 'Primary category is required.',
                                    ])
                                    ->columnSpanFull(),

                                Placeholder::make('category_path_display')
                                    ->hiddenLabel()
                                    ->content(function ($get) use ($categories): HtmlString {
                                        $path = $categories->categoryPathLabel($get('category_id'));

                                        if (blank($path)) {
                                            return new HtmlString('<span class="agricart-product-category-breadcrumb agricart-product-category-breadcrumb--empty">Select a leaf category.</span>');
                                        }

                                        return new HtmlString(
                                            '<span class="agricart-product-category-breadcrumb">' . e($path) . '</span>',
                                        );
                                    })
                                    ->columnSpanFull(),

                                Placeholder::make('category_gallery_display')
                                    ->hiddenLabel()
                                    ->content(function ($get): HtmlString {
                                        $categoryId = $get('category_id');

                                        if (blank($categoryId)) {
                                            return new HtmlString(
                                                '<div class="agricart-product-category-hierarchy-row agricart-product-category-hierarchy-row--empty">'
                                                . '<span class="agricart-product-category-hierarchy-empty">Category hierarchy preview</span>'
                                                . '</div>',
                                            );
                                        }

                                        $category = Category::query()->find($categoryId);

                                        if ($category === null) {
                                            return new HtmlString('');
                                        }

                                        return new HtmlString(self::categoryHierarchyGalleryHtml($category));
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->extraAttributes(['class' => 'agricart-product-intro-category'])
                            ->columnSpan(['default' => 1, 'lg' => 1]),

                        Group::make()
                            ->schema([
                                FileUpload::make('main_image')
                                    ->label('Main')
                                    ->disk(config('product-catalog.product_image_disk', 'local'))
                                    ->directory('products')
                                    ->image()
                                    ->imagePreviewHeight('150')
                                    ->panelLayout('compact')
                                    ->required($record === null)
                                    ->live()
                                    ->afterStateUpdated(function (mixed $state, callable $set, callable $get) use ($record): void {
                                        $name = ProductFilenameNameFormatter::fromNewUpload($state);

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
                                    })
                                    ->extraAttributes(['class' => 'agricart-product-main-image']),

                                Repeater::make('additional_images')
                                    ->hiddenLabel()
                                    ->schema([
                                        FileUpload::make('image')
                                            ->hiddenLabel()
                                            ->disk(config('product-catalog.product_image_disk', 'local'))
                                            ->directory('products')
                                            ->image()
                                            ->imagePreviewHeight('150')
                                            ->panelLayout('compact')
                                            ->required(),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('+')
                                    ->reorderable()
                                    ->extraAttributes(['class' => 'agricart-product-additional-images']),
                            ])
                            ->extraAttributes(['class' => 'agricart-product-intro-images'])
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'agricart-product-intro-row']),
            ])
            ->extraAttributes(['class' => 'agricart-product-block agricart-product-block--intro']),

                Group::make()
                    ->schema([
                        TextInput::make('name_en')
                            ->label('English Name')
                            ->placeholder('Auto from image filename')
                            ->required()
                            ->maxLength(500)
                            ->live(onBlur: true)
                            ->rules($readOnly ? [] : fn ($get) => [
                                new UniqueProductEnglishNamePerBrand($get('brand_id'), $record),
                            ]),

                        Select::make('brand_id')
                            ->label('Brand')
                            ->placeholder('Brand')
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->options(fn (): array => $masters->activeBrandOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $masters->searchActiveBrands($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $masters->brandLabel($value)),

                        TextInput::make('name_ur')
                            ->label('Urdu Name')
                            ->placeholder('AI later')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('base_unit_id')
                            ->label('Base Unit')
                            ->placeholder('Base unit')
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->options(fn (): array => $masters->activeUnitOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $masters->searchActiveUnits($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $masters->unitLabel($value)),

                        TextInput::make('packing_value')
                            ->label('Pack Qty')
                            ->numeric()
                            ->required()
                            ->minValue(0.0001)
                            ->live(onBlur: true),

                        Select::make('packing_unit_id')
                            ->label('Pack Unit')
                            ->placeholder('Pack unit')
                            ->required()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->options(fn (): array => $masters->activeUnitOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $masters->searchActiveUnits($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $masters->unitLabel($value)),

                        TextInput::make('required_quantity')
                            ->label('Required Qty')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        TextInput::make('alert_quantity')
                            ->label('Alert Qty')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->extraAttributes(['class' => 'agricart-product-block agricart-product-block--core']),

                Group::make()
                    ->schema([
                        Select::make('display_category_ids')
                            ->label('Display Categories')
                            ->placeholder('Search display categories...')
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->options(fn ($get): array => $categories->activeDisplayCategoryOptions($get('category_id')))
                            ->getSearchResultsUsing(fn (string $search, $get): array => $categories->searchActiveDisplayCategories($search, $get('category_id')))
                            ->getOptionLabelsUsing(function (array $values) use ($categories): array {
                                $labels = [];

                                foreach ($values as $value) {
                                    $labels[$value] = $categories->categoryNameLabel($value) ?? (string) $value;
                                }

                                return $labels;
                            })
                            ->extraAttributes(['class' => 'agricart-product-display-categories']),

                        Repeater::make('attribute_rows')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('attribute_id')
                                    ->hiddenLabel()
                                    ->placeholder('Attribute')
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->options(fn (): array => Attribute::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                TextInput::make('value')
                                    ->hiddenLabel()
                                    ->placeholder('Value')
                                    ->required()
                                    ->maxLength(500),
                            ])
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('+')
                            ->columns(2)
                            ->extraAttributes(['class' => 'agricart-product-attribute-repeater']),
                    ])
                    ->extraAttributes(['class' => 'agricart-product-block agricart-product-tags-attributes']),

                Group::make()
                    ->schema([
                        Select::make('control_group_ids')
                            ->label('Control Groups')
                            ->placeholder('Groups...')
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->options(fn (): array => $groups->activeGroupOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $groups->searchActiveGroups($search))
                            ->getOptionLabelsUsing(function (array $values) use ($groups): array {
                                $labels = [];

                                foreach ($values as $value) {
                                    $labels[$value] = $groups->groupLabel($value) ?? (string) $value;
                                }

                                return $labels;
                            }),

                        Select::make('individual_control_ids')
                            ->label('Individual Controls')
                            ->placeholder('Controls...')
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->hint(function ($get) use ($assignment): ?string {
                                $labels = $assignment->effectiveControlLabels(
                                    array_map(fn ($id): int => (int) $id, $get('control_group_ids') ?? []),
                                    array_map(fn ($id): int => (int) $id, $get('individual_control_ids') ?? []),
                                );

                                if ($labels === []) {
                                    return null;
                                }

                                return count($labels) . ' effective control(s)';
                            })
                            ->options(fn (): array => $controls->activeControlOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $controls->searchActiveControls($search))
                            ->getOptionLabelsUsing(function (array $values) use ($controls): array {
                                $labels = [];

                                foreach ($values as $value) {
                                    $labels[$value] = $controls->controlLabel($value) ?? (string) $value;
                                }

                                return $labels;
                            }),
                    ])
                    ->columns(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'agricart-product-block agricart-product-block--controls']),

                Section::make('Additional Information')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('SEO Title')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('seo_focus_keyword')
                            ->label('SEO Focus Keyword')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('seo_description')
                            ->label('SEO Description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('seo_keywords')
                            ->label('SEO Keywords')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('short_description_en')
                            ->label('Short Description (EN)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('short_description_ur')
                            ->label('Short Description (UR)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('description_en')
                            ->label('Description (EN)')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('description_ur')
                            ->label('Description (UR)')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('usage_en')
                            ->label('Usage (EN)')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('usage_ur')
                            ->label('Usage (UR)')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('hs_code')
                            ->label('HS Code')
                            ->maxLength(20)
                            ->columnSpanFull(),
                    ]),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->schema([
                        TextInput::make('product_number_display')
                            ->label('Product Number')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_status_display')
                            ->label('AI Status')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(['default' => 1, 'lg' => 2]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return [
            'category_id' => null,
            'main_image' => null,
            'additional_images' => [],
            'name_en' => null,
            'brand_id' => null,
            'base_unit_id' => null,
            'packing_value' => null,
            'packing_unit_id' => null,
            'required_quantity' => 0,
            'alert_quantity' => 0,
            'display_category_ids' => [],
            'attribute_rows' => self::emptyAttributeRowState(),
            'control_group_ids' => [],
            'individual_control_ids' => [],
            'short_description_en' => null,
            'short_description_ur' => null,
            'description_en' => null,
            'description_ur' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'seo_focus_keyword' => null,
            'hs_code' => null,
            'usage_en' => null,
            'usage_ur' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Product $product): array
    {
        $product->loadMissing([
            'images',
            'attributeValues',
            'categoryTags',
            'controlGroups',
            'individualControls',
        ]);

        $mainImage = $product->images->firstWhere('is_main', true);

        return [
            'category_id' => $product->category_id,
            'main_image' => $mainImage?->image_path,
            'additional_images' => $product->images
                ->where('is_main', false)
                ->values()
                ->map(fn ($image): array => ['image' => $image->image_path])
                ->all(),
            'name_en' => $product->name_en,
            'brand_id' => $product->brand_id,
            'base_unit_id' => $product->base_unit_id,
            'packing_value' => $product->packing_value,
            'packing_unit_id' => $product->packing_unit_id,
            'required_quantity' => $product->required_quantity,
            'alert_quantity' => $product->alert_quantity,
            'display_category_ids' => $product->categoryTags->pluck('id')->all(),
            'attribute_rows' => self::attributeRowsFromModel($product),
            'control_group_ids' => $product->controlGroups->pluck('id')->all(),
            'individual_control_ids' => $product->individualControls->pluck('id')->all(),
            'short_description_en' => $product->short_description_en,
            'short_description_ur' => $product->short_description_ur,
            'description_en' => $product->description_en,
            'description_ur' => $product->description_ur,
            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
            'seo_keywords' => $product->seo_keywords,
            'seo_focus_keyword' => $product->seo_focus_keyword,
            'hs_code' => $product->hs_code,
            'usage_en' => $product->usage_en,
            'usage_ur' => $product->usage_ur,
            'product_number_display' => $product->product_number,
            'ai_status_display' => config('product-catalog.product_ai_statuses')[$product->ai_status] ?? ucfirst($product->ai_status),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset(
            $state['category_path_display'],
            $state['category_gallery_display'],
            $state['product_number_display'],
            $state['ai_status_display'],
            $state['name_ur'],
        );

        if (isset($state['attribute_rows']) && is_array($state['attribute_rows'])) {
            $state['attribute_rows'] = array_values(array_filter(
                $state['attribute_rows'],
                fn (array $row): bool => filled($row['attribute_id'] ?? null) && filled($row['value'] ?? null),
            ));
        }

        return $state;
    }

    /**
     * @return list<array{attribute_id: null, value: null}>
     */
    public static function emptyAttributeRowState(): array
    {
        return [
            ['attribute_id' => null, 'value' => null],
        ];
    }

    /**
     * @return list<array{attribute_id: int|null, value: string|null}>
     */
    public static function attributeRowsFromModel(Product $product): array
    {
        $rows = $product->attributeValues
            ->map(fn ($row): array => [
                'attribute_id' => $row->attribute_id,
                'value' => $row->value,
            ])
            ->all();

        return $rows !== [] ? $rows : self::emptyAttributeRowState();
    }

    public static function categoryHierarchyGalleryHtml(Category $category): string
    {
        $imageStorage = app(CategoryImageStorage::class);
        $levels = [...self::categoryAncestorChain($category), $category];

        $html = '<div class="agricart-product-category-hierarchy-row">';

        foreach ($levels as $index => $level) {
            $isCurrent = $index === count($levels) - 1;
            $imageUrl = $imageStorage->url($level->image_path);
            $levelClass = $isCurrent
                ? 'agricart-product-category-hierarchy-level agricart-product-category-hierarchy-level--current'
                : 'agricart-product-category-hierarchy-level';

            $media = filled($imageUrl)
                ? '<img src="' . e($imageUrl) . '" alt="' . e($level->name_en) . '" loading="lazy">'
                : '<span class="agricart-product-category-hierarchy-level__fallback">' . e(Str::limit($level->name_en, 16)) . '</span>';

            $html .= '<div class="' . $levelClass . '" title="' . e($level->name_en) . '">'
                . '<div class="agricart-product-category-hierarchy-level__box">'
                . '<div class="agricart-product-category-hierarchy-level__media">' . $media . '</div>'
                . '</div>'
                . '<div class="agricart-product-category-hierarchy-level__label">' . e(Str::limit($level->name_en, 14)) . '</div>'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @return list<Category>
     */
    public static function categoryAncestorChain(Category $category): array
    {
        $ancestors = [];
        $parentId = $category->parent_id;

        while ($parentId !== null) {
            $parent = Category::query()->find($parentId);

            if ($parent === null) {
                break;
            }

            array_unshift($ancestors, $parent);
            $parentId = $parent->parent_id;
        }

        return $ancestors;
    }
}
