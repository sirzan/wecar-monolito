<?php

namespace App\Http\Responses;

use App\Support\LoginRedirector;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function __construct(private readonly LoginRedirector $redirector) {}

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->noContent()
            : redirect()->to($this->redirector->path($request));
    }
}
