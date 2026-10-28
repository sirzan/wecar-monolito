<?php

return [
    'base_url' => rtrim((string) env('SITEMAP_BASE_URL', env('APP_URL', 'https://wecar.mx')), '/'),

    'sitemap_disk' => (string) env('SITEMAP_DISK', 'sitemaps'),

    'cars_per_file' => max((int) env('SITEMAP_CARS_PER_FILE', 10000), 1),

    'cache_seconds' => max((int) env('SITEMAP_CACHE_SECONDS', 3600), 0),
];
