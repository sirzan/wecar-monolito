<?php

namespace App\Services\ExternalSearch;

use App\Support\ExternalListingSlug;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExternalCarNormalizer
{
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

    /** @var array<int, string> */
    private const NON_CAR_IMAGE_FRAGMENTS = [
        'static.seminuevos.com/banners/',
        'seminuevos-og-default',
        'og-default',
        '/catalog-ui/',
        '/empty-state/',
        '/growth-blog/',
        'seminuevos_tarjeta',
        'images.unsplash.com',
        'image%20placeholder',
        'image placeholder',
    ];

    /** @var array<int, string> */
    private const PLACEHOLDER_HOSTS = [
        'example.com', 'example.org', 'example.net', 'placeholder.com',
        'via.placeholder.com', 'placehold.co', 'placehold.it', 'dummyimage.com',
        'picsum.photos', 'fakeimg.pl', 'test.com', 'localhost', 'tudominio.com',
    ];

    /** @var array<int, string> */
    private const DUMMY_NUMERIC_IDS = [
        '123456', '654321', '1234567', '7654321', '000000', '111111', '999999',
    ];

    /** @var array<int, string> */
    private const QUERY_STOPWORDS = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'de', 'del', 'en',
        'con', 'sin', 'por', 'para', 'al', 'y', 'o', 'quiero', 'busco', 'necesito',
        'auto', 'autos', 'carro', 'carros', 'coche', 'coches', 'vehiculo', 'vehiculos',
        'camioneta', 'camionetas', 'pickup', 'pick', 'up', 'sedan', 'suv', 'hatchback',
        'coupe', 'seminuevo', 'seminuevos', 'usado', 'usados', 'nuevo', 'nuevos',
        'venta', 'compra', 'precio', 'pesos', 'mxn', 'barato', 'barata', 'economico',
        'economica', 'mexico', 'cdmx', 'monterrey', 'guadalajara', 'automatico',
        'automatica', 'estandar', 'manual', 'cvt', 'menos', 'mas', 'hasta', 'desde',
        'mil', 'millones', 'entre', 'bajo', 'buen', 'estado',
    ];

    /** @var array<string, string> */
    private const BRAND_ALIASES = [
        'vw' => 'volkswagen',
        'chevy' => 'chevrolet',
        'mb' => 'mercedes',
    ];

    /** @var array<int, string> */
    private const TRACKER_IMAGE_HOSTS = [
        'doubleclick.net', 'googlesyndication.com', 'google-analytics.com',
        'googletagmanager.com', 'scorecardresearch.com', 'facebook.com',
    ];

    /** @var array<int, string> */
    private const TRUSTED_IMAGE_CDN_HOSTS = [
        'mlstatic.com', 'kavak.io', 'kavak.services', 'latamautos.com',
        'seminuevos.com', 'autocosmos.com', 'autocosmos.com.mx', 'fbcdn.net',
        'cloudfront.net', 'amazonaws.com', 'cloudinary.com',
    ];

    /** @var array<string, array<int, string>> */
    private const PLATFORM_ALLOWED_HOSTS = [
        'mercadolibre' => ['mercadolibre.com.mx'],
        'seminuevos' => ['seminuevos.com'],
        'kavak' => ['kavak.com', 'kavak.mx'],
        'autocosmos' => ['autocosmos.com.mx'],
        'facebook' => ['facebook.com'],
    ];

    public function cleanUrl(?string $rawUrl, ?string $baseDomain = null): ?string
    {
        if (! is_string($rawUrl)) {
            return null;
        }

        $url = trim($rawUrl);
        $url = str_replace('\#', '#', $url);
        $hashPos = strpos($url, '#');

        if ($hashPos !== false) {
            $url = substr($url, 0, $hashPos);
        }

        $url = trim($url);

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

    public function isPlaceholderUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $lower = strtolower($url);

        foreach (self::PLACEHOLDER_HOSTS as $blockedHost) {
            if ($host === $blockedHost || str_ends_with($host, '.'.$blockedHost)) {
                return true;
            }
        }

        foreach (self::DUMMY_NUMERIC_IDS as $dummyId) {
            if (preg_match('/(?:mlm-?|\/id-|\/|-)'.preg_quote($dummyId, '/').'(?:\b|_|$)/i', $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    public function isDetailUrl(string $url, string $platformId): bool
    {
        if ($this->isPlaceholderUrl($url) || ! $this->isSafeExternalUrl($url)) {
            return false;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        $path = strtolower($parts['path'] ?? '/');
        $search = strtolower($parts['query'] ?? '');
        $full = $path.$search;
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $lastSegment = $segments !== [] ? $segments[count($segments) - 1] : '';

        if (! $this->matchesPlatformHost($host, $platformId)) {
            return false;
        }

        if (in_array($path, ['/', '/mx', '/mx/', '/auto', '/autos'], true) || $this->isCalendarYear($lastSegment)) {
            return false;
        }

        return match ($platformId) {
            'mercadolibre' => (
                preg_match('/mlm-?\d{6,}/i', $full) === 1
                || str_contains($path, '/articulo')
                || (
                    (str_contains($host, 'auto.mercadolibre') || str_contains($host, 'vehiculo.mercadolibre'))
                    && $segments !== []
                    && preg_match('/(?:^|[\/_-])(\d{6,})(?:$|[\/_-])/', $path, $mlMatch) === 1
                    && ! $this->isCalendarYear($mlMatch[1])
                )
            ) && ! str_contains($path, '/listado/')
                && ! str_starts_with($host, 'listado.')
                && ! str_contains($path, '/c/')
                && ! str_contains($path, '/p/')
                && ! str_contains($path, '_noindex')
                && ! str_starts_with($host, 'ayuda.')
                && ! str_starts_with($host, 'myaccount.'),
            'kavak' => (
                str_starts_with($path, '/mx/')
                && ! str_contains($path, '/mx/buscar')
                && ! str_contains($path, '/mx/catalogo')
                && ! str_contains($path, '/mx/marcas')
                && ! str_contains($path, '/mx/sedan')
                && ! str_contains($path, '/mx/suv')
                && ! str_contains($path, '/mx/hatchback')
                && ! str_contains($path, '/mx/pickup')
                && ! str_contains($path, '/mx/vende')
                && ! str_contains($path, '/mx/financiamiento')
                && ! str_contains($path, '/mx/blog')
                && (
                    preg_match('#^/mx/usado/[a-z0-9][a-z0-9_-]{8,}/?$#i', $path) === 1
                    || (preg_match('/-id-(\d+)/i', $path, $kavakId) === 1 && ! $this->isCalendarYear($kavakId[1]))
                    || (
                        preg_match('/(?:^|[\/_-])(\d{5,})\/?$/', $path, $kavakNum) === 1
                        && ! $this->isCalendarYear($kavakNum[1])
                        && (
                            str_contains($path, '/comprar-auto/')
                            || str_contains($path, '/mx/venta/')
                            || str_contains($path, '/mx/compra/')
                            || str_contains($path, '/mx/seminuevos/')
                            || str_contains($path, '/mx/auto/')
                        )
                    )
                    || (preg_match('/(?:^|&)id=(\d{4,})(?:&|$)/i', $search, $kavakQuery) === 1 && ! $this->isCalendarYear($kavakQuery[1]))
                )
            ),
            'seminuevos' => (
                ! str_contains($path, '/usados/')
                && ! str_contains($path, '/search')
                && ! str_contains($path, '/marcas')
                && ! str_contains($path, '/categorias')
                && ! str_contains($path, '/blog')
                && (
                    (str_contains($path, '/vehicle/') && preg_match('/(?:[-_\/])(\d{5,})(?:\.html)?\/?$/', $path, $smId) === 1 && ! $this->isCalendarYear($smId[1]))
                    || (preg_match('/[-_\/](\d{6,})(?:\.html)?\/?$/', $path, $smTrailing) === 1 && ! $this->isCalendarYear($smTrailing[1]))
                    || ((str_contains($path, '/anuncio/') || str_contains($path, '/anuncios/') || str_contains($path, '/usado/')) && count($segments) >= 2 && preg_match('/\d{5,}/', $path) === 1)
                    || preg_match('/\/d\/?$/i', $path) === 1
                )
            ),
            'autocosmos' => (
                (
                    (str_contains($path, '/publications/clasified') || str_contains($path, '/publications/classified'))
                    && preg_match('/(?:^|&)id=\d{4,}(?:&|$)/i', $search) === 1
                )
                || (
                    (
                        str_contains($path, '/auto/usado/')
                        || str_contains($path, '/auto/nuevo/')
                        || str_contains($path, '/auto/detalle/')
                        || str_contains($path, '/usado/')
                        || str_contains($path, '/autos-usados/')
                    ) && count($segments) >= 4
                )
            ) && ! str_contains($path, '/auto/search')
                && ! str_contains($path, '/auto/listado')
                && ! str_contains($path, '/catalogo')
                && ! str_contains($path, '/noticias')
                && ! str_contains($path, '/reviews')
                && ! str_starts_with($host, 'noticias.'),
            'facebook' => preg_match('/\/marketplace\/item\/\d+/i', $path) === 1,
            default => collect(self::BAD_KEYWORDS)->doesntContain(fn (string $word) => str_contains($full, $word))
                && count($segments) >= 2,
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

        if ($this->isPlaceholderUrl($url) || ! $this->isSafeExternalUrl($url)) {
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
        $pathAndQuery = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? '').'?'.(string) (parse_url($url, PHP_URL_QUERY) ?? ''));
        $lower = strtolower($url);

        if (preg_match('/\.svg(\?|$)/', $lower) || preg_match('/\.gif(\?|$)/', $lower)) {
            return false;
        }

        foreach (self::NON_CAR_IMAGE_FRAGMENTS as $fragment) {
            if (str_contains($lower, $fragment)) {
                return false;
            }
        }

        if (preg_match('/(?:^|[\/_.-])(logo|sprite|icon|favicon|placeholder|spinner|loader|avatar|badge|banner|empty-state|og-default)(?:$|[\/_.-])/i', $pathAndQuery)) {
            return false;
        }

        $kavakKey = $this->extractKavakImageKey($url);

        if ($kavakKey !== null) {
            $lowerKey = strtolower($kavakKey);

            if (
                str_contains($lowerKey, 'assets/images/brands/')
                || str_contains($lowerKey, 'assets/images/models/')
                || str_contains($lowerKey, 'placeholder')
                || str_contains($lowerKey, 'catalog-ui')
                || str_contains($lowerKey, 'empty-state')
                || str_contains($lowerKey, 'growth-blog')
                || str_ends_with($lowerKey, '.svg')
                || str_ends_with($lowerKey, '.gif')
            ) {
                return false;
            }
        }

        foreach (self::TRACKER_IMAGE_HOSTS as $trackerHost) {
            if (str_contains($host, $trackerHost)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $car
     */
    public function isValidCar(array $car, string $platformId, bool $requireImage = true): bool
    {
        $title = trim((string) ($car['title'] ?? ''));

        if (strlen($title) < 4 || preg_match('/^\d{4}$/', $title) === 1) {
            return false;
        }

        if (
            preg_match('/^(?:autos|carros|coches|veh[ií]culos|camionetas|listado|cat[aá]logo|resultados)\b/iu', $title) === 1
            || preg_match('/\b(?:usados en venta|seminuevos de agencia|seminuevos en venta)\b/iu', $title) === 1
        ) {
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
     * Verifica que el enlace del vehículo haya aparecido realmente en el contenido o links scrapeados (anti-alucinación).
     *
     * @param  array<string, mixed>  $car
     * @param  array<int, array<string, mixed>>  $pages
     */
    public function isGroundedInPages(array $car, array $pages): bool
    {
        if ($pages === []) {
            return false;
        }

        $sourceUrl = (string) ($car['sourceUrl'] ?? '');

        if ($sourceUrl === '') {
            return false;
        }

        $targetSignature = $this->urlSignature($sourceUrl);
        $targetTokens = $this->extractUniqueListingIds($sourceUrl);
        $knownUrls = [];
        $combinedText = '';

        foreach ($pages as $page) {
            if (! empty($page['url'])) {
                $knownUrls[] = (string) $page['url'];
            }

            foreach ($page['links'] ?? [] as $link) {
                if (is_string($link) && $link !== '') {
                    $knownUrls[] = $link;
                }
            }

            $content = (string) ($page['content'] ?? '');

            if ($content !== '') {
                $combinedText .= "\n".$content;
            }
        }

        foreach ($knownUrls as $knownUrl) {
            if ($this->urlSignature($knownUrl) === $targetSignature) {
                return true;
            }
        }

        if ($combinedText !== '' && str_contains(strtolower($combinedText), strtolower($sourceUrl))) {
            return true;
        }

        // Si el link tiene un ID numérico único (ej. MLM3344556677 o 4842765) presente dentro de los links reales, también es válido.
        if ($targetTokens !== []) {
            $knownPool = strtolower(implode("\n", $knownUrls)."\n".$combinedText);

            foreach ($targetTokens as $idToken) {
                if (str_contains($knownPool, strtolower($idToken))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verifica en paralelo que las fotos carguen como imágenes reales y los enlaces no estén rotos o redirigidos a listados.
     *
     * @param  array<int, array<string, mixed>>  $cars
     * @return array<int, array<string, mixed>>
     */
    public function verifyLiveCars(array $cars): array
    {
        if ($cars === []) {
            return [];
        }

        $imageResponses = Http::pool(function (Pool $pool) use ($cars): array {
            $requests = [];

            foreach ($cars as $index => $car) {
                $imageUrl = (string) ($car['imageUrl'] ?? '');

                if ($imageUrl !== '' && $this->isSafeExternalUrl($imageUrl)) {
                    $requests[] = $pool->as('img_'.$index)
                        ->connectTimeout(2)
                        ->timeout(3)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; WecarBot/1.0)'])
                        ->head($imageUrl);
                }

                $sourceUrl = (string) ($car['sourceUrl'] ?? '');

                if ($sourceUrl !== '' && $this->isSafeExternalUrl($sourceUrl)) {
                    $requests[] = $pool->as('link_'.$index)
                        ->connectTimeout(2)
                        ->timeout(3)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                            'Range' => 'bytes=0-8192',
                        ])
                        ->get($sourceUrl);
                }
            }

            return $requests;
        });

        $verified = [];

        foreach ($cars as $index => $car) {
            $imageOk = $this->checkImageResponse($imageResponses['img_'.$index] ?? null, (string) ($car['imageUrl'] ?? ''));

            if (! $imageOk) {
                $fallbackPhoto = $this->findLiveGalleryPhoto($car);

                if ($fallbackPhoto === null) {
                    continue;
                }

                $car['imageUrl'] = $fallbackPhoto;
            }

            if (! $this->checkSourceResponse($imageResponses['link_'.$index] ?? null, (string) ($car['sourceUrl'] ?? ''), (string) ($car['platformId'] ?? ''))) {
                continue;
            }

            $verified[] = $car;
        }

        return $verified;
    }

    /**
     * Verifica que el anuncio coincida con las especificaciones principales (marca, modelo y año) sin descartar falsos negativos por ciudad o versión larga.
     *
     * @param  array<string, mixed>  $car
     * @param  array<string, mixed>  $specs
     */
    public function matchesQuery(array $car, string $query, array $specs = []): bool
    {
        $trimmedQuery = trim($query);
        $brandSpec = is_string($specs['brand'] ?? null) ? trim($specs['brand']) : '';
        $modelSpec = is_string($specs['model'] ?? null) ? trim($specs['model']) : '';
        $yearSpec = is_numeric($specs['year'] ?? null) ? (int) $specs['year'] : null;

        if ($trimmedQuery === '' && $brandSpec === '' && $modelSpec === '' && $yearSpec === null) {
            return true;
        }

        $title = (string) ($car['title'] ?? '');
        $sourceUrl = (string) ($car['sourceUrl'] ?? '');
        $location = (string) ($car['location'] ?? '');
        $urlPath = (string) (parse_url($sourceUrl, PHP_URL_PATH) ?: '');
        $coreHaystack = Str::lower(Str::ascii($title.' '.$urlPath));
        $fullHaystack = Str::lower(Str::ascii($title.' '.$urlPath.' '.$location));
        $haystackTokens = array_values(array_filter(preg_split('/[^a-z0-9]+/', $coreHaystack) ?: []));
        $haystackCompact = preg_replace('/[^a-z0-9]/', '', $coreHaystack) ?? '';

        $queryYears = $yearSpec !== null ? [$yearSpec] : $this->extractYears($trimmedQuery);

        if ($queryYears !== []) {
            $carYear = isset($car['year']) && is_numeric($car['year']) ? (int) $car['year'] : null;
            $textYears = $this->extractYears($fullHaystack);

            if ($carYear !== null && ! in_array($carYear, $queryYears, true)) {
                return false;
            }

            if ($carYear === null && $textYears !== [] && array_intersect($queryYears, $textYears) === []) {
                return false;
            }

            // En Facebook Marketplace los vendedores suelen omitir el año en el título; permitimos si marca y modelo coinciden.
            if ($carYear === null && $textYears === [] && ($car['platformId'] ?? '') !== 'facebook') {
                return false;
            }
        }

        // Cuando llegan especificaciones estructuradas, marca y modelo son obligatorios; versión y ciudad priorizan el ranking sin matar resultados.
        $requiredQuery = ($brandSpec !== '' || $modelSpec !== '')
            ? trim($brandSpec.' '.$modelSpec)
            : $trimmedQuery;

        $queryTokens = $this->extractQueryTokens($requiredQuery);

        if ($queryTokens === []) {
            return true;
        }

        $matchedCount = 0;

        foreach ($queryTokens as $index => $token) {
            $variants = [$token];

            if (isset(self::BRAND_ALIASES[$token])) {
                $variants[] = self::BRAND_ALIASES[$token];
            }

            $reverseAlias = array_search($token, self::BRAND_ALIASES, true);

            if ($reverseAlias !== false) {
                $variants[] = $reverseAlias;
            }

            $matched = false;

            foreach ($variants as $variant) {
                if (in_array($variant, $haystackTokens, true)
                    || (strlen($variant) >= 4 && str_contains($haystackCompact, $variant))) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                $matchedCount++;
            } elseif ($index === 0 && count($queryTokens) <= 3) {
                return false;
            }
        }

        if (count($queryTokens) <= 2) {
            return $matchedCount === count($queryTokens);
        }

        return $matchedCount >= max(2, (int) ceil(count($queryTokens) * 0.7));
    }

    /**
     * @param  array<string, mixed>  $car
     * @param  array<string, mixed>  $platform
     * @return array<string, mixed>|null
     */
    public function normalize(array $car, array $platform, bool $requireImage = true): ?array
    {
        $platformId = (string) $platform['id'];

        if (! $this->isValidCar($car, $platformId, $requireImage)) {
            return null;
        }

        $photos = collect([$car['imageUrl'] ?? null, ...($car['gallery'] ?? [])])
            ->map(function ($url) use ($platformId): ?string {
                $cleaned = $this->cleanImageUrl(is_string($url) ? $url : null);

                return $cleaned && $this->isValidImage($cleaned, $platformId) ? $cleaned : null;
            })
            ->filter()
            ->unique()
            ->values();

        $sourceUrl = $this->cleanUrl((string) $car['sourceUrl']);
        $imageUrl = $photos->first();

        if ($requireImage && $imageUrl === null) {
            return null;
        }

        $title = $this->sanitizeListingTitle((string) ($car['title'] ?? ''), (string) $sourceUrl);

        if ($title === '') {
            return null;
        }

        $rawPrice = is_numeric($car['price'] ?? null) ? (int) round((float) $car['price']) : null;
        $normalizedPrice = ($rawPrice !== null && $rawPrice >= 25_000 && $rawPrice <= 20_000_000) ? $rawPrice : null;

        return [
            'id' => $this->externalId($sourceUrl),
            'slug' => ExternalListingSlug::make(
                is_numeric($car['year'] ?? null) ? (int) $car['year'] : null,
                $title,
                $this->externalId($sourceUrl),
            ),
            'title' => $title,
            'price' => $normalizedPrice,
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
            $car['url'] = route('external.detail', $car['slug'] ?? $car['id']);
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
            ->sortBy(fn (array $car) => sprintf(
                '%d-%d-%02d',
                filled($car['imageUrl'] ?? null) ? 0 : 1,
                is_numeric($car['price'] ?? null) ? 0 : 1,
                $this->platformPriority((string) ($car['platformId'] ?? '')),
            ))
            ->values()
            ->all();
    }

    /**
     * Ordena priorizando coincidencias con versión/ciudad opcionales, foto, precio y prioridad de plataforma.
     *
     * @param  array<int, array<string, mixed>>  $cars
     * @param  array<string, mixed>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function rankBySpecs(array $cars, array $specs = []): array
    {
        if ($specs === []) {
            return $this->sortByPlatform($cars);
        }

        $versionTokens = is_string($specs['version'] ?? null) && trim($specs['version']) !== ''
            ? $this->extractQueryTokens($specs['version'])
            : [];
        $cityNeedle = is_string($specs['city'] ?? null) && trim($specs['city']) !== ''
            ? Str::lower(Str::ascii(trim($specs['city'])))
            : '';

        return collect($cars)
            ->sortBy(function (array $car) use ($versionTokens, $cityNeedle): string {
                $haystack = Str::lower(Str::ascii(
                    (string) ($car['title'] ?? '').' '.(string) ($car['sourceUrl'] ?? '').' '.(string) ($car['location'] ?? '')
                ));

                $versionMiss = 0;

                if ($versionTokens !== []) {
                    $matchedVersion = 0;

                    foreach ($versionTokens as $token) {
                        if (str_contains($haystack, $token)) {
                            $matchedVersion++;
                        }
                    }

                    $versionMiss = $matchedVersion > 0 ? 0 : 1;
                }

                $cityMiss = ($cityNeedle !== '' && ! str_contains($haystack, $cityNeedle)) ? 1 : 0;

                return sprintf(
                    '%d-%d-%d-%d-%02d',
                    filled($car['imageUrl'] ?? null) ? 0 : 1,
                    is_numeric($car['price'] ?? null) ? 0 : 1,
                    $versionMiss,
                    $cityMiss,
                    $this->platformPriority((string) ($car['platformId'] ?? '')),
                );
            })
            ->values()
            ->all();
    }

    /**
     * Intercala resultados por plataforma para entregar hasta $limit autos diversos con foto y precio verificados.
     *
     * @param  array<int, array<string, mixed>>  $cars
     * @param  array<string, mixed>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function balanceByPlatform(array $cars, int $limit = 15, array $specs = []): array
    {
        $sorted = $this->rankBySpecs($cars, $specs);
        $buckets = [];

        foreach ($sorted as $car) {
            $platformId = (string) ($car['platformId'] ?? 'other');
            $buckets[$platformId][] = $car;
        }

        $balanced = [];

        while (count($balanced) < $limit && $buckets !== []) {
            foreach (array_keys($buckets) as $platformId) {
                $item = array_shift($buckets[$platformId]);

                if ($item !== null) {
                    $balanced[] = $item;
                }

                if ($buckets[$platformId] === []) {
                    unset($buckets[$platformId]);
                }

                if (count($balanced) >= $limit) {
                    break;
                }
            }
        }

        return $balanced;
    }

    public function platformPriority(string $platformId): int
    {
        $platforms = collect(config('external_search.platforms', []))->keyBy('id');

        return (int) ($platforms->get($platformId)['priority'] ?? 99);
    }

    /**
     * Extrae candidatos reales con foto desde fichas directas o enlaces cercanos en Markdown.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<int, array<string, mixed>>
     */
    public function fallbackFromPages(array $pages, array $platform, string $query, array $specs = []): array
    {
        $cars = [];
        $seen = [];
        $platformId = (string) ($platform['id'] ?? '');
        $defaultDomain = (string) ($platform['domain'] ?? '');
        $currentYear = (int) date('Y');

        foreach ($pages as $page) {
            $rawContent = (string) ($page['content'] ?? '');
            $pageUrl = $this->cleanUrl((string) ($page['url'] ?? ''), $defaultDomain);
            $pageHost = $pageUrl ? ((string) parse_url($pageUrl, PHP_URL_HOST) ?: $defaultDomain) : $defaultDomain;
            $content = $this->absolutizeMarkdownUrls($rawContent, $pageHost);

            $rawOgImage = $this->cleanImageUrl(is_string($page['ogImage'] ?? null) ? $page['ogImage'] : null);
            $pageOgImage = ($rawOgImage && $this->isValidImage($rawOgImage, $platformId)) ? $rawOgImage : null;
            $pageImages = array_values(array_filter(
                array_map(fn ($img) => is_string($img) ? $this->cleanImageUrl($img) : null, is_array($page['images'] ?? null) ? $page['images'] : []),
                fn (?string $img) => $img !== null && $this->isValidImage($img, $platformId),
            ));

            // 1. Si la propia URL devuelta por Firecrawl es una ficha individual real, extraer directo sus datos.
            if ($pageUrl && ! isset($seen[$pageUrl]) && $this->isDetailUrl($pageUrl, $platformId)) {
                $rawPageTitle = trim((string) ($page['title'] ?? ''));
                $title = $this->cleanListingTitle($rawPageTitle, $pageUrl);

                if ($rawPageTitle === '' && $content !== '') {
                    $title = $this->extractTitleFromWindow($content, $pageUrl);
                }

                $years = $this->extractYears($title.' '.$pageUrl.' '.$content);
                $year = $years[0] ?? null;

                if ($year !== null && ($year < 1995 || $year > $currentYear + 1)) {
                    $year = null;
                }

                $image = $this->matchImageForListing($pageUrl, $pageImages)
                    ?? $pageOgImage
                    ?? $this->imageFromMarkdown($content, $platformId)
                    ?? ($pageImages[0] ?? null);

                $candidate = $this->normalize([
                    'title' => $title,
                    'price' => $this->extractPriceFromText($content),
                    'year' => $year,
                    'km' => $this->extractKmFromText($content),
                    'location' => null,
                    'imageUrl' => $image,
                    'gallery' => array_slice($pageImages, 0, 5),
                    'sourceUrl' => $pageUrl,
                ], $platform, true);

                if ($candidate && $this->matchesQuery($candidate, $query, $specs)) {
                    $seen[$pageUrl] = true;
                    $cars[] = $candidate;
                }
            }

            if ($content === '') {
                continue;
            }

            // 2. Extraer tarjetas Markdown completas (incluyendo bloques multilínea con imagen anidada de Kavak/Seminuevos/Mercado Libre).
            $processed = [];
            $groupedCards = $this->extractMarkdownCardGroups($content, $pageHost, $platformId);

            foreach ($groupedCards as $url => $card) {
                if (isset($seen[$url])) {
                    continue;
                }

                $processed[$url] = true;
                $anchorText = $card['anchor'];
                $contextText = $card['context'];
                $title = $this->extractCardTitle($anchorText, $contextText, $url);
                $image = $card['image']
                    ?? $this->imageFromMarkdown($anchorText."\n".$contextText, $platformId)
                    ?? $this->matchImageForListing($url, $pageImages);

                $candidate = $this->buildListingCandidateFromWindow(
                    $url,
                    $title,
                    $anchorText."\n".$contextText,
                    $pageImages,
                    $platform,
                    $query,
                    $specs,
                    $image,
                );

                if ($candidate) {
                    $seen[$url] = true;
                    $cars[] = $candidate;
                }
            }

            $lines = preg_split('/\R+/', $content) ?: [];
            $lineCount = count($lines);

            // 3. Rescatar links de detalle detectados en la página que tengan tarjeta/precio en Markdown y foto emparejada.
            foreach ($page['links'] ?? [] as $rawLink) {
                if (! is_string($rawLink) || $rawLink === '') {
                    continue;
                }

                $url = $this->cleanUrl($rawLink, $pageHost);

                if (! $url || isset($seen[$url]) || isset($processed[$url]) || ! $this->isDetailUrl($url, $platformId)) {
                    continue;
                }

                $matchIndex = $this->findLineIndexForListing($lines, $url);

                if ($matchIndex === null) {
                    continue;
                }

                $windowText = $this->cardWindow($lines, $matchIndex, $lineCount, 1, 4);
                $candidate = $this->buildListingCandidateFromWindow(
                    $url,
                    $this->extractTitleFromWindow($windowText, $url, $lines[$matchIndex] ?? ''),
                    $windowText,
                    $pageImages,
                    $platform,
                    $query,
                    $specs,
                );

                if ($candidate) {
                    $seen[$url] = true;
                    $cars[] = $candidate;
                }
            }
        }

        return $cars;
    }

    public function imageFromMarkdown(?string $content, string $platformId = ''): ?string
    {
        if (! $content) {
            return null;
        }

        preg_match_all('/!\[[^\]]*\]\((https?:\/\/[^\s)]+)\)/', $content, $matches);

        foreach ($matches[1] ?? [] as $url) {
            if ($this->isValidImage($url, $platformId)) {
                return $this->cleanImageUrl($url);
            }
        }

        return null;
    }

    public function isLinkAlive(string $url): bool
    {
        if (! $this->isSafeExternalUrl($url) || $this->isPlaceholderUrl($url)) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Range' => 'bytes=0-16384',
                ])
                ->get($url);

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

    private function matchesPlatformHost(string $host, string $platformId): bool
    {
        $allowed = self::PLATFORM_ALLOWED_HOSTS[$platformId] ?? [];

        if ($allowed === []) {
            return true;
        }

        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private function urlSignature(string $url): string
    {
        $parts = parse_url(strtolower(trim($url)));
        $host = preg_replace('/^www\./', '', (string) ($parts['host'] ?? ''));
        $path = rtrim((string) ($parts['path'] ?? '/'), '/');

        return $host.$path;
    }

    /**
     * @return array<int, string>
     */
    private function extractUniqueListingIds(string $url): array
    {
        $ids = [];
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        if (preg_match('/(mlm-?\d{7,})/i', $url, $match)) {
            $ids[] = str_replace('-', '', $match[1]);
            $ids[] = $match[1];
        }

        if (preg_match('/(?:\/id-|-id-|\/|-)(\d{5,})(?:\/|$|\?)/i', $path, $match) && ! $this->isCalendarYear($match[1])) {
            $ids[] = $match[1];
        }

        return array_values(array_unique($ids));
    }

    public function extractKavakImageKey(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! str_contains($host, 'kavak.io')) {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if (! str_starts_with($path, 'eyJ')) {
            return null;
        }

        $decoded = base64_decode(strtr($path, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $json = json_decode($decoded, true);

        return is_string($json['key'] ?? null) ? $json['key'] : null;
    }

    /**
     * @param  array<int, string>  $pageImages
     */
    private function matchImageForListing(string $listingUrl, array $pageImages): ?string
    {
        if ($pageImages === []) {
            return null;
        }

        foreach ($this->extractUniqueListingIds($listingUrl) as $idToken) {
            $needle = strtolower($idToken);

            foreach ($pageImages as $imageUrl) {
                if (str_contains(strtolower($imageUrl), $needle)) {
                    return $imageUrl;
                }

                $kavakKey = $this->extractKavakImageKey($imageUrl);

                if ($kavakKey !== null && str_contains(strtolower($kavakKey), $needle)) {
                    return $imageUrl;
                }
            }
        }

        return null;
    }

    private function isCalendarYear(string|int $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        $year = (int) $value;

        return $year >= 1980 && $year <= ((int) date('Y') + 2);
    }

    private function absolutizeMarkdownUrls(string $content, string $host): string
    {
        if ($content === '' || $host === '') {
            return $content;
        }

        return preg_replace(
            '/\]\((\/[^)\s]+)\)/',
            '](https://'.$host.'$1)',
            $content,
        ) ?? $content;
    }

    /**
     * Construye una ventana acotada alrededor de la tarjeta actual (priorizando la propia línea y las siguientes).
     *
     * @param  array<int, string>  $lines
     */
    private function cardWindow(array $lines, int $index, int $lineCount, int $before, int $after): string
    {
        $start = max(0, $index - $before);
        $end = min($lineCount - 1, $index + $after);

        return implode("\n", array_slice($lines, $start, ($end - $start) + 1));
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function findLineIndexForListing(array $lines, string $url): ?int
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        $ids = $this->extractUniqueListingIds($url);

        foreach ($lines as $index => $line) {
            $lowerLine = strtolower($line);

            if ($path !== '' && $path !== '/' && str_contains($lowerLine, $path)) {
                return $index;
            }

            foreach ($ids as $idToken) {
                if (str_contains($lowerLine, strtolower($idToken))) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function extractTitleFromWindow(string $windowText, string $url, string $preferredLine = ''): string
    {
        $lines = preg_split('/\R+/', $windowText) ?: [];

        if ($preferredLine !== '') {
            array_unshift($lines, $preferredLine);
        }

        foreach ($lines as $line) {
            $plain = trim(strip_tags(preg_replace('/!\[[^\]]*\]\([^)]*\)|\[([^\]]*)\]\([^)]*\)/', '$1', $line) ?? $line));
            $plain = trim(ltrim($plain, "#*- \t"));

            if ($plain === '' || strlen($plain) < 5 || str_starts_with($plain, '$')) {
                continue;
            }

            if (preg_match('/^\d[\d,.]*\s*km\b/i', $plain) === 1) {
                continue;
            }

            if ($this->extractYears($plain) !== [] && strlen($plain) <= 95) {
                return $this->sanitizeListingTitle($plain, $url);
            }
        }

        return $this->sanitizeListingTitle('', $url);
    }

    private function cleanListingTitle(string $title, string $url): string
    {
        $cleaned = trim(preg_replace('/\s*(?:\|\s*(?:Seminuevos\.com|Kavak.*|Autocosmos.*|Mercado\s*Libre.*)|(?:usado\s+en\s+venta.*|-\s*Autocosmos.*))$/iu', '', $title) ?? $title);

        return $cleaned !== '' ? $cleaned : $this->titleFromUrlSlug($url);
    }

    /**
     * Limpia títulos provenientes de Markdown o scrapeo y evita títulos vacíos, URLs o años sueltos.
     */
    public function sanitizeListingTitle(string $title, string $fallbackUrl = ''): string
    {
        $value = trim($title);

        if ($value === '') {
            return trim($this->titleFromUrlSlug($fallbackUrl));
        }

        $value = (string) preg_replace('/!\[([^\]]*)\]\([^)]*\)/u', '$1', $value);
        $value = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $value);
        $value = strip_tags($value);
        $value = (string) preg_replace('/\s*(?:\|\s*(?:Seminuevos\.com|Kavak.*|Autocosmos.*|Mercado\s*Libre.*)|(?:usado\s+en\s+venta.*|-\s*Autocosmos.*))$/iu', ' ', $value);
        $value = str_replace(['\\', '!', '[', ']', "\u{00A0}"], ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);
        $value = trim($value, " \t\n\r\0\x0B-|");

        $isUnusable = $value === ''
            || mb_strlen($value) < 5
            || preg_match('~^https?://~i', $value) === 1
            || preg_match('/^\d{4}$/', $value) === 1
            || preg_match('/^[\d\s.,-]+$/u', $value) === 1;

        if ($isUnusable) {
            $value = trim($this->titleFromUrlSlug($fallbackUrl));
        }

        return $value;
    }

    private function titleFromUrlSlug(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if ($segments === []) {
            return '';
        }

        $slug = $segments[count($segments) - 1];

        if (preg_match('/^\d+$/', $slug) === 1 && count($segments) >= 2) {
            $slug = $segments[count($segments) - 2];
        }

        $slug = preg_replace('/^(?:autos|auto|mlm)-?/i', '', $slug) ?? $slug;
        $slug = preg_replace('/(?:-id-\d+|-\d{5,}|-_jm)$/i', '', $slug) ?? $slug;
        $words = preg_split('/[-_]+/', $slug) ?: [];
        $words = array_values(array_filter($words, fn (string $w): bool => $w !== '' && strtolower($w) !== 'mx'));

        return Str::title(implode(' ', $words));
    }

    /**
     * Agrupa enlaces Markdown de tarjetas (incluyendo bloques multilínea e imágenes anidadas) por URL de ficha individual.
     *
     * @return array<string, array{anchor: string, context: string, image: ?string}>
     */
    private function extractMarkdownCardGroups(string $content, string $pageHost, string $platformId): array
    {
        $pattern = '/(?<!!)\[((?:!\[[^\]]*\]\([^)]+\)|[^\[\]])+)\]\((https?:\/\/[^\s)]+)\)/su';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $contentLength = strlen($content);
        $groups = [];

        foreach ($matches as $match) {
            $rawInner = (string) $match[1][0];
            $rawUrl = (string) $match[2][0];
            $offset = (int) $match[0][1];
            $matchLength = strlen((string) $match[0][0]);

            $url = $this->cleanUrl($rawUrl, $pageHost);

            if (! $url || ! $this->isDetailUrl($url, $platformId)) {
                continue;
            }

            $innerImage = $this->imageFromMarkdown($rawInner, $platformId);
            $beforeStart = max(0, $offset - 420);
            $beforeSnippet = substr($content, $beforeStart, $offset - $beforeStart);
            $afterSnippet = substr($content, $offset + $matchLength, 420);

            // Para tarjetas como Mercado Libre donde la imagen viene justo antes de ### [Título](url) y precio/año/km justo después.
            $beforeImage = null;

            if ($innerImage === null && preg_match_all('/!\[[^\]]*\]\((https?:\/\/[^\s)]+)\)/', $beforeSnippet, $beforeImgs)) {
                foreach (array_reverse($beforeImgs[1]) as $candidateImg) {
                    if ($this->isValidImage($candidateImg, $platformId)) {
                        $beforeImage = $this->cleanImageUrl($candidateImg);
                        break;
                    }
                }
            }

            // Recortamos afterSnippet en la siguiente tarjeta para no mezclar precios/km de otro auto.
            $afterTrimmed = preg_split('/\n\s*(?:\d+\.\s+!\[|\[!\[)/', $afterSnippet)[0] ?? $afterSnippet;

            if (! isset($groups[$url])) {
                $groups[$url] = [
                    'anchor' => $rawInner,
                    'context' => $afterTrimmed,
                    'image' => $innerImage ?? $beforeImage,
                ];
            } else {
                $groups[$url]['anchor'] .= "\n".$rawInner;

                if ($groups[$url]['image'] === null) {
                    $groups[$url]['image'] = $innerImage ?? $beforeImage;
                }

                if (strlen($afterTrimmed) > strlen($groups[$url]['context'])) {
                    $groups[$url]['context'] = $afterTrimmed;
                }
            }
        }

        return $groups;
    }

    private function extractCardTitle(string $anchorText, string $contextText, string $url): string
    {
        $withoutImages = trim((string) preg_replace('/!\[([^\]]*)\]\([^)]+\)/u', ' ', $anchorText));
        $cleanedLines = array_values(array_filter(array_map(
            static fn (string $line): string => trim(str_replace(['**', '__', '\\'], '', $line), " \t\n\r\0\x0B#*-|"),
            preg_split('/\R+/', $withoutImages) ?: [],
        )));

        $brandModelLine = null;
        $versionYearLine = null;

        foreach ($cleanedLines as $line) {
            if (
                $line === ''
                || str_starts_with($line, '$')
                || preg_match('/^(?:gold|precio desde|único dueño|unico dueno|enganche desde|envío|envio|\d{4})$/iu', $line) === 1
                || preg_match('/^\d[\d,.]*\s*kms?\.?/i', $line) === 1
                || str_contains($line, '/ mes')
            ) {
                continue;
            }

            if ($brandModelLine === null && mb_strlen($line) >= 5 && mb_strlen($line) <= 95) {
                if (preg_match('/[·•]/u', $line) === 1 || $this->extractYears($line) !== []) {
                    $brandModelLine = $line;

                    continue;
                }
            }

            if ($brandModelLine !== null && $versionYearLine === null && $this->extractYears($line) !== [] && mb_strlen($line) <= 95) {
                $versionYearLine = $line;
                break;
            }
        }

        if ($brandModelLine !== null) {
            $base = preg_replace('/\s*[·•]\s*/u', ' ', $brandModelLine) ?? $brandModelLine;
            $years = $this->extractYears($base);

            if ($years === []) {
                $allYears = $this->extractYears($anchorText.' '.$contextText.' '.$url);

                if ($allYears !== []) {
                    $base .= ' '.$allYears[0];
                }
            }

            return $this->sanitizeListingTitle($base, $url);
        }

        if ($cleanedLines !== [] && count($cleanedLines) === 1 && mb_strlen($cleanedLines[0]) >= 5) {
            return $this->sanitizeListingTitle($cleanedLines[0], $url);
        }

        return $this->extractTitleFromWindow($anchorText."\n".$contextText, $url);
    }

    /**
     * @param  array<int, string>  $pageImages
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $specs
     * @return array<string, mixed>|null
     */
    private function buildListingCandidateFromWindow(
        string $url,
        string $title,
        string $windowText,
        array $pageImages,
        array $platform,
        string $query,
        array $specs = [],
        ?string $preferredImage = null,
    ): ?array {
        $platformId = (string) ($platform['id'] ?? '');
        $currentYear = (int) date('Y');
        $price = $this->extractPriceFromText($windowText);
        $years = $this->extractYears($title.' '.$url.' '.$windowText);
        $year = $years[0] ?? null;

        if ($year !== null && ($year < 1995 || $year > $currentYear + 1)) {
            $year = null;
        }

        $image = $preferredImage
            ?? $this->imageFromMarkdown($windowText, $platformId)
            ?? $this->matchImageForListing($url, $pageImages);

        $candidate = $this->normalize([
            'title' => $this->cleanListingTitle($title, $url),
            'price' => $price,
            'year' => $year,
            'km' => $this->extractKmFromText($windowText),
            'location' => $this->extractLocationFromText($windowText),
            'imageUrl' => $image,
            'sourceUrl' => $url,
        ], $platform, true);

        return ($candidate && $this->matchesQuery($candidate, $query, $specs)) ? $candidate : null;
    }

    private function checkImageResponse(mixed $response, string $imageUrl): bool
    {
        if ($imageUrl === '' || $this->isPlaceholderUrl($imageUrl)) {
            return false;
        }

        if ($response instanceof \Throwable) {
            // Ante timeouts o fallos de red no descartamos la foto: priorizamos mostrar el auto.
            return true;
        }

        if (! $response instanceof Response) {
            return true;
        }

        // Algunos CDN responden 405 a HEAD o 403 a bots de nube: evitamos retries secuenciales lentos en CDNs confiables.
        if (in_array($response->status(), [403, 405], true)) {
            $host = strtolower((string) parse_url($imageUrl, PHP_URL_HOST));

            foreach (self::TRUSTED_IMAGE_CDN_HOSTS as $cdnHost) {
                if ($host === $cdnHost || str_ends_with($host, '.'.$cdnHost)) {
                    return true;
                }
            }

            try {
                $retry = Http::connectTimeout(2)
                    ->timeout(3)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                        'Range' => 'bytes=0-1024',
                    ])
                    ->get($imageUrl);

                if (in_array($retry->status(), [404, 410], true) || $retry->serverError()) {
                    return false;
                }

                $contentType = strtolower((string) $retry->header('Content-Type'));

                if ($contentType !== '' && (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/json'))) {
                    return false;
                }

                return $retry->successful() || $retry->status() === 403;
            } catch (\Throwable) {
                return false;
            }
        }

        if (in_array($response->status(), [404, 410], true)) {
            return false;
        }

        // 403, 429 o 5xx suelen ser bloqueos temporales de CDN: conservamos la foto.
        if ($response->status() >= 400) {
            return true;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if ($contentType !== '' && ! str_contains($contentType, 'image/') && ! str_contains($contentType, 'octet-stream')) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $car
     */
    private function findLiveGalleryPhoto(array $car): ?string
    {
        foreach (array_slice($car['gallery'] ?? [], 0, 2) as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            try {
                $response = Http::connectTimeout(2)
                    ->timeout(2)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; WecarBot/1.0)'])
                    ->head($candidate);

                if ($this->checkImageResponse($response, $candidate)) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function checkSourceResponse(mixed $response, string $sourceUrl, string $platformId): bool
    {
        if ($sourceUrl === '' || $this->isPlaceholderUrl($sourceUrl)) {
            return false;
        }

        if ($response instanceof \Throwable) {
            // Si la falla fue por dominio inexistente o conexión rechazada no verificada, rechazamos el enlace.
            $message = strtolower($response->getMessage());

            if (str_contains($message, 'could not resolve host') || str_contains($message, 'name or service not known')) {
                return false;
            }

            // Timeouts o errores de red temporales: conservamos el anuncio.
            return true;
        }

        if (! $response instanceof Response) {
            return true;
        }

        if (in_array($response->status(), [404, 410], true)) {
            return false;
        }

        $effectiveUri = (string) ($response->effectiveUri() ?? '');

        if ($effectiveUri !== '' && $this->urlSignature($effectiveUri) !== $this->urlSignature($sourceUrl)) {
            if (! $this->isDetailUrl($effectiveUri, $platformId)) {
                return false;
            }
        }

        if ($response->successful()) {
            $body = Str::lower(Str::ascii(mb_substr($response->body(), 0, 24_000)));
            $deadSignals = [
                'publicacion pausada',
                'publicacion finalizada',
                'esta publicacion ya no esta disponible',
                'esta publicacion no esta disponible',
                'este vehiculo ya fue vendido',
                'anuncio no encontrado',
                'pagina no encontrada',
                'no pudimos encontrar esta pagina',
                'this listing isn\'t available',
                'this content isn\'t available',
            ];

            foreach ($deadSignals as $signal) {
                if (str_contains($body, $signal)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<int, int>
     */
    private function extractYears(string $text): array
    {
        $currentYear = (int) date('Y');
        preg_match_all('/\b(199\d|20[0-3]\d)\b/', $text, $matches);
        $years = [];

        foreach ($matches[1] ?? [] as $raw) {
            $year = (int) $raw;

            if ($year >= 1995 && $year <= $currentYear + 1) {
                $years[] = $year;
            }
        }

        return array_values(array_unique($years));
    }

    /**
     * @return array<int, string>
     */
    private function extractQueryTokens(string $query): array
    {
        $ascii = Str::lower(Str::ascii($query));
        $cleaned = preg_replace('/\$\s*\d[\d.,]*|\b\d{5,}\b/', ' ', $ascii) ?? $ascii;
        $parts = preg_split('/[^a-z0-9]+/', $cleaned) ?: [];
        $currentYear = (int) date('Y');
        $tokens = [];

        foreach ($parts as $part) {
            if ($part === '' || in_array($part, self::QUERY_STOPWORDS, true)) {
                continue;
            }

            if (is_numeric($part)) {
                $num = (int) $part;

                if ($num >= 1995 && $num <= $currentYear + 1) {
                    continue;
                }
            }

            if (strlen($part) >= 2 || is_numeric($part)) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function extractPriceFromText(string $text): ?int
    {
        if (! preg_match_all('/\$\s*(\d{1,3}(?:,\d{3})+(?:\.\d{2})?|\d{5,8})/', $text, $matches)) {
            return null;
        }

        foreach ($matches[1] as $raw) {
            $clean = preg_replace('/\.\d{2}$/', '', str_replace(',', '', $raw));
            $value = (int) $clean;

            if ($value >= 25_000 && $value <= 20_000_000) {
                return $value;
            }
        }

        return null;
    }

    private function extractKmFromText(string $text): ?int
    {
        if (preg_match('/(\d[\d.,]*)\s*kms?\.?\b/i', $text, $match)) {
            $km = (int) str_replace([',', '.'], '', $match[1]);

            if ($km >= 0 && $km <= 999_999) {
                return $km;
            }
        }

        return null;
    }

    private function extractLocationFromText(string $text): ?string
    {
        $lines = preg_split('/\R+/', $text) ?: [];

        foreach ($lines as $rawLine) {
            $line = trim(str_replace(['**', '__', '\\'], '', $rawLine));

            if ($line === '' || mb_strlen($line) > 60) {
                continue;
            }

            if (preg_match('/^[A-ZÁÉÍÓÚÑ][a-zA-ZáéíóúñÁÉÍÓÚÑ\s]{2,28}\s-\s[A-ZÁÉÍÓÚÑ][a-zA-ZáéíóúñÁÉÍÓÚÑ\s]{2,28}$/u', $line) === 1) {
                return $line;
            }

            if (preg_match('/^(Ciudad de M[eé]xico|CDMX|Monterrey|Guadalajara|Puebla|Quer[eé]taro|Tijuana|Le[oó]n|M[eé]rida|Canc[uú]n|Toluca|Chihuahua|Aguascalientes|Saltillo|San Luis Potos[ií]|Hermosillo|Veracruz|Morelia|Culiac[aá]n|Estado de M[eé]xico|Tlalpan|Corregidora|Iztacalco|Cuauht[eé]moc|Zapopan|Naucalpan)$/iu', $line) === 1) {
                return $line;
            }
        }

        return null;
    }
}
