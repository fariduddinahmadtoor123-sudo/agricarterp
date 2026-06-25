<?php

namespace App\Services\ProductCatalog;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Facades\Filament;

class ProductLabelQrGenerator
{
    public function url(?string $productNumber): ?string
    {
        if (blank($productNumber) || ! $this->isValidProductNumber($productNumber)) {
            return null;
        }

        try {
            return Filament::getPanel(ProductImageStorage::SERVING_PANEL)->route(
                'product-catalog.product-label-qr',
                ['code' => $productNumber],
            );
        } catch (\Throwable) {
            return url('/admin/product-label-qr?' . http_build_query(['code' => $productNumber]));
        }
    }

    public function svg(string $productNumber, int $size = 72): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 0),
                new SvgImageBackEnd,
            ),
        );

        return $writer->writeString($productNumber);
    }

    public function isValidProductNumber(string $productNumber): bool
    {
        return (bool) preg_match('/^PRD-\d{6}$/', $productNumber);
    }
}
