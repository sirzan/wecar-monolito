<?php

namespace App\Http\Responses;

use App\Support\LoginRedirector;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(private readonly LoginRedirector $redirector) {}

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->to($this->redirector->path($request));
    }
}
