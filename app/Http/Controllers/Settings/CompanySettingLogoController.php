<?php

namespace App\Http\Controllers\Settings;

use App\Services\Settings\CompanySettingLogoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanySettingLogoController
{
    public function __invoke(Request $request, CompanySettingLogoStorage $storage): StreamedResponse
    {
        $located = $storage->locate($request->query('path'));

        abort_unless($located !== null, 404);

        return Storage::disk($located['disk'])->response($located['path']);
    }
}
