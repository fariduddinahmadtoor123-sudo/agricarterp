<?php

namespace App\Http\Controllers\ProductCatalog;

use App\Services\ProductCatalog\BrandLogoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandImageController
{
    public function __invoke(Request $request, BrandLogoStorage $storage): StreamedResponse
    {
        $located = $storage->locate($request->query('path'));

        abort_unless($located !== null, 404);

        return Storage::disk($located['disk'])->response($located['path']);
    }
}
