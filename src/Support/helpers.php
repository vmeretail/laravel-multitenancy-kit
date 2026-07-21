<?php

declare(strict_types=1);

if (! function_exists('tenant_route')) {
    /**
     * Generate a URL for a named route, replacing the host with the tenant's domain.
     */
    function tenant_route(string $domain, string $routeName, mixed $parameters = [], bool $absolute = true): string
    {
        if (! $absolute) {
            return route($routeName, $parameters, false);
        }

        $url = route($routeName, $parameters, $absolute);

        $parsed = parse_url($url);
        $tenantUrl = tenant_url_parts($domain);

        return $tenantUrl['root']
            .($parsed['path'] ?? '')
            .(isset($parsed['query']) ? '?'.$parsed['query'] : '')
            .(isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '');
    }
}

if (! function_exists('tenant_temporary_signed_route')) {
    /**
     * Generate a temporary signed URL for a named route, using the current
     * tenant's domain as the host.
     *
     * The signature includes the tenant domain, so the route being linked to
     * must be protected by `signed` middleware.
     *
     * @param  array<string, mixed>  $parameters
     */
    function tenant_temporary_signed_route(
        string $routeName,
        DateTimeInterface|int $expiration,
        array $parameters = [],
    ): string {
        foreach (['signature', 'expires'] as $reserved) {
            if (array_key_exists($reserved, $parameters)) {
                throw new InvalidArgumentException(
                    "\"{$reserved}\" is a reserved parameter when generating signed routes. Please rename your route parameter."
                );
            }
        }

        $tenantUrl = tenant_url_parts((string) app('currentTenant')->domain);
        $parameters += [
            'expires' => $expiration instanceof DateTimeInterface
                ? $expiration->getTimestamp()
                : now()->addSeconds($expiration)->getTimestamp(),
        ];

        ksort($parameters);

        $routeUrl = tenant_route($tenantUrl['authority'], $routeName, $parameters);
        $unsignedUrl = $tenantUrl['root'].parse_url($routeUrl, PHP_URL_PATH).'?'.parse_url($routeUrl, PHP_URL_QUERY);
        $signature = hash_hmac('sha256', $unsignedUrl, (string) config('app.key'));

        return "{$unsignedUrl}&signature={$signature}";
    }
}

if (! function_exists('tenant_url_parts')) {
    /**
     * @return array{authority: string, root: string}
     */
    function tenant_url_parts(string $domain): array
    {
        $domain = mb_trim($domain);
        $hasScheme = preg_match('#^https?://#i', $domain) === 1;
        $parsed = parse_url($hasScheme ? $domain : '//'.$domain);

        $host = $parsed['host'] ?? $domain;
        $authority = $host.(isset($parsed['port']) ? ':'.$parsed['port'] : '');
        $scheme = $parsed['scheme'] ?? tenant_default_scheme($host);

        return [
            'authority' => $authority,
            'root' => "{$scheme}://{$authority}",
        ];
    }
}

if (! function_exists('tenant_default_scheme')) {
    function tenant_default_scheme(string $host): string
    {
        $host = mb_strtolower($host);

        if (
            $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test')
        ) {
            return str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';
        }

        return 'https';
    }
}
