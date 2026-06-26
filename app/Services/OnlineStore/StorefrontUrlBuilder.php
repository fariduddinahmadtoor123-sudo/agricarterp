<?php

namespace App\Services\OnlineStore;

use App\Support\OnlineStore\ResolvesStorefrontRootUrl;

class StorefrontUrlBuilder
{
    use ResolvesStorefrontRootUrl;

    public function publicAsset(string $path): string
    {
        return $this->rootedUrl('/' . ltrim($path, '/'));
    }

    public function versionedPublicAsset(string $path): string
    {
        $fullPath = public_path($path);
        $version = '1';

        if (is_file($fullPath)) {
            $hash = @md5_file($fullPath);
            $version = is_string($hash) && $hash !== ''
                ? substr($hash, 0, 12)
                : (string) (@filemtime($fullPath) ?: 1);
        }

        return $this->publicAsset($path) . '?v=' . $version;
    }

    public function catalogStylesheetUrl(): string
    {
        return $this->versionedPublicAsset('css/catalog.css');
    }
}
