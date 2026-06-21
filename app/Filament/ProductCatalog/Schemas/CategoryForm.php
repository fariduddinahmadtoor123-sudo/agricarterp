<?php

namespace App\Filament\ProductCatalog\Schemas;

use App\Models\Category;
use App\Services\ProductCatalog\CategoryHierarchyService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                Section::make('Category Location')
                    ->compact()
                    ->schema([
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->placeholder('Root level (no parent)')
                            ->searchable()
                            ->native(false)
                            ->options(fn (): array => $hierarchy->parentOptions($record))
                            ->helperText(fn (callable $get): string => static::hierarchyPreview(
                                filled($get('parent_id')) ? (int) $get('parent_id') : null,
                            ))
                            ->live()
                            ->disabled($readOnly),
                    ])
                    ->columnSpanFull(),

                Section::make('Identity')
                    ->compact()
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('name_en')
                            ->label('English Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ur')
                            ->label('Urdu Name')
                            ->required()
                            ->maxLength(255),

                        FileUpload::make('image')
                            ->label('Category Image')
                            ->disk(config('product-catalog.category_image_disk', 'local'))
                            ->directory('categories')
                            ->image()
                            ->columnSpanFull(),
                    ])
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
                    ])
                    ->columnSpanFull(),

                Section::make('Descriptions')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        Textarea::make('description_en')->label('English Description')->rows(4),
                        Textarea::make('description_ur')->label('Urdu Description')->rows(4),
                        Textarea::make('short_description_en')->label('Short Description (English)')->rows(2),
                        Textarea::make('short_description_ur')->label('Short Description (Urdu)')->rows(2),
                    ])
                    ->columnSpanFull(),

                Section::make('SEO')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')->label('SEO Title')->maxLength(255),
                        Textarea::make('seo_description')->label('SEO Description')->rows(3),
                        Textarea::make('seo_keywords')->label('Keywords')->rows(2),
                    ])
                    ->columnSpanFull(),

                Section::make('Classification & Usage')
                    ->compact()
                    ->collapsed()
                    ->schema([
                        TextInput::make('hs_code')->label('HS Code')->maxLength(20),
                        Textarea::make('usage_en')->label('Usage (English)')->rows(3),
                        Textarea::make('usage_ur')->label('Usage (Urdu)')->rows(3),
                        Textarea::make('benefits_en')->label('Benefits (English)')->rows(3),
                        Textarea::make('benefits_ur')->label('Benefits (Urdu)')->rows(3),
                        Textarea::make('warnings_en')->label('Warnings (English)')->rows(3),
                        Textarea::make('warnings_ur')->label('Warnings (Urdu)')->rows(3),
                        Textarea::make('import_export_notes_en')->label('Import / Export Notes (English)')->rows(3),
                        Textarea::make('import_export_notes_ur')->label('Import / Export Notes (Urdu)')->rows(3),
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
            'image' => null,
            'description_en' => null,
            'description_ur' => null,
            'short_description_en' => null,
            'short_description_ur' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'hs_code' => null,
            'usage_en' => null,
            'usage_ur' => null,
            'benefits_en' => null,
            'benefits_ur' => null,
            'warnings_en' => null,
            'warnings_ur' => null,
            'import_export_notes_en' => null,
            'import_export_notes_ur' => null,
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
            'image' => $category->image_path,
            'description_en' => $category->description_en,
            'description_ur' => $category->description_ur,
            'short_description_en' => $category->short_description_en,
            'short_description_ur' => $category->short_description_ur,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'seo_keywords' => $category->seo_keywords,
            'hs_code' => $category->hs_code,
            'usage_en' => $category->usage_en,
            'usage_ur' => $category->usage_ur,
            'benefits_en' => $category->benefits_en,
            'benefits_ur' => $category->benefits_ur,
            'warnings_en' => $category->warnings_en,
            'warnings_ur' => $category->warnings_ur,
            'import_export_notes_en' => $category->import_export_notes_en,
            'import_export_notes_ur' => $category->import_export_notes_ur,
            'category_number_display' => $category->category_number,
            'visual_mapping_display' => $category->visual_mapping_code,
            'full_path_display' => $category->full_path,
            'level_display' => (string) $category->level,
            'products_count_display' => (string) $category->products_count,
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
        );

        return $state;
    }

    public static function hierarchyPreview(?int $parentId): string
    {
        if ($parentId === null) {
            return 'Root level — you are creating a top-level category.';
        }

        $parent = Category::query()->find($parentId);

        if ($parent === null) {
            return 'Root level — you are creating a top-level category.';
        }

        return $parent->full_path . ' — you are creating under: ' . $parent->name_en;
    }
}
