<?php

namespace App\Http\Responses;

use App\Support\LoginRedirector;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(private readonly LoginRedirector $redirector) {}

    public function toResponse($request)
    {
        $target = $this->redirector->path($request);

        return $request->wantsJson()
            ? response()->json(['redirect' => $target])
            : redirect()->to($target);
    }
}
