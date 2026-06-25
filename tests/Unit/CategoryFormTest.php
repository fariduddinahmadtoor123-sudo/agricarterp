<?php

namespace Tests\Unit;

use App\Filament\ProductCatalog\Schemas\CategoryForm;
use App\Services\ProductCatalog\CategoryHierarchyService;
use App\Services\ProductCatalog\CategoryPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchy_segments_show_full_path_before_save(): void
    {
        $root = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture',
            'name_ur' => 'زراعت',
        ]);

        $child = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotavator',
            'name_ur' => 'روٹیویٹر',
            'parent_id' => $root->id,
        ]);

        $segments = CategoryForm::hierarchySegments($child->id, 'Side Gear');

        $this->assertSame(['Agriculture', 'Rotavator', 'Side Gear'], $segments);
        $this->assertSame(
            'Agriculture → Rotavator → Side Gear',
            CategoryForm::hierarchyBreadcrumb($child->id, 'Side Gear'),
        );
    }

    public function test_search_parent_options_use_name_only(): void
    {
        $root = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture Machinery',
            'name_ur' => 'زرعی مشینری',
        ]);

        $child = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Spray Pump',
            'name_ur' => 'سپرے پمپ',
            'parent_id' => $root->id,
        ]);

        $hierarchy = app(CategoryHierarchyService::class);

        $results = $hierarchy->searchParentOptions('Spray');

        $this->assertSame('Spray Pump', $results[$child->id]);
        $this->assertStringNotContainsString('›', implode(' ', $results));
        $this->assertStringNotContainsString('Agriculture Machinery', $results[$child->id]);
    }

    public function test_parent_short_label_uses_final_category_name_only(): void
    {
        $root = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture',
            'name_ur' => 'زراعت',
        ]);

        $child = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotavator',
            'name_ur' => 'روٹیویٹر',
            'parent_id' => $root->id,
        ]);

        $hierarchy = app(CategoryHierarchyService::class);

        $this->assertSame('Rotavator', $hierarchy->parentShortLabel($child->id));
        $this->assertSame('Rotavator', $hierarchy->parentOptionsShort()[$child->id]);
    }

    public function test_hierarchy_breadcrumb_shows_root_name_only(): void
    {
        $this->assertSame(
            'Agriculture',
            CategoryForm::hierarchyBreadcrumb(null, 'Agriculture'),
        );
    }
}
