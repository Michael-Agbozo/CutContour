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

        if (! $request->wantsJson()) {
            session()->flash('flux_toast', [
                'text'    => 'Successfully authenticated. Welcome back!',
                'variant' => 'success',
            ]);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($home);
    }
}
