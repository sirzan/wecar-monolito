<?php

namespace App\Support;

use Illuminate\Http\Request;

class LoginRedirector
{
    public function path(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');
        $user = $request->user();

        if ($user?->hasAdminPrivileges()) {
            $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;

            return is_string($path) && str_starts_with($path, '/admin')
                ? (string) $intended
                : route('admin.dashboard');
        }

        return is_string($intended) && $intended !== ''
            ? $intended
            : route('panel.dashboard');
    }
}
