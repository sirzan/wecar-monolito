<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemaps): Response
    {
        return $this->serve($sitemaps, 'sitemap.xml');
    }

    public function show(SitemapService $sitemaps, string $file): Response
    {
        abort_unless(preg_match('/^(pages|cars-\d{1,5})\.xml$/', $file), 404);

        return $this->serve($sitemaps, $file);
    }

    private function serve(SitemapService $sitemaps, string $file): Response
    {
        $disk = Storage::disk($sitemaps->diskName());

        if (! $disk->exists($file)) {
            try {
                $sitemaps->generate();
            } catch (Throwable $exception) {
                report($exception);

                if (! $disk->exists($file)) {
                    abort(503, 'El sitemap se está generando. Intenta de nuevo en unos minutos.');
                }
            }
        }

        abort_unless($disk->exists($file), 404);

        $path = $disk->path($file);
        $content = $disk->get($file);
        $cacheSeconds = max((int) config('seo.cache_seconds', 3600), 0);

        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age='.$cacheSeconds,
            'Last-Modified' => gmdate('D, d M Y H:i:s', (int) filemtime($path)).' GMT',
        ]);
    }
}
