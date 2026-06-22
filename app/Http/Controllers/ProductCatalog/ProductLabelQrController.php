<?php

namespace App\Http\Controllers\ProductCatalog;

use App\Services\ProductCatalog\ProductLabelQrGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductLabelQrController
{
    public function __invoke(Request $request, ProductLabelQrGenerator $generator): Response
    {
        $code = (string) $request->query('code', '');

        abort_unless($generator->isValidProductNumber($code), 404);

        return response($generator->svg($code), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
