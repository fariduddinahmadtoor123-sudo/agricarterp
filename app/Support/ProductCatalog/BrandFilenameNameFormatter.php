<?php

namespace App\Support\ProductCatalog;

class BrandFilenameNameFormatter
{
    public static function fromNewUpload(mixed $state): ?string
    {
        return CategoryFilenameNameFormatter::fromNewUpload($state);
    }
}
