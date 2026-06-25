<?php

namespace Tests\Unit;

use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Services\ProductCatalog\ProductCategoryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_full_path_search_returns_only_matching_category(): void
    {
        $persistence = app(CategoryPersistenceService::class);
        $query = app(ProductCategoryQuery::class);

        $root = $persistence->create([
            'name_en' => 'Agriculture',
            'name_ur' => 'زراعت',
        ]);

        $child = $persistence->create([
            'name_en' => 'Rotavator',
            'name_ur' => 'روٹیویٹر',
            'parent_id' => $root->id,
        ]);

        $leaf = $persistence->create([
            'name_en' => 'Side Gear',
            'name_ur' => 'سائیڈ گیئر',
            'parent_id' => $child->id,
        ]);

        $otherRoot = $persistence->create([
            'name_en' => 'Garden Tools',
            'name_ur' => 'اوزار',
        ]);

        $persistence->create([
            'name_en' => 'Hand Trowel',
            'name_ur' => 'ٹراول',
            'parent_id' => $otherRoot->id,
        ]);

        $results = $query->searchActiveLeafCategories('Agriculture › Rotavator › Side Gear');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey($leaf->id, $results);
        $this->assertSame('Agriculture › Rotavator › Side Gear', $results[$leaf->id]);
    }

    public function test_exact_english_name_is_ranked_above_partial_matches(): void
    {
        $persistence = app(CategoryPersistenceService::class);
        $query = app(ProductCategoryQuery::class);

        $root = $persistence->create([
            'name_en' => 'Spray',
            'name_ur' => 'سپرے',
        ]);

        $exact = $persistence->create([
            'name_en' => 'Spray Pump',
            'name_ur' => 'سپرے پمپ',
            'parent_id' => $root->id,
        ]);

        $partial = $persistence->create([
            'name_en' => 'Battery Spray',
            'name_ur' => 'بیٹری',
            'parent_id' => $root->id,
        ]);

        $results = $query->searchActiveLeafCategories('Spray Pump');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey($exact->id, $results);
        $this->assertSame('Spray › Spray Pump', $results[$exact->id]);
        $this->assertArrayNotHasKey($partial->id, $results);
        $this->assertArrayNotHasKey($root->id, $results);

        $ranked = $query->searchActiveLeafCategories('Spray');

        $this->assertSame(array_keys($ranked), [$exact->id, $partial->id]);
    }

    public function test_multi_word_search_requires_all_tokens(): void
    {
        $persistence = app(CategoryPersistenceService::class);
        $query = app(ProductCategoryQuery::class);

        $root = $persistence->create([
            'name_en' => 'Machinery',
            'name_ur' => 'مشینری',
        ]);

        $match = $persistence->create([
            'name_en' => 'Spray Pump',
            'name_ur' => 'سپرے پمپ',
            'parent_id' => $root->id,
        ]);

        $persistence->create([
            'name_en' => 'Spray Nozzle',
            'name_ur' => 'نوزل',
            'parent_id' => $root->id,
        ]);

        $results = $query->searchActiveLeafCategories('Spray Pump');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey($match->id, $results);
    }
}
