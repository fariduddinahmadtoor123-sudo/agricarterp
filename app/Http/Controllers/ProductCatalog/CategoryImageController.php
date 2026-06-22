<?php

namespace App\Http\Controllers\ProductCatalog;

use App\Services\ProductCatalog\CategoryImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryImageController
{
    public function __invoke(Request $request, CategoryImageStorage $storage): StreamedResponse
    {
        $path = (string) $request->query('path', '');
        $path = str_replace(['..', '\\'], '', $path);

        $disk = Storage::disk($storage->disk());

        abort_unless(filled($path) && $disk->exists($path), 404);

        return $disk->response($path);
    }
}
