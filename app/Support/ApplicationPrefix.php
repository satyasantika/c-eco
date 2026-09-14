<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Root (c-eco.tech) dan subfolder (/c-eco di supportfkip.unsil.ac.id)
 * hidup di image yang sama. Prefiks dideteksi per permintaan.
 */
final class ApplicationPrefix
{
    /** @var list<string> */
    private const RESERVED = [
        't', 'admin', 'login', 'masuk', 'awas', 'api', 'up',
        'livewire', 'build', 'storage', 'css', 'js', 'fonts',
        'vendor', 'filament',
    ];

    /**
     * @return array{0: string, 1: Request}
     */
    public static function bind(Request $request): array
    {
        $prefix = self::detect($request);

        if ($prefix === '') {
            URL::useOrigin(null);
            URL::useAssetOrigin(null);

            return ['', $request];
        }

        $path = $request->getPathInfo();
        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            $request = self::withoutPrefix($request, $prefix);
        }

        $root = rtrim($request->getSchemeAndHttpHost().$prefix, '/');

        app()->instance('request', $request);
        URL::setRequest($request);
        URL::useOrigin($root);
        URL::useAssetOrigin($root);

        config([
            'app.asset_url' => $root,
            'session.path' => $prefix,
        ]);

        return [$prefix, $request];
    }

    public static function detect(Request $request): string
    {
        $forced = self::normalize((string) config('app.dir', ''));
        if ($forced !== '' && ! self::isReserved($forced)) {
            return $forced;
        }

        $header = self::normalize((string) $request->headers->get('X-Forwarded-Prefix', ''));
        if ($header !== '' && ! self::isReserved($header)) {
            return $header;
        }

        $path = $request->getPathInfo();
        foreach (self::knownFolderNames() as $name) {
            if ($path === '/'.$name || str_starts_with($path, '/'.$name.'/')) {
                return '/'.$name;
            }
        }

        $fromUrl = self::fromAppUrl();
        if ($fromUrl === '') {
            return '';
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '' && strcasecmp($appHost, $request->getHost()) === 0) {
            return $fromUrl;
        }

        return '';
    }

    public static function forConsole(): string
    {
        $forced = self::normalize((string) config('app.dir', ''));
        if ($forced !== '' && ! self::isReserved($forced)) {
            return $forced;
        }

        return self::fromAppUrl();
    }

    /** @return list<string> */
    public static function knownFolderNames(): array
    {
        $names = [];
        foreach ([
            (string) config('app.dir', ''),
            (string) config('app.subdirectory', 'c-eco'),
            trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/'),
        ] as $raw) {
            $folder = strtolower(trim($raw, '/'));
            if ($folder !== '' && ! self::isReserved('/'.$folder) && ! in_array($folder, $names, true)) {
                $names[] = $folder;
            }
        }

        return $names;
    }

    private static function fromAppUrl(): string
    {
        $path = self::normalize((string) parse_url((string) config('app.url'), PHP_URL_PATH));

        return ($path !== '' && ! self::isReserved($path)) ? $path : '';
    }

    private static function withoutPrefix(Request $request, string $prefix): Request
    {
        $remainder = substr($request->getPathInfo(), strlen($prefix)) ?: '/';
        $query = $request->getQueryString();

        $request->server->set('REQUEST_URI', $query ? $remainder.'?'.$query : $remainder);
        $request->server->remove('PATH_INFO');
        $request->server->remove('ORIG_PATH_INFO');

        foreach (['pathInfo', 'requestUri', 'baseUrl', 'basePath'] as $property) {
            $ref = new \ReflectionProperty($request, $property);
            $ref->setValue($request, null);
        }

        URL::setRequest($request);

        return $request;
    }

    private static function normalize(string $value): string
    {
        $trimmed = '/'.trim($value, '/');

        return $trimmed === '/' ? '' : $trimmed;
    }

    private static function isReserved(string $prefix): bool
    {
        $folder = strtolower(trim($prefix, '/'));
        $first = explode('/', $folder)[0] ?? '';

        return $first === '' || in_array($first, self::RESERVED, true);
    }
}
