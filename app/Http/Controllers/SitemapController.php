<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Car;
use App\Models\CarBrand;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect();

        foreach (['home', 'comprar', 'comparar', 'vende', 'credito', 'garantia', 'certificaciones', 'nosotros', 'contacto', 'terminos', 'privacidad'] as $route) {
            $urls->push([
                'loc' => route($route),
                'changefreq' => 'weekly',
                'priority' => $route === 'home' ? '1.0' : '0.7',
            ]);
        }

        CarBrand::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['slug'])
            ->each(fn (CarBrand $brand) => $urls->push([
                'loc' => route('comprar.brand', $brand->slug),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ]));

        Car::query()
            ->published()
            ->whereNotNull('city')
            ->distinct()
            ->limit(100)
            ->pluck('city')
            ->each(fn (string $city) => $urls->push([
                'loc' => route('comprar.city', rawurlencode($city)),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]));

        Car::query()
            ->published()
            ->whereNotNull('body')
            ->distinct()
            ->limit(20)
            ->pluck('body')
            ->each(fn (string $body) => $urls->push([
                'loc' => route('comprar.body', rawurlencode($body)),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]));

        Car::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(5000)
            ->get()
            ->each(fn (Car $car) => $urls->push([
                'loc' => route('autos.show', $car->slug),
                'lastmod' => $car->updated_at?->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ]));

        Agency::query()
            ->where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->limit(2000)
            ->get()
            ->each(fn (Agency $agency) => $urls->push([
                'loc' => route('agencia.show', $agency->slug),
                'lastmod' => $agency->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]));

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
