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
                'is_agency' => (bool) $profile?->is_agency,
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
                'email' => $request->user()->email,
                'phone' => $profile?->phone ?? $request->user()->phone,
                'birthdate' => $profile?->birthdate?->toDateString(),
                'rfc' => $profile?->rfc,
                'curp' => $profile?->curp,
                'gender' => $profile?->genero,
                'calle' => $profile?->calle,
                'colonia' => $profile?->colonia,
                'cp' => $profile?->cp,
                'alcaldia' => $profile?->alcaldia,
                'city' => $profile?->city,
                'state' => $profile?->state,
                'country' => $profile?->pais ?? 'México',
                'ocupacion' => $profile?->ocupacion ?? data_get($profile?->metadata, 'ocupacion'),
                'empresa' => $profile?->empresa ?? data_get($profile?->metadata, 'empresa'),
                'antiguedad' => $profile?->antiguedad ?? data_get($profile?->metadata, 'antiguedad'),
                'tipo_trabajo' => $profile?->tipo_trabajo ?? data_get($profile?->metadata, 'tipo_trabajo'),
                'ingreso_mensual' => $profile?->ingreso_mensual ?? data_get($profile?->metadata, 'ingreso_mensual'),
                'deuda_mensual' => $profile?->deuda_mensual ?? data_get($profile?->metadata, 'deuda_mensual'),
                'banco' => $profile?->banco ?? data_get($profile?->metadata, 'banco'),
                'tipo_cuenta' => $profile?->tipo_cuenta ?? data_get($profile?->metadata, 'tipo_cuenta'),
                'enganche_disponible' => $profile?->enganche_disponible ?? data_get($profile?->metadata, 'enganche_disponible'),
            ],
        ]);
    }

    public function updateCredit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'curp' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'max:30'],
            'calle' => ['nullable', 'string', 'max:160'],
            'colonia' => ['nullable', 'string', 'max:120'],
            'cp' => ['nullable', 'string', 'max:10'],
            'alcaldia' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
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
            'full_name' => $validated['full_name'],
            'phone' => $validated['phone'] ?? $profile->phone,
            'birthdate' => $validated['birthdate'] ?? null,
            'rfc' => $validated['rfc'] ?? null,
            'curp' => $validated['curp'] ?? null,
            'genero' => $validated['gender'] ?? null,
            'calle' => $validated['calle'] ?? null,
            'colonia' => $validated['colonia'] ?? null,
            'cp' => $validated['cp'] ?? null,
            'alcaldia' => $validated['alcaldia'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'pais' => $validated['country'] ?? 'México',
            'ocupacion' => $validated['ocupacion'] ?? null,
            'empresa' => $validated['empresa'] ?? null,
            'antiguedad' => $validated['antiguedad'] ?? null,
            'tipo_trabajo' => $validated['tipo_trabajo'] ?? null,
            'ingreso_mensual' => $validated['ingreso_mensual'] ?? null,
            'deuda_mensual' => $validated['deuda_mensual'] ?? null,
            'banco' => $validated['banco'] ?? null,
            'tipo_cuenta' => $validated['tipo_cuenta'] ?? null,
            'enganche_disponible' => $validated['enganche_disponible'] ?? null,
        ]);

        return back()->with('success', 'Perfil crediticio actualizado.');
    }

    public function updateAccountType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:personal,agency'],
        ]);

        $user = $request->user();
        $profile = $user->profile ?? Profile::query()->create([
            'user_id' => $user->getKey(),
            'full_name' => $user->name,
        ]);

        if ($validated['account_type'] === 'agency') {
            $profile->update(['is_agency' => true]);
            $user->assignRole('agency_owner');

            if (! $user->agenciesOwned()->exists()) {
                return redirect()
                    ->route('panel.agency.create')
                    ->with('success', 'Completa los datos de tu agencia para activar el perfil de agencia.');
            }

            return back()->with('success', 'Perfil de agencia activado.');
        }

        if ($user->agenciesOwned()->exists()) {
            return back()->with('error', 'No puedes cambiar a perfil personal mientras tengas una agencia activa.');
        }

        $profile->update(['is_agency' => false]);

        if ($user->hasRole('agency_owner')) {
            $user->removeRole('agency_owner');
        }

        if (! $user->hasRole('seller')) {
            $user->assignRole('seller');
        }

        return back()->with('success', 'Perfil personal activado.');
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
