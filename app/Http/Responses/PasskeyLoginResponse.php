<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTenant;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use RedirectsToCurrentTenant;

    public function toResponse($request): Response
    {
        Inertia::clearHistory();

        $redirect = $this->redirectPathForCurrentTenant($request, Fortify::redirects('login'));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => redirect()->intended($redirect)->getTargetUrl()], 200)
            : redirect()->intended($redirect);
    }
}
