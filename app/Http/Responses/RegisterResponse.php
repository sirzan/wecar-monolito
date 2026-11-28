<?php

namespace App\Http\Responses;

use App\Support\LoginRedirector;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private readonly LoginRedirector $redirector) {}

    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json([], 201)
            : redirect()->to($this->redirector->path($request));
    }
}
