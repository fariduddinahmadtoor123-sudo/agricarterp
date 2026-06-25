<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\ProductCatalog\CategoryCodeGenerator;
use App\Services\ProductCatalog\CategoryPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_category_with_generated_number_and_visual_code(): void
    {
        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Agriculture',
            'name_ur' => 'زراعت',
        ]));

        $this->assertSame('CAT-000001', $category->category_number);
        $this->assertSame('R1', $category->visual_mapping_code);
        $this->assertSame('Agriculture', $category->full_path);
        $this->assertSame(0, $category->level);
        $this->assertTrue($category->is_leaf);
        $this->assertSame(0, $category->products_count);
    }

    public function test_creates_child_category_with_nested_visual_code(): void
    {
        $root = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Agriculture',
            'name_ur' => 'زراعت',
        ]));

        $child = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Rotavator',
            'name_ur' => 'روٹیویٹر',
            'parent_id' => $root->id,
        ]));

        $this->assertSame('R1M1', $child->visual_mapping_code);
        $this->assertSame('Agriculture › Rotavator', $child->full_path);
        $this->assertSame(1, $child->level);
        $this->assertFalse($root->fresh()->is_leaf);
    }

    public function test_generates_incrementing_category_numbers(): void
    {
        $generator = app(CategoryCodeGenerator::class);

        $this->assertSame('CAT-000001', $generator->generate());
        $this->assertSame('CAT-000002', $generator->generate());
    }

    public function test_requires_english_name(): void
    {
        $this->expectException(ValidationException::class);

        app(CategoryPersistenceService::class)->create([
            'name_ur' => 'صرف اردو',
        ]);
    }

    public function test_allows_create_without_urdu_name(): void
    {
        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Side Gear',
        ]);

        $this->assertSame('Side Gear', $category->name_en);
        $this->assertSame('', $category->name_ur);
        $this->assertSame(Category::AI_STATUS_PENDING, $category->ai_status);
        $this->assertNull($category->slug);
    }

    public function test_persists_extended_content_fields(): void
    {
        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'slug' => 'side-gear',
            'seo_focus_keyword' => 'rotavator side gear',
            'search_terms' => [
                'en' => ['side gear'],
                'ur' => ['سائیڈ گیئر'],
                'aliases' => ['rotary tiller gear'],
            ],
            'faqs_en' => [
                ['question' => 'What is this?', 'answer' => 'A side gear part.'],
            ],
            'buying_guide_en' => 'Check tooth count before purchase.',
            'common_applications_en' => 'Wheat and maize rotavators.',
            'customs_notes_en' => 'Classified under machinery parts.',
            'import_notes_en' => 'Requires commercial invoice.',
            'export_notes_en' => 'Allowed for re-export.',
        ]));

        $this->assertSame('side-gear', $category->slug);
        $this->assertSame('rotavator side gear', $category->seo_focus_keyword);
        $this->assertSame(['side gear'], $category->search_terms['en']);
        $this->assertSame('Check tooth count before purchase.', $category->buying_guide_en);
        $this->assertSame('Classified under machinery parts.', $category->customs_notes_en);
    }

    public function test_rejects_duplicate_slug(): void
    {
        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'First',
            'slug' => 'side-gear',
        ]));

        $this->expectException(ValidationException::class);

        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Second',
            'slug' => 'side-gear',
        ]));
    }

    public function test_rejects_duplicate_english_name(): void
    {
        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Rotavator Parts',
        ]));

        try {
            app(CategoryPersistenceService::class)->create($this->categoryPayload([
                'name_en' => 'Rotavator Parts',
            ]));

            $this->fail('Expected duplicate English name validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['This category name already exists.'],
                $exception->errors()['name_en'],
            );
        }
    }

    public function test_rejects_case_insensitive_duplicate_english_name(): void
    {
        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Rotavator Parts',
        ]));

        $this->expectException(ValidationException::class);

        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'rotavator parts',
        ]));
    }

    public function test_rejects_duplicate_english_name_with_surrounding_spaces(): void
    {
        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Rotavator Parts',
        ]));

        $this->expectException(ValidationException::class);

        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => '  ROTAVATOR PARTS  ',
        ]));
    }

    public function test_allows_edit_without_changing_english_name(): void
    {
        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Rotavator Parts',
            'name_ur' => 'روٹیویٹر پارٹس',
        ]));

        $updated = app(CategoryPersistenceService::class)->update($category, [
            'name_en' => 'rotavator parts',
            'name_ur' => 'اپ ڈیٹ',
        ]);

        $this->assertSame('rotavator parts', $updated->name_en);
        $this->assertSame('اپ ڈیٹ', $updated->name_ur);
    }

    public function test_rejects_edit_to_existing_english_name(): void
    {
        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Rotavator Parts',
        ]));

        $other = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Spray Pump',
        ]));

        $this->expectException(ValidationException::class);

        app(CategoryPersistenceService::class)->update($other, [
            'name_en' => 'ROTAVATOR PARTS',
        ]);
    }

    public function test_trims_english_name_on_create(): void
    {
        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => '  Side Gear  ',
        ]));

        $this->assertSame('Side Gear', $category->name_en);
    }

    public function test_rejects_create_under_archived_parent(): void
    {
        $parent = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Parent',
            'name_ur' => 'والد',
        ]));

        $this->actingAs(User::factory()->superAdmin()->create());
        app(CategoryPersistenceService::class)->archive($parent);

        $this->expectException(ValidationException::class);

        app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Child',
            'name_ur' => 'بچہ',
            'parent_id' => $parent->id,
        ]));
    }

    public function test_archive_preserves_visual_mapping_codes(): void
    {
        $first = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'First',
            'name_ur' => 'پہلا',
        ]));

        $second = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Second',
            'name_ur' => 'دوسرا',
        ]));

        $third = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Third',
            'name_ur' => 'تیسرا',
        ]));

        $this->actingAs(User::factory()->superAdmin()->create());
        app(CategoryPersistenceService::class)->archive($second);

        $this->assertSame('R1', $first->fresh()->visual_mapping_code);
        $this->assertSame('R2', $second->fresh()->visual_mapping_code);
        $this->assertSame('R3', $third->fresh()->visual_mapping_code);
    }

    public function test_category_number_never_changes_on_move(): void
    {
        $root = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Root',
            'name_ur' => 'جڑ',
        ]));

        $child = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Child',
            'name_ur' => 'بچہ',
            'parent_id' => $root->id,
        ]));

        $otherRoot = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Other Root',
            'name_ur' => 'دوسری جڑ',
        ]));

        $originalNumber = $child->category_number;

        app(CategoryPersistenceService::class)->move($child, $otherRoot->id);

        $this->assertSame($originalNumber, $child->fresh()->category_number);
        $this->assertSame('R2M1', $child->fresh()->visual_mapping_code);
    }

    public function test_move_updates_descendant_paths(): void
    {
        $root = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Root',
            'name_ur' => 'جڑ',
        ]));

        $child = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Child',
            'name_ur' => 'بچہ',
            'parent_id' => $root->id,
        ]));

        $grandchild = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Grandchild',
            'name_ur' => 'پوتا',
            'parent_id' => $child->id,
        ]));

        $newRoot = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'New Root',
            'name_ur' => 'نئی جڑ',
        ]));

        app(CategoryPersistenceService::class)->move($child, $newRoot->id);

        $this->assertSame('New Root › Child', $child->fresh()->full_path);
        $this->assertSame('New Root › Child › Grandchild', $grandchild->fresh()->full_path);
        $this->assertSame('R2M1S1', $grandchild->fresh()->visual_mapping_code);
    }

    public function test_deep_level_uses_n_letter(): void
    {
        $category = null;
        $parentId = null;

        foreach (['L0', 'L1', 'L2', 'L3', 'L4', 'L5', 'L6'] as $index => $label) {
            $category = app(CategoryPersistenceService::class)->create($this->categoryPayload([
                'name_en' => $label,
                'name_ur' => 'سطح ' . $index,
                'parent_id' => $parentId,
            ]));

            $parentId = $category->id;
        }

        $this->assertSame('R1M1S1C1L1N1N1', $category->visual_mapping_code);
        $this->assertSame(6, $category->level);
    }

    public function test_super_admin_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload());

        app(CategoryPersistenceService::class)->archive($category);
        $this->assertSame(Category::STATUS_ARCHIVED, $category->fresh()->status);

        app(CategoryPersistenceService::class)->restore($category);
        $this->assertSame(Category::STATUS_ACTIVE, $category->fresh()->status);
    }

    public function test_persists_category_image_path(): void
    {
        Storage::fake('local');
        config(['product-catalog.category_image_disk' => 'local']);

        Storage::disk('local')->put('categories/pump.jpg', 'image-bytes');

        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload([
            'name_en' => 'Spray Pump',
            'image' => 'categories/pump.jpg',
        ]));

        $this->assertSame('categories/pump.jpg', $category->image_path);
    }

    public function test_staff_cannot_archive(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $category = app(CategoryPersistenceService::class)->create($this->categoryPayload());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(CategoryPersistenceService::class)->archive($category);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function categoryPayload(array $overrides = []): array
    {
        return array_replace([
            'name_en' => 'Test Category',
            'name_ur' => 'ٹیسٹ کیٹیگری',
            'parent_id' => null,
        ], $overrides);
    }
}
