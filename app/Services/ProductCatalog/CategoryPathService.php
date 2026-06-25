<?php

namespace App\Services\ProductCatalog;

class CategoryPathService
{
    public function buildFullPath(?string $parentFullPath, string $nameEn): string
    {
        if (blank($parentFullPath)) {
            return $nameEn;
        }

        return $parentFullPath . ' › ' . $nameEn;
    }
}
