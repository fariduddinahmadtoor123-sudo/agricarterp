<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\AiEnrichmentLog;
use App\Models\Category;
use App\Rules\UniqueCategoryEnglishName;
use App\Services\ProductCatalog\CategoryHierarchyService;
use App\Services\ProductCatalog\CategoryImageStorage;
use App\Support\ProductCatalog\CategoryFilenameNameFormatter;
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
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CategoryForm
{
    public static function configure(Schema $schema, bool $readOnly = false, ?Category $record = null): Schema
    {
        $hierarchy = app(CategoryHierarchyService::class);

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->extraAttributes([
                'class' => 'agricart-category-form' . ($readOnly ? ' agricart-category-form-readonly' : ''),
            ])
            ->components([
                Group::make()
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->placeholder('Root level (no parent)')
                            ->searchable()
                            ->native(false)
                            ->options(fn (): array => $hierarchy->parentOptionsShort($record))
                            ->getSearchResultsUsing(fn (string $search): array => $hierarchy->searchParentOptions($search, $record))
                            ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? $hierarchy->parentShortLabel($value) : null)
                            ->afterStateHydrated(function (Select $component): void {
                                if (filled($component->getState())) {
                                    $component->refreshSelectedOptionLabel();
                                }
                            })
                            ->afterStateUpdated(function (Select $component): void {
                                if (filled($component->getState())) {
                                    $component->refreshSelectedOptionLabel();
                                }
                            })
                            ->autofocus($record === null && ! $readOnly)
                            ->live()
                            ->disabled($readOnly)
                            ->columnSpanFull(),

                        Group::make()
                            ->schema($readOnly
                                ? [
                                    Placeholder::make('hierarchy_preview')
                                        ->hiddenLabel()
                                        ->content(fn (callable $get): HtmlString => static::hierarchyPreviewHtml(
                                            filled($get('parent_id')) ? (int) $get('parent_id') : null,
                                            filled($get('name_en')) ? (string) $get('name_en') : null,
                                            $get('image'),
                                            $record,
                                        )),
                                ]
                                : [
                                    Placeholder::make('hierarchy_ancestors')
                                        ->hiddenLabel()
                                        ->content(fn (callable $get): HtmlString => static::ancestorHierarchyPreviewHtml(
                                            filled($get('parent_id')) ? (int) $get('parent_id') : null,
                                        )),

                                    Group::make()
                                        ->schema([
                                            FileUpload::make('image')
                                                ->hiddenLabel()
                                                ->disk(config('product-catalog.category_image_disk', 'local'))
                                                ->directory('categories')
                                                ->image()
                                                ->imagePreviewHeight('160')
                                                ->panelLayout('compact')
                                                ->live()
                                                ->afterStateUpdated(function (mixed $state, callable $set, callable $get) use ($record): void {
                                                    $name = CategoryFilenameNameFormatter::fromNewUpload($state);

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

                                            Placeholder::make('current_category_label')
                                                ->hiddenLabel()
                                                ->content(fn (callable $get): HtmlString => new HtmlString(
                                                    '<div class="agricart-category-hierarchy-level__label">'
                                                    . e(Str::limit(filled($get('name_en')) ? trim((string) $get('name_en')) : 'New category', 24))
                                                    . '</div>',
                                                )),
                                        ])
                                        ->extraAttributes([
                                            'class' => 'agricart-category-hierarchy-level agricart-category-hierarchy-level--current agricart-category-hierarchy-upload',
                                        ]),
                                ])
                            ->extraAttributes(fn (): array => $readOnly ? [] : [
                                'class' => 'agricart-category-hierarchy-row',
                            ])
                            ->columnSpanFull(),

                        TextInput::make('name_en')
                            ->label('English Name')
                            ->placeholder('Auto-filled from image filename')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->rules($readOnly ? [] : [new UniqueCategoryEnglishName($record)])
                            ->validationMessages([
                                'required' => 'English name is required.',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'sm' => 12])
                    ->columnSpanFull(),

                Section::make('System Information')
                    ->compact()
                    ->visible($record !== null)
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('category_number_display')
                            ->label('Category Number')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('visual_mapping_display')
                            ->label('Visual Mapping Code')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('full_path_display')
                            ->label('Full Category Path')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        TextInput::make('level_display')
                            ->label('Level')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('products_count_display')
                            ->label('Product Count')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_status_display')
                            ->label('AI Status')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('ai_last_error_display')
                            ->label('Last AI Error')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (callable $get): bool => filled($get('ai_last_error_display')))
                            ->columnSpanFull(),

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
                    ->description('AI-generated and review content. Not required during fast category entry.')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        Fieldset::make('Urdu Identity')
                            ->schema([
                                TextInput::make('name_ur')
                                    ->label('Urdu Name')
                                    ->maxLength(255)
                                    ->helperText('Optional during entry. AI will generate Urdu content later.'),
                            ]),

                        Fieldset::make('SEO & Search')
                            ->schema([
                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->maxLength(255)
                                    ->helperText('URL-safe identifier. AI-generated after enrichment.'),
                                TextInput::make('seo_title')->label('SEO Title')->maxLength(255),
                                Textarea::make('seo_description')->label('SEO Description')->rows(3),
                                TextInput::make('seo_focus_keyword')->label('SEO Focus Keyword')->maxLength(255),
                                Textarea::make('seo_keywords')->label('SEO Keywords')->rows(2),
                                Textarea::make('search_terms')
                                    ->label('Search Terms (JSON)')
                                    ->rows(4)
                                    ->helperText('Structured aliases and bilingual search terms. AI-managed.')
                                    ->formatStateUsing(fn (mixed $state): ?string => is_array($state)
                                        ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                        : (is_string($state) ? $state : null))
                                    ->dehydrateStateUsing(function (mixed $state): ?array {
                                        if (blank($state)) {
                                            return null;
                                        }

                                        if (is_array($state)) {
                                            return $state;
                                        }

                                        $decoded = json_decode((string) $state, true);

                                        return is_array($decoded) ? $decoded : null;
                                    }),
                            ]),

                        Fieldset::make('Customer Content')
                            ->schema([
                                Textarea::make('short_description_en')->label('Short Description (English)')->rows(2),
                                Textarea::make('short_description_ur')->label('Short Description (Urdu)')->rows(2),
                                Textarea::make('description_en')->label('English Description')->rows(4),
                                Textarea::make('description_ur')->label('Urdu Description')->rows(4),
                                Textarea::make('usage_en')->label('Usage (English)')->rows(3),
                                Textarea::make('usage_ur')->label('Usage (Urdu)')->rows(3),
                                Textarea::make('benefits_en')->label('Benefits (English)')->rows(3),
                                Textarea::make('benefits_ur')->label('Benefits (Urdu)')->rows(3),
                                Textarea::make('warnings_en')->label('Warnings (English)')->rows(3),
                                Textarea::make('warnings_ur')->label('Warnings (Urdu)')->rows(3),
                                Textarea::make('common_applications_en')->label('Common Applications (English)')->rows(3),
                                Textarea::make('common_applications_ur')->label('Common Applications (Urdu)')->rows(3),
                                Textarea::make('buying_guide_en')->label('Buying Guide (English)')->rows(3),
                                Textarea::make('buying_guide_ur')->label('Buying Guide (Urdu)')->rows(3),
                                Repeater::make('faqs_en')
                                    ->label('FAQs (English)')
                                    ->schema([
                                        TextInput::make('question')->label('Question')->required(),
                                        Textarea::make('answer')->label('Answer')->rows(2)->required(),
                                    ])
                                    ->collapsed()
                                    ->defaultItems(0),
                                Repeater::make('faqs_ur')
                                    ->label('FAQs (Urdu)')
                                    ->schema([
                                        TextInput::make('question')->label('Question')->required(),
                                        Textarea::make('answer')->label('Answer')->rows(2)->required(),
                                    ])
                                    ->collapsed()
                                    ->defaultItems(0),
                            ]),

                        Fieldset::make('Import / Export')
                            ->schema([
                                TextInput::make('hs_code')->label('HS Code')->maxLength(20),
                                Textarea::make('customs_notes_en')->label('Customs Notes (English)')->rows(3),
                                Textarea::make('customs_notes_ur')->label('Customs Notes (Urdu)')->rows(3),
                                Textarea::make('import_notes_en')->label('Import Notes (English)')->rows(3),
                                Textarea::make('import_notes_ur')->label('Import Notes (Urdu)')->rows(3),
                                Textarea::make('export_notes_en')->label('Export Notes (English)')->rows(3),
                                Textarea::make('export_notes_ur')->label('Export Notes (Urdu)')->rows(3),
                                Textarea::make('import_export_notes_en')->label('Legacy Import / Export Notes (English)')->rows(3),
                                Textarea::make('import_export_notes_ur')->label('Legacy Import / Export Notes (Urdu)')->rows(3),
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
            'parent_id' => null,
            'name_en' => null,
            'name_ur' => null,
            'slug' => null,
            'image' => null,
            'description_en' => null,
            'description_ur' => null,
            'short_description_en' => null,
            'short_description_ur' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'seo_focus_keyword' => null,
            'search_terms' => null,
            'hs_code' => null,
            'usage_en' => null,
            'usage_ur' => null,
            'benefits_en' => null,
            'benefits_ur' => null,
            'warnings_en' => null,
            'warnings_ur' => null,
            'import_export_notes_en' => null,
            'import_export_notes_ur' => null,
            'faqs_en' => [],
            'faqs_ur' => [],
            'buying_guide_en' => null,
            'buying_guide_ur' => null,
            'common_applications_en' => null,
            'common_applications_ur' => null,
            'customs_notes_en' => null,
            'customs_notes_ur' => null,
            'import_notes_en' => null,
            'import_notes_ur' => null,
            'export_notes_en' => null,
            'export_notes_ur' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(Category $category): array
    {
        return [
            'parent_id' => $category->parent_id,
            'name_en' => $category->name_en,
            'name_ur' => $category->name_ur,
            'slug' => $category->slug,
            'image' => $category->image_path,
            'description_en' => $category->description_en,
            'description_ur' => $category->description_ur,
            'short_description_en' => $category->short_description_en,
            'short_description_ur' => $category->short_description_ur,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'seo_keywords' => $category->seo_keywords,
            'seo_focus_keyword' => $category->seo_focus_keyword,
            'search_terms' => $category->search_terms,
            'hs_code' => $category->hs_code,
            'usage_en' => $category->usage_en,
            'usage_ur' => $category->usage_ur,
            'benefits_en' => $category->benefits_en,
            'benefits_ur' => $category->benefits_ur,
            'warnings_en' => $category->warnings_en,
            'warnings_ur' => $category->warnings_ur,
            'import_export_notes_en' => $category->import_export_notes_en,
            'import_export_notes_ur' => $category->import_export_notes_ur,
            'faqs_en' => $category->faqs_en ?? [],
            'faqs_ur' => $category->faqs_ur ?? [],
            'buying_guide_en' => $category->buying_guide_en,
            'buying_guide_ur' => $category->buying_guide_ur,
            'common_applications_en' => $category->common_applications_en,
            'common_applications_ur' => $category->common_applications_ur,
            'customs_notes_en' => $category->customs_notes_en,
            'customs_notes_ur' => $category->customs_notes_ur,
            'import_notes_en' => $category->import_notes_en,
            'import_notes_ur' => $category->import_notes_ur,
            'export_notes_en' => $category->export_notes_en,
            'export_notes_ur' => $category->export_notes_ur,
            'category_number_display' => $category->category_number,
            'visual_mapping_display' => $category->visual_mapping_code,
            'full_path_display' => $category->full_path,
            'level_display' => (string) $category->level,
            'products_count_display' => (string) $category->products_count,
            'ai_status_display' => config('product-catalog.category_ai_statuses')[$category->ai_status] ?? ucfirst($category->ai_status),
            'ai_last_error_display' => static::lastAiErrorForCategory($category),
            'ai_generated_at_display' => $category->ai_generated_at?->toDateTimeString(),
            'ai_version_display' => $category->ai_version,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function normalizeState(array $state): array
    {
        unset(
            $state['category_number_display'],
            $state['visual_mapping_display'],
            $state['full_path_display'],
            $state['level_display'],
            $state['products_count_display'],
            $state['ai_status_display'],
            $state['ai_last_error_display'],
            $state['ai_generated_at_display'],
            $state['ai_version_display'],
        );

        if (($state['faqs_en'] ?? null) === []) {
            $state['faqs_en'] = null;
        }

        if (($state['faqs_ur'] ?? null) === []) {
            $state['faqs_ur'] = null;
        }

        return $state;
    }

    /**
     * @return list<string>
     */
    public static function hierarchySegments(?int $parentId, ?string $nameEn): array
    {
        return array_map(
            fn (array $level): string => $level['name'],
            static::hierarchyLevels($parentId, $nameEn),
        );
    }

    /**
     * @return list<array{name: string, image_url: ?string, current: bool}>
     */
    public static function hierarchyLevels(
        ?int $parentId,
        ?string $nameEn,
        mixed $imageState = null,
        ?Category $record = null,
    ): array {
        $levels = [];

        foreach (static::ancestorCategories($parentId) as $category) {
            $levels[] = [
                'name' => $category->name_en,
                'image_url' => static::resolveImageUrl($category->image_path),
                'current' => false,
            ];
        }

        if (filled($nameEn) || $levels !== []) {
            $levels[] = [
                'name' => filled($nameEn) ? trim($nameEn) : '(new category)',
                'image_url' => static::resolveImageUrlFromState($imageState)
                    ?? ($record !== null ? static::resolveImageUrl($record->image_path) : null),
                'current' => true,
            ];
        }

        return $levels;
    }

    /**
     * @return list<Category>
     */
    protected static function ancestorCategories(?int $parentId): array
    {
        if ($parentId === null) {
            return [];
        }

        $ancestors = [];
        $current = Category::query()->find($parentId);

        while ($current !== null) {
            array_unshift($ancestors, $current);
            $current = $current->parent_id
                ? Category::query()->find($current->parent_id)
                : null;
        }

        return $ancestors;
    }

    protected static function resolveImageUrl(?string $path): ?string
    {
        return app(CategoryImageStorage::class)->url($path);
    }

    protected static function resolveImageUrlFromState(mixed $imageState): ?string
    {
        if ($imageState instanceof TemporaryUploadedFile) {
            try {
                return $imageState->temporaryUrl();
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_array($imageState)) {
            return static::resolveImageUrlFromState(Arr::first($imageState));
        }

        if (is_string($imageState) && filled($imageState)) {
            return static::resolveImageUrl($imageState);
        }

        return null;
    }

    public static function ancestorHierarchyPreviewHtml(?int $parentId): HtmlString
    {
        $html = '<div class="agricart-category-hierarchy-ancestors">';

        foreach (static::ancestorCategories($parentId) as $category) {
            $html .= static::renderHierarchyLevelBox([
                'name' => $category->name_en,
                'image_url' => static::resolveImageUrl($category->image_path),
                'current' => false,
            ], false);
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    public static function hierarchyPreviewHtml(
        ?int $parentId,
        ?string $nameEn,
        mixed $imageState = null,
        ?Category $record = null,
    ): HtmlString {
        $levels = static::hierarchyLevels($parentId, $nameEn, $imageState, $record);

        if ($levels === []) {
            return new HtmlString(
                '<div class="agricart-category-hierarchy-row">'
                . static::renderHierarchyLevelBox([
                    'name' => 'Root',
                    'image_url' => null,
                    'current' => false,
                ], false)
                . '</div>',
            );
        }

        $html = '<div class="agricart-category-hierarchy-row">';

        foreach ($levels as $index => $level) {
            $html .= static::renderHierarchyLevelBox($level, (bool) $level['current']);
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * @param  array{name: string, image_url: ?string, current: bool}  $level
     */
    protected static function renderHierarchyLevelBox(array $level, bool $isCurrent): string
    {
        $levelClass = $isCurrent
            ? 'agricart-category-hierarchy-level agricart-category-hierarchy-level--current'
            : 'agricart-category-hierarchy-level';

        $media = filled($level['image_url'])
            ? '<img src="' . e($level['image_url']) . '" alt="' . e($level['name']) . '" loading="lazy">'
            : '<span class="agricart-category-hierarchy-level__fallback">' . e(Str::limit($level['name'], 28)) . '</span>';

        return '<div class="' . $levelClass . '">'
            . '<div class="agricart-category-hierarchy-level__box">'
            . '<div class="agricart-category-hierarchy-level__media">' . $media . '</div>'
            . '</div>'
            . '<div class="agricart-category-hierarchy-level__label">' . e(Str::limit($level['name'], 24)) . '</div>'
            . '</div>';
    }

    public static function hierarchyBreadcrumb(?int $parentId, ?string $nameEn): string
    {
        $segments = static::hierarchySegments($parentId, $nameEn);

        if ($segments === []) {
            return 'Select a parent category and upload an image to preview hierarchy.';
        }

        return implode(' → ', $segments);
    }

    protected static function lastAiErrorForCategory(Category $category): ?string
    {
        $log = AiEnrichmentLog::query()
            ->where('subject_type', Category::class)
            ->where('subject_id', $category->id)
            ->where('status', AiEnrichmentLog::STATUS_FAILED)
            ->latest('id')
            ->first();

        return $log?->message;
    }
}
