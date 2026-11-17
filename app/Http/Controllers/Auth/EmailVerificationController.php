<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmailVerificationController extends Controller
{
    public function update(Request $request, UpdateUserProfileInformation $profileInformation): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'current_password' => ['required', 'current_password:web'],
        ]);

        $email = Str::lower(trim($validated['email']));

        if ($email === Str::lower($request->user()->email)) {
            return back()->withErrors([
                'email' => 'Ingresa un correo diferente o utiliza el botón para reenviar la verificación.',
            ]);
        }

        $profileInformation->update($request->user(), [
            'name' => $request->user()->name,
            'email' => $email,
        ]);

        return back()->with('success', 'Correo actualizado. Revisa tu nueva bandeja de entrada para verificarlo.');
    }
}
