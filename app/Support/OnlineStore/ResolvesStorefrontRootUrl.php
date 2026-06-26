<?php

namespace App\Support\OnlineStore;

trait ResolvesStorefrontRootUrl
{
    protected function rootedUrl(string $relativePath): string
    {
        $relative = '/' . ltrim($relativePath, '/');

        if (app()->bound('request')) {
            $request = request();
            $baseUrl = $request->getBaseUrl();

            if ($baseUrl !== '') {
                return rtrim($request->getSchemeAndHttpHost() . $baseUrl, '/') . $relative;
            }

            $configuredUrl = rtrim((string) config('app.url'), '/');
            $configuredHost = parse_url($configuredUrl, PHP_URL_HOST) ?: '';
            $configuredPath = parse_url($configuredUrl, PHP_URL_PATH) ?: '';

            if (
                $configuredHost === $request->getHost()
                && $configuredPath !== ''
                && $configuredPath !== '/'
            ) {
                return $configuredUrl . $relative;
            }
        }

        return url($relative);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function rootedRoute(string $name, array $parameters = []): string
    {
        return $this->rootedUrl(route($name, $parameters, false));
    }

}
