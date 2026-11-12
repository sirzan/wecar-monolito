<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LoginRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function __construct(private readonly LoginRedirector $redirector) {}

    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')->with('error', 'El inicio de sesión con Google no está configurado.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')->with('error', 'El inicio de sesión con Google no está configurado.');
        }

        $googleUser = Socialite::driver('google')->user();

        $user = User::query()->firstOrNew(['email' => $googleUser->getEmail()]);

        abort_if($user->exists && ! $user->is_active, 403, 'Esta cuenta está desactivada.');

        if (! $user->exists) {
            $user->name = $googleUser->getName() ?: $googleUser->getNickname() ?: 'Usuario Wecar';
            $user->password = Hash::make(Str::random(32));
            $user->email_verified_at = now();
            $user->save();
        }

        Auth::login($user, remember: true);

        return redirect()->to($this->redirector->path(request()));
    }

    private function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
