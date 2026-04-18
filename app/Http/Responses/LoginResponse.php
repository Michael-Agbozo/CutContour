<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var Request $request */
        $home = $request->user()?->is_admin
            ? route('admin.dashboard')
            : route('dashboard');

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($home);
    }
}
