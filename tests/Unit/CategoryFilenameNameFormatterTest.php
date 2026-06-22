<?php

namespace Tests\Unit;

use App\Support\ProductCatalog\CategoryFilenameNameFormatter;
use Tests\TestCase;

class CategoryFilenameNameFormatterTest extends TestCase
{
    public function test_formats_hyphenated_filename_to_title_case(): void
    {
        $this->assertSame(
            'Rotavator Side Gear',
            CategoryFilenameNameFormatter::format('rotavator-side-gear.jpg'),
        );
    }

    public function test_formats_underscored_filename(): void
    {
        $this->assertSame(
            'Rotavator Side Gear',
            CategoryFilenameNameFormatter::format('rotavator_side_gear.png'),
        );
    }

    public function test_formats_filename_preserving_ampersand(): void
    {
        $this->assertSame(
            'Agricultural Machinery & Parts',
            CategoryFilenameNameFormatter::format('agricultural-machinery-&-parts.jpg'),
        );
    }

    public function test_from_new_upload_ignores_stored_path_strings(): void
    {
        $this->assertNull(
            CategoryFilenameNameFormatter::fromNewUpload('categories/livewire-abc123.jpg'),
        );
    }

    public function test_extracts_filename_from_string_path(): void
    {
        $this->assertSame(
            'Rotavator Side Gear',
            CategoryFilenameNameFormatter::fromUploadState('categories/rotavator-side-gear.jpg'),
        );
    }
}
