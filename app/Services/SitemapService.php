<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Car;
use App\Models\CarBrand;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use XMLWriter;

class SitemapService
{
    private const STATIC_ROUTES = [
        'home',
        'comprar',
        'comparar',
        'vende',
        'credito',
        'garantia',
        'certificaciones',
        'nosotros',
        'contacto',
        'terminos',
        'privacidad',
    ];

    /**
     * @return array{pages:int, cars:int, files:int}
     */
    public function generate(): array
    {
        $disk = Storage::disk($this->diskName());

        try {
            $lock = Cache::lock('seo:generate-sitemap', 600);

            if (! $lock->get()) {
                throw new RuntimeException('Ya hay una generación de sitemap en curso.');
            }
        } catch (LockTimeoutException $exception) {
            throw new RuntimeException('No se pudo obtener el lock del sitemap.', 0, $exception);
        }

        $previousRoot = URL::to('/');
        $previousScheme = parse_url($previousRoot, PHP_URL_SCHEME) ?: 'https';

        try {
            $disk->makeDirectory('.');
            URL::forceRootUrl($this->baseUrl());
            URL::forceScheme(parse_url($this->baseUrl(), PHP_URL_SCHEME) ?: 'https');

            $pagesCount = $this->writeUrlset('pages.xml', $this->pageUrls());
            [$carFiles, $carsCount] = $this->writeCars();
            $children = array_merge(['pages.xml'], $carFiles);
            $this->writeIndex($children);
            $this->cleanup(array_merge(['sitemap.xml'], $children));

            return [
                'pages' => $pagesCount,
                'cars' => $carsCount,
                'files' => count($children) + 1,
            ];
        } finally {
            URL::forceRootUrl($previousRoot);
            URL::forceScheme($previousScheme);
            $lock->release();
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function pageUrls(): \Generator
    {
        foreach (self::STATIC_ROUTES as $route) {
            yield [
                'loc' => route($route),
                'changefreq' => 'weekly',
                'priority' => $route === 'home' ? '1.0' : '0.7',
            ];
        }

        foreach (CarBrand::query()->whereNotNull('slug')->orderBy('name')->cursor() as $brand) {
            yield [
                'loc' => route('comprar.brand', $brand->slug),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        foreach (Car::query()->seoIndexable()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city') as $city) {
            yield [
                'loc' => route('comprar.city', $city),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        foreach (Car::query()->seoIndexable()->whereNotNull('body')->distinct()->orderBy('body')->pluck('body') as $body) {
            yield [
                'loc' => route('comprar.body', $body),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        foreach (Agency::query()->where('is_active', true)->whereNotNull('slug')->orderBy('name')->cursor() as $agency) {
            yield [
                'loc' => route('agencia.show', $agency->slug),
                'lastmod' => $agency->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }
    }

    /**
     * @return array{0:array<int, string>, 1:int}
     */
    private function writeCars(): array
    {
        $perFile = max((int) config('seo.cars_per_file', 10000), 1);
        $buffer = [];
        $files = [];
        $total = 0;
        $index = 1;

        $flush = function () use (&$buffer, &$files, &$total, &$index): void {
            if ($buffer === []) {
                return;
            }

            $file = 'cars-'.$index.'.xml';
            $this->writeUrlset($file, $buffer);
            $files[] = $file;
            $total += count($buffer);
            $buffer = [];
            $index++;
        };

        Car::query()
            ->seoIndexable()
            ->with(['brand:id,name', 'model:id,name', 'version:id,name'])
            ->orderBy('id')
            ->chunkById(1000, function ($cars) use (&$buffer, $flush, $perFile): void {
                foreach ($cars as $car) {
                    $url = [
                        'loc' => route('autos.show', $car->slug),
                        'lastmod' => $car->updated_at?->toAtomString(),
                        'changefreq' => 'daily',
                        'priority' => '0.8',
                    ];

                    $image = $this->absoluteImage($car->image ?? data_get($car->gallery, 0));

                    if ($image) {
                        $url['images'] = [$image];
                    }

                    $buffer[] = $url;

                    if (count($buffer) >= $perFile) {
                        $flush();
                    }
                }
            });

        $flush();

        return [$files, $total];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $urls
     */
    private function writeUrlset(string $file, iterable $urls): int
    {
        $disk = Storage::disk($this->diskName());
        $temporary = '.tmp-'.Str::random(10);
        $path = $disk->path($temporary);

        $writer = new XMLWriter;
        $writer->openUri($path);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $writer->writeAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');

        $count = 0;

        foreach ($urls as $url) {
            $writer->startElement('url');
            $writer->writeElement('loc', (string) $url['loc']);

            if (! empty($url['lastmod'])) {
                $writer->writeElement('lastmod', (string) $url['lastmod']);
            }

            if (! empty($url['changefreq'])) {
                $writer->writeElement('changefreq', (string) $url['changefreq']);
            }

            if (! empty($url['priority'])) {
                $writer->writeElement('priority', (string) $url['priority']);
            }

            foreach ($url['images'] ?? [] as $image) {
                $writer->startElement('image:image');
                $writer->writeElement('image:loc', (string) $image);
                $writer->endElement();
            }

            $writer->endElement();
            $count++;
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->moveFile($temporary, $file);

        return $count;
    }

    /**
     * @param  array<int, string>  $children
     */
    private function writeIndex(array $children): void
    {
        $disk = Storage::disk($this->diskName());
        $temporary = '.tmp-'.Str::random(10);
        $path = $disk->path($temporary);

        $writer = new XMLWriter;
        $writer->openUri($path);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($children as $child) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $this->baseUrl().'/sitemaps/'.$child);

            $childPath = $disk->path($child);

            if ($disk->exists($child)) {
                $writer->writeElement('lastmod', date('c', (int) filemtime($childPath)));
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();

        $this->moveFile($temporary, 'sitemap.xml');
    }

    private function moveFile(string $temporary, string $file): void
    {
        $disk = Storage::disk($this->diskName());

        if (! rename($disk->path($temporary), $disk->path($file))) {
            $disk->delete($temporary);

            throw new RuntimeException('No se pudo mover el sitemap generado: '.$file);
        }
    }

    /**
     * @param  array<int, string>  $keep
     */
    private function cleanup(array $keep): void
    {
        $disk = Storage::disk($this->diskName());

        foreach ($disk->files() as $file) {
            $name = basename($file);

            if (! preg_match('/^(pages|cars-\d{1,5})\.xml$/', $name)) {
                continue;
            }

            if (! in_array($name, $keep, true)) {
                $disk->delete($file);
            }
        }
    }

    private function absoluteImage(?string $image): ?string
    {
        if (! filled($image)) {
            return null;
        }

        if (Str::startsWith($image, ['http://', 'https://'])) {
            $host = parse_url($image, PHP_URL_HOST);
            $path = parse_url($image, PHP_URL_PATH) ?: '/';

            $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;

            if ($host === 'localhost' || $host === '127.0.0.1' || ($isIp && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
                return $this->baseUrl().'/'.ltrim($path, '/');
            }

            return $image;
        }

        return $this->baseUrl().'/'.ltrim($image, '/');
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('seo.base_url', config('app.url')), '/');
    }

    public function diskName(): string
    {
        return (string) config('seo.sitemap_disk', 'sitemaps');
    }
}
