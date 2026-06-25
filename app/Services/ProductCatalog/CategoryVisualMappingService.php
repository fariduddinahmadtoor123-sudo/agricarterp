<?php

namespace App\Services\ProductCatalog;

class CategoryVisualMappingService
{
    /**
     * @var list<string>
     */
    public const LEVEL_LETTERS = ['R', 'M', 'S', 'C', 'L'];

    public function letterForLevel(int $level): string
    {
        if ($level <= 4) {
            return self::LEVEL_LETTERS[$level];
        }

        return 'N';
    }

    public function buildCode(?string $parentVisualMappingCode, int $level, int $sortOrder): string
    {
        $segment = $this->letterForLevel($level) . $sortOrder;

        return $parentVisualMappingCode === null
            ? $segment
            : $parentVisualMappingCode . $segment;
    }
}
