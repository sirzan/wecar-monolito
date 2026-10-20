<?php

namespace App\Services\ExternalSearch;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExternalCarNormalizer
{
    /** @var array<string, array<int, string>> */
    private const IMAGE_CDN_HOSTS = [
        'mercadolibre' => ['mlstatic.com'],
        'seminuevos' => ['latamautos.com', 'seminuevos.com'],
        'autocosmos' => ['acroadtrip.blob.core.windows.net', 'autocosmos.com.mx', 'autocosmos.com'],
        'kavak' => ['kavak.com', 'kavak.mx', 'cloudfront.net', 'amazonaws.com'],
        'facebook' => ['fbcdn.net'],
    ];

    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'ref', 'tracking_id',
        'search_source', 'campaign_id', 'ad_id', 'placement', '_gl', '_ga', 'gad_source',
    ];

    private const BAD_KEYWORDS = [
        'search', 'buscar', 'busqueda', 'catalogo', 'listado', 'categoria',
        'categories', 'noticias', 'news', 'blog', 'terminos', 'privacidad',
        'privacy', 'faq', 'ayuda', 'help', 'login', 'register', 'signup',
        'error', '404', 'notfound', 'contacto', 'financiamiento', 'vender',
        'sell', 'tasacion', 'cotizador', 'garantia',
    ];

    public function cleanUrl(?string $rawUrl, ?string $baseDomain = null): ?string
    {
        if (! is_string($rawUrl)) {
            return null;
        }

        $url = trim($rawUrl);

        if ($url === '' || str_starts_with($url, 'javascript:') || str_starts_with($url, 'mailto:')) {
            return null;
        }

        if (str_starts_with($url, '/') && $baseDomain) {
            $url = 'https://'.$baseDomain.$url;
        } elseif (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = $baseDomain && ! str_contains($url, '.')
                ? 'https://'.$baseDomain.'/'.ltrim($url, '/')
                : 'https://'.ltrim($url, '/');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);

            foreach (self::TRACKING_PARAMS as $param) {
                unset($query[$param]);
            }
        }

        $clean = 'https://'.$parts['host'].($parts['path'] ?? '/');

        if ($query !== []) {
            $clean .= '?'.http_build_query($query);
        }

        return $clean;
    }

    public function isDetailUrl(string $url, string $platformId): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        $path = strtolower($parts['path'] ?? '/');
        $search = strtolower($parts['query'] ?? '');
        $full = $path.$search;

        if (in_array($path, ['/', '/mx', '/mx/', '/auto', '/autos'], true)) {
            return false;
        }

        return match ($platformId) {
            'mercadolibre' => (
                str_contains($path, 'mlm-')
                || str_contains($path, '/mlm')
                || str_contains($search, 'mlm-')
                || str_contains($path, '/articulo')
                || str_contains($host, 'articulo.mercadolibre')
            ) && ! str_contains($path, '/listado/')
                && ! str_contains($path, '/c/')
                && ! str_starts_with($host, 'ayuda.')
                && ! str_starts_with($host, 'myaccount.'),
            'kavak' => (
                str_contains($path, '/comprar-auto/')
                || str_contains($path, '-seminuevo')
                || str_contains($path, '-usado')
                || preg_match('/\/[a-z0-9-]+-\d{4}-id-\d+/i', $path) === 1
                || preg_match('/\/\d{4,}\/?$/', $path) === 1
                || str_contains($search, 'id=')
            ) && ! str_contains($path, '/mx/buscar')
                && ! str_contains($path, '/mx/catalogo')
                && ! str_contains($path, '/mx/marcas')
                && ! str_contains($path, '/mx/sedan')
                && ! str_contains($path, '/mx/suv')
                && ! str_contains($path, '/mx/hatchback')
                && ! str_contains($path, '/mx/pickup'),
            'seminuevos' => (
                str_contains($path, '/auto/')
                || str_contains($path, '/anuncio/')
                || str_contains($path, '/usado/')
                || preg_match('/\/d\/?$/i', $path) === 1
                || preg_match('/\/\d{5,}/', $path) === 1
            ) && ! str_contains($path, '/search')
                && ! str_contains($path, '/marcas')
                && ! str_contains($path, '/categorias'),
            'autocosmos' => (
                str_contains($path, '/auto/usado/')
                || str_contains($path, '/auto/detalle/')
                || str_contains($path, '/usados/')
            ) && ! str_contains($path, '/auto/search')
                && ! str_contains($path, '/catalogo')
                && ! str_contains($path, '/noticias'),
            'facebook' => preg_match('/\/marketplace\/item\/\d+/i', $path) === 1,
            default => collect(self::BAD_KEYWORDS)->doesntContain(fn (string $word) => str_contains($full, $word))
                && count(array_filter(explode('/', $path))) >= 2,
        };
    }

    public function cleanImageUrl(?string $rawUrl): ?string
    {
        if (! is_string($rawUrl)) {
            return null;
        }

        $url = trim($rawUrl);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            return null;
        }

        if (preg_match('/[.\/]$/', $url)) {
            return null;
        }

        $lower = strtolower($url);

        if (str_contains($lower, 'pixel.gif')
            || str_contains($lower, 'spacer.gif')
            || str_contains($lower, '1x1')
            || str_contains($lower, 'blank.gif')
            || str_contains($lower, 'placeholder')) {
            return null;
        }

        return $url;
    }

    public function isValidImage(?string $imageUrl, string $platformId): bool
    {
        $url = $this->cleanImageUrl($imageUrl);

        if (! $url) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $lower = strtolower($url);

        if (preg_match('/\.svg(\?|$)/', $lower) || preg_match('/\.gif(\?|$)/', $lower)) {
            return false;
        }

        if (preg_match('/(logo|sprite|icon|favicon|placeholder|spinner|loader|avatar)/', $lower)) {
            return false;
        }

        $allowed = self::IMAGE_CDN_HOSTS[$platformId] ?? [];

        foreach ($allowed as $domain) {
            if (str_contains($host, $domain)) {
                return true;
            }
        }

        return $allowed === [];
    }

    /**
     * @param  array<string, mixed>  $car
     */
    public function isValidCar(array $car, string $platformId, bool $requireImage = false): bool
    {
        $title = trim((string) ($car['title'] ?? ''));

        if (strlen($title) < 4) {
            return false;
        }

        $lowerTitle = strtolower($title);
        $genericTitles = [
            'seminuevos en venta', 'autos usados', 'catálogo de autos', 'buscar autos',
            'resultados de', 'página no encontrada', 'error 404', 'comprar auto', 'vende tu auto',
        ];

        foreach ($genericTitles as $generic) {
            if ($lowerTitle === $generic || str_starts_with($lowerTitle, $generic)) {
                return false;
            }
        }

        $sourceUrl = $this->cleanUrl((string) ($car['sourceUrl'] ?? ''));

        if (! $sourceUrl || ! $this->isDetailUrl($sourceUrl, $platformId)) {
            return false;
        }

        $price = $car['price'] ?? null;
        $year = $car['year'] ?? null;
        $hasPrice = is_numeric($price) && (float) $price >= 25000 && (float) $price <= 20000000;
        $currentYear = (int) date('Y');
        $hasYear = is_numeric($year) && (int) $year >= 1995 && (int) $year <= $currentYear + 1;

        if (! $hasPrice && ! $hasYear) {
            return false;
        }

        return ! $requireImage || $this->isValidImage(isset($car['imageUrl']) ? (string) $car['imageUrl'] : null, $platformId);
    }

    /**
     * @param  array<string, mixed>  $car
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>|null
     */
    public function normalize(array $car, array $platform, bool $requireImage = false): ?array
    {
        $platformId = (string) $platform['id'];

        if (! $this->isValidCar($car, $platformId, $requireImage)) {
            return null;
        }

        $photos = collect([$car['imageUrl'] ?? null, ...($car['gallery'] ?? [])])
            ->map(fn ($url) => $this->cleanImageUrl(is_string($url) ? $url : null))
            ->filter()
            ->unique()
            ->values();

        $sourceUrl = $this->cleanUrl((string) $car['sourceUrl']);
        $imageUrl = $photos->first();

        return [
            'id' => $this->externalId($sourceUrl),
            'title' => trim((string) $car['title']),
            'price' => is_numeric($car['price'] ?? null) ? (int) round((float) $car['price']) : null,
            'year' => is_numeric($car['year'] ?? null) ? (int) $car['year'] : null,
            'km' => is_numeric($car['km'] ?? null) ? max(0, (int) round((float) $car['km'])) : null,
            'location' => isset($car['location']) && $car['location'] !== '' ? trim((string) $car['location']) : null,
            'imageUrl' => $imageUrl,
            'gallery' => $photos->slice(1)->values()->all(),
            'sourceUrl' => $sourceUrl,
            'platformId' => $platformId,
            'platformName' => (string) ($platform['name'] ?? ''),
            'platformColor' => (string) ($platform['color'] ?? ''),
            'platformInitials' => (string) ($platform['initials'] ?? Str::upper(Str::substr($platformId, 0, 2))),
        ];
    }

    public function externalId(string $sourceUrl): string
    {
        return substr(hash('sha256', strtolower(trim($sourceUrl))), 0, 32);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cars
     * @return array<int, array<string, mixed>>
     */
    public function withIds(array $cars): array
    {
        return array_map(function (array $car): array {
            $car['id'] = $car['id'] ?? $this->externalId((string) ($car['sourceUrl'] ?? ''));

            return $car;
        }, $cars);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cars
     * @return array<int, array<string, mixed>>
     */
    public function toClient(array $cars, bool $allowSourceUrl): array
    {
        return array_map(function (array $car) use ($allowSourceUrl): array {
            $car['sourceUrl'] = $allowSourceUrl ? ($car['sourceUrl'] ?? '') : '';
            unset($car['platformLogo']);

            return $car;
        }, $cars);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cars
     * @return array<int, array<string, mixed>>
     */
    public function sortByPlatform(array $cars): array
    {
        return collect($cars)
            ->sortBy(fn (array $car) => $this->platformPriority((string) ($car['platformId'] ?? '')))
            ->values()
            ->all();
    }

    public function platformPriority(string $platformId): int
    {
        $platforms = collect(config('external_search.platforms', []))->keyBy('id');

        return (int) ($platforms->get($platformId)['priority'] ?? 99);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $platform
     * @return array<int, array<string, mixed>>
     */
    public function fallbackFromPages(array $pages, array $platform, string $query): array
    {
        $cars = [];
        $seen = [];

        foreach ($pages as $page) {
            $content = (string) ($page['content'] ?? '');

            if ($content === '') {
                continue;
            }

            preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', $content, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $title = trim($match[1]);
                $url = $this->cleanUrl($match[2], (string) ($platform['domain'] ?? ''));

                if (! $url || isset($seen[$url]) || ! $this->isDetailUrl($url, (string) $platform['id'])) {
                    continue;
                }

                $candidate = $this->normalize([
                    'title' => $title,
                    'price' => null,
                    'year' => null,
                    'km' => null,
                    'location' => null,
                    'imageUrl' => $this->imageFromMarkdown($content),
                    'sourceUrl' => $url,
                ], $platform, true);

                if ($candidate) {
                    $seen[$url] = true;
                    $cars[] = $candidate;
                }
            }
        }

        return $cars;
    }

    public function imageFromMarkdown(?string $content): ?string
    {
        if (! $content) {
            return null;
        }

        preg_match_all('/!\[[^\]]*\]\((https?:\/\/[^\s)]+)\)/', $content, $matches);

        foreach ($matches[1] ?? [] as $url) {
            $lower = strtolower($url);

            if (preg_match('/\.svg(\?|$)/', $lower)
                || preg_match('/\.gif(\?|$)/', $lower)
                || preg_match('/(logo|sprite|icon|favicon|placeholder|spinner|loader|avatar)/', $lower)) {
                continue;
            }

            return $url;
        }

        return null;
    }

    public function isLinkAlive(string $url): bool
    {
        if (! $this->isSafeExternalUrl($url)) {
            return false;
        }

        try {
            $response = Http::timeout(6)->head($url);

            return ! in_array($response->status(), [404, 410], true);
        } catch (\Throwable) {
            return true;
        }
    }

    public function isSafeExternalUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }
}
