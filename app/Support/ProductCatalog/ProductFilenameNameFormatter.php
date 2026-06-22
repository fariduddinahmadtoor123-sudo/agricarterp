<?php

namespace App\Support\ProductCatalog;

class ProductFilenameNameFormatter
{
    public static function fromNewUpload(mixed $state): ?string
    {
        return CategoryFilenameNameFormatter::fromNewUpload($state);
    }
}
