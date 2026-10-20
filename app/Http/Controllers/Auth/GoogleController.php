<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::query()->firstOrNew(['email' => $googleUser->getEmail()]);

        if (! $user->exists) {
            $user->name = $googleUser->getName() ?: $googleUser->getNickname() ?: 'Usuario Wecar';
            $user->password = Hash::make(Str::random(32));
            $user->email_verified_at = now();
            $user->save();
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('panel.dashboard'));
    }
}
