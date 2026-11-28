<?php

namespace App\Support;

use Illuminate\Http\Request;

class LoginRedirector
{
    public function sanitizeInternalPath(Request $request, ?string $target): ?string
    {
        if (! is_string($target)) {
            return null;
        }

        $target = trim($target);

        if ($target === '') {
            return null;
        }

        $parsed = parse_url($target);

        if (! is_array($parsed)) {
            return null;
        }

        $path = $parsed['path'] ?? null;
        $host = $parsed['host'] ?? null;
        $scheme = $parsed['scheme'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        if ($scheme !== null && ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        if ($host !== null && $host !== $request->getHost()) {
            return null;
        }

        $blockedPrefixes = ['/login', '/registro', '/register', '/logout', '/auth/'];

        foreach ($blockedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                return null;
            }
        }

        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';
        $fragment = isset($parsed['fragment']) && $parsed['fragment'] !== '' ? '#'.$parsed['fragment'] : '';

        return $path.$query.$fragment;
    }

    public function storeIntended(Request $request, ?string $redirect): ?string
    {
        $safe = $this->sanitizeInternalPath($request, $redirect);

        if ($safe !== null) {
            $request->session()->put('url.intended', $safe);
        }

        return $safe;
    }

    public function path(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $user = $request->user();
        $safeIntended = $this->sanitizeInternalPath($request, is_string($intended) ? $intended : null);
        $path = $safeIntended !== null ? (parse_url($safeIntended, PHP_URL_PATH) ?: null) : null;
        $isAdminPath = is_string($path) && ($path === '/admin' || str_starts_with($path, '/admin/'));

        if ($user?->hasAdminPrivileges()) {
            return $isAdminPath && $safeIntended !== null
                ? $safeIntended
                : route('admin.dashboard');
        }

        return $safeIntended !== null && ! $isAdminPath
            ? $safeIntended
            : route('panel.dashboard');
    }
}
