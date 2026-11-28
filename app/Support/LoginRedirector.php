<?php

namespace App\Support;

use Illuminate\Http\Request;

class LoginRedirector
{
    public function path(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $user = $request->user();
        $parsedIntended = is_string($intended) ? parse_url($intended) : false;
        $path = is_array($parsedIntended) ? ($parsedIntended['path'] ?? null) : null;
        $host = is_array($parsedIntended) ? ($parsedIntended['host'] ?? null) : null;
        $isInternal = is_string($path)
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ($host === null || $host === $request->getHost());
        $isAdminPath = $isInternal && ($path === '/admin' || str_starts_with($path, '/admin/'));

        if ($user?->hasAdminPrivileges()) {
            return $isAdminPath
                ? (string) $intended
                : route('admin.dashboard');
        }

        return $isInternal && ! $isAdminPath
            ? $intended
            : route('panel.dashboard');
    }
}
