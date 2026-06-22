<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductControlGroup;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductCatalog\AttributePersistenceService;
use App\Services\ProductCatalog\BrandPersistenceService;
use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Services\ProductCatalog\ProductControlGroupPersistenceService;
use App\Services\ProductCatalog\ProductControlPersistenceService;
use App\Services\ProductCatalog\ProductPersistenceService;
use App\Services\ProductCatalog\UnitPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected Category $leafCategory;

    protected Category $displayCategory;

    protected Brand $brand;

    protected Unit $pieceUnit;

    protected Unit $cartonUnit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $parent = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Agriculture',
            'name_ur' => '',
        ]);

        $this->leafCategory = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Rotor Gear',
            'name_ur' => '',
            'parent_id' => app(CategoryPersistenceService::class)->create([
                'name_en' => 'Rotavator',
                'name_ur' => '',
                'parent_id' => $parent->id,
            ])->id,
        ]);

        $this->displayCategory = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Boom Spray Pumps',
            'name_ur' => '',
            'parent_id' => $parent->id,
        ]);

        $this->brand = app(BrandPersistenceService::class)->create([
            'name_en' => 'Honda',
            'short_note' => 'Engines and pumps.',
            'category_ids' => [],
        ]);

        $this->pieceUnit = app(UnitPersistenceService::class)->create([
            'name_en' => 'Piece',
            'abbreviation_en' => 'pcs',
            'unit_type' => Unit::TYPE_COUNT,
        ]);

        $this->cartonUnit = app(UnitPersistenceService::class)->create([
            'name_en' => 'Carton',
            'abbreviation_en' => 'ctn',
            'unit_type' => Unit::TYPE_PACKAGING,
        ]);
    }

    public function test_creates_product_with_generated_number(): void
    {
        $product = app(ProductPersistenceService::class)->create($this->productPayload([
            'name_en' => 'BJ-170 Pump',
        ]));

        $this->assertSame('PRD-000001', $product->product_number);
        $this->assertSame('BJ-170 Pump', $product->name_en);
        $this->assertSame(Product::STATUS_ACTIVE, $product->status);
        $this->assertSame($this->leafCategory->id, $product->category_id);
    }

    public function test_rejects_duplicate_name_for_same_brand(): void
    {
        app(ProductPersistenceService::class)->create($this->productPayload([
            'name_en' => 'BJ-170 Pump',
        ]));

        $this->expectException(ValidationException::class);

        app(ProductPersistenceService::class)->create($this->productPayload([
            'name_en' => 'bj-170 pump',
        ]));
    }

    public function test_allows_same_name_for_different_brands(): void
    {
        app(ProductPersistenceService::class)->create($this->productPayload([
            'name_en' => 'BJ-170 Pump',
        ]));

        $otherBrand = app(BrandPersistenceService::class)->create([
            'name_en' => 'Yanmar',
            'short_note' => 'Diesel engines.',
            'category_ids' => [],
        ]);

        $product = app(ProductPersistenceService::class)->create($this->productPayload([
            'name_en' => 'BJ-170 Pump',
            'brand_id' => $otherBrand->id,
        ]));

        $this->assertSame('BJ-170 Pump', $product->name_en);
        $this->assertSame($otherBrand->id, $product->brand_id);
    }

    public function test_assigns_display_category_tags(): void
    {
        $product = app(ProductPersistenceService::class)->create($this->productPayload([
            'display_category_ids' => [$this->displayCategory->id],
        ]));

        $this->assertCount(1, $product->categoryTags);
        $this->assertTrue($product->categoryTags->contains('id', $this->displayCategory->id));
    }

    public function test_syncs_products_count_on_primary_and_tag_categories(): void
    {
        app(ProductPersistenceService::class)->create($this->productPayload([
            'display_category_ids' => [$this->displayCategory->id],
        ]));

        $this->assertSame(1, $this->leafCategory->fresh()->products_count);
        $this->assertSame(1, $this->displayCategory->fresh()->products_count);
    }

    public function test_assigns_attributes_and_controls_without_duplicates(): void
    {
        $attribute = app(AttributePersistenceService::class)->create(['name' => 'Color']);

        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Handle with care',
            'control_type' => ProductControl::TYPE_HANDLING_ALERT,
        ]);

        $groupControl = app(ProductControlPersistenceService::class)->create([
            'name' => 'Motor winding covered',
            'control_type' => ProductControl::TYPE_WARRANTY,
        ]);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Motor Standard Policy',
            'control_ids' => [$groupControl->id],
        ]);

        $product = app(ProductPersistenceService::class)->create($this->productPayload([
            'attribute_rows' => [
                ['attribute_id' => $attribute->id, 'value' => 'Black'],
            ],
            'control_group_ids' => [$group->id],
            'individual_control_ids' => [$control->id, $groupControl->id],
        ]));

        $this->assertCount(1, $product->attributeValues);
        $this->assertCount(1, $product->controlGroups);
        $this->assertCount(1, $product->individualControls);
        $this->assertFalse($product->individualControls->contains('id', $groupControl->id));
    }

    public function test_updates_primary_category_and_reconciles_products_count(): void
    {
        $product = app(ProductPersistenceService::class)->create($this->productPayload([
            'display_category_ids' => [$this->displayCategory->id],
        ]));

        $newLeaf = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Submersible',
            'name_ur' => '',
            'parent_id' => Category::query()->where('name_en', 'Agriculture')->value('id'),
        ]);

        app(ProductPersistenceService::class)->update($product, $this->productPayload([
            'category_id' => $newLeaf->id,
            'display_category_ids' => [$this->displayCategory->id],
        ]));

        $this->assertSame(0, $this->leafCategory->fresh()->products_count);
        $this->assertSame(1, $newLeaf->fresh()->products_count);
        $this->assertSame(1, $this->displayCategory->fresh()->products_count);
    }

    public function test_super_admin_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $product = app(ProductPersistenceService::class)->create($this->productPayload());

        app(ProductPersistenceService::class)->archive($product);
        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);
        $this->assertSame(0, $this->leafCategory->fresh()->products_count);

        app(ProductPersistenceService::class)->restore($product);
        $this->assertSame(Product::STATUS_ACTIVE, $product->fresh()->status);
        $this->assertSame(1, $this->leafCategory->fresh()->products_count);
    }

    public function test_staff_cannot_archive_product(): void
    {
        $this->actingAs(User::factory()->create());

        $product = app(ProductPersistenceService::class)->create($this->productPayload());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ProductPersistenceService::class)->archive($product);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        $path = 'products/test-main.jpg';
        Storage::disk('local')->put($path, 'image');

        return array_merge([
            'category_id' => $this->leafCategory->id,
            'brand_id' => $this->brand->id,
            'base_unit_id' => $this->pieceUnit->id,
            'packing_unit_id' => $this->cartonUnit->id,
            'packing_value' => 12,
            'name_en' => 'Test Product',
            'main_image' => $path,
            'additional_images' => [],
            'required_quantity' => 10,
            'alert_quantity' => 2,
            'display_category_ids' => [],
            'attribute_rows' => [],
            'control_group_ids' => [],
            'individual_control_ids' => [],
        ], $overrides);
    }
}
