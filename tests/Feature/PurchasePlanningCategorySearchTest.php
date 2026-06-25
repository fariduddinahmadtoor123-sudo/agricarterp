<?php

namespace Tests\Feature;

use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Services\PurchasingInventory\PurchasePlanningCategorySearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePlanningCategorySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_categories_by_name_and_path_tokens(): void
    {
        app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture',
            'status' => 'active',
        ]);

        $child = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Bloom',
            'parent_id' => \App\Models\Category::query()->where('name_en', 'Agriculture')->value('id'),
            'status' => 'active',
        ]);

        $results = app(PurchasePlanningCategorySearch::class)->search('bloom');

        $this->assertNotEmpty($results);
        $this->assertSame('Bloom', collect($results)->firstWhere('id', $child->id)['name'] ?? null);
        $this->assertStringContainsString('Agriculture', (string) (collect($results)->firstWhere('id', $child->id)['path_hint'] ?? ''));
    }
}
