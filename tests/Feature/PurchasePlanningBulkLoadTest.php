<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Services\PurchasingInventory\PurchasePlanningBulkLoad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePlanningBulkLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_by_category_handles_categories_without_full_path(): void
    {
        $parent = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Empty Path Parent',
            'status' => 'active',
        ]);

        Category::query()->whereKey($parent->id)->update(['full_path' => '']);

        $results = app(PurchasePlanningBulkLoad::class)->byCategory((int) $parent->id);

        $this->assertIsArray($results);
    }
}
