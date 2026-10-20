<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->profile;

        return Inertia::render('Panel/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $profile?->phone ?? $user->phone,
                'city' => $profile?->city,
                'state' => $profile?->state,
                'birthdate' => $profile?->birthdate?->toDateString(),
                'rfc' => $profile?->rfc,
                'curp' => $profile?->curp,
                'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'curp' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
        ]);

        Profile::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'full_name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'birthdate' => $validated['birthdate'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'curp' => $validated['curp'] ?? null,
            ],
        );

        return back()->with('success', 'Datos actualizados.');
    }

    public function credit(Request $request): Response
    {
        $profile = $request->user()->profile;

        return Inertia::render('Panel/CreditProfile', [
            'profile' => [
                'full_name' => $profile?->full_name ?? $request->user()->name,
                'phone' => $profile?->phone ?? $request->user()->phone,
                'birthdate' => $profile?->birthdate?->toDateString(),
                'rfc' => $profile?->rfc,
                'curp' => $profile?->curp,
                'metadata' => $profile?->metadata ?? [],
            ],
        ]);
    }

    public function updateCredit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ocupacion' => ['nullable', 'string', 'max:120'],
            'empresa' => ['nullable', 'string', 'max:160'],
            'antiguedad' => ['nullable', 'string', 'max:80'],
            'tipo_trabajo' => ['nullable', 'string', 'max:80'],
            'ingreso_mensual' => ['nullable', 'numeric', 'min:0'],
            'deuda_mensual' => ['nullable', 'numeric', 'min:0'],
            'banco' => ['nullable', 'string', 'max:80'],
            'tipo_cuenta' => ['nullable', 'string', 'max:80'],
            'enganche_disponible' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $profile = $user->profile ?? Profile::query()->create([
            'user_id' => $user->getKey(),
            'full_name' => $user->name,
        ]);

        $profile->update([
            'metadata' => array_merge($profile->metadata ?? [], $validated),
        ]);

        return back()->with('success', 'Perfil crediticio actualizado.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $url = Storage::disk('public')->url($path);

        $user = $request->user();
        $user->update(['avatar_url' => $url]);

        Profile::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['avatar_url' => $url, 'full_name' => $user->name],
        );

        return back()->with('success', 'Foto de perfil actualizada.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['avatar_url' => null]);

        $user->profile?->update(['avatar_url' => null]);

        return back()->with('success', 'Foto de perfil eliminada.');
    }
}
