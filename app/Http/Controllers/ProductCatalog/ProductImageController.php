<?php

namespace App\Http\Controllers\ProductCatalog;

use App\Services\ProductCatalog\ProductImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController
{
    public function __invoke(Request $request, ProductImageStorage $storage): StreamedResponse
    {
        $located = $storage->locate($request->query('path'));

        abort_unless($located !== null, 404);

        return Storage::disk($located['disk'])->response($located['path']);
    }
}
