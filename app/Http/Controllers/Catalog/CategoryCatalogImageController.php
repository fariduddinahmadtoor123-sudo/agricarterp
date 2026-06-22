<?php

namespace App\Http\Controllers\Catalog;

use App\Services\ProductCatalog\CategoryImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Temporary public image serving for the category catalog prototype.
 */
class CategoryCatalogImageController
{
    public function __invoke(Request $request, CategoryImageStorage $storage): StreamedResponse
    {
        $located = $storage->locate($request->query('path'));

        abort_unless($located !== null, 404);

        $response = Storage::disk($located['disk'])->response($located['path']);
        $response->headers->set('Cache-Control', 'public, max-age=86400');

        return $response;
    }
}
