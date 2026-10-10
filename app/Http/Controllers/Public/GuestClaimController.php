<?php

namespace App\Http\Controllers\Public;

use App\Actions\Claims\SubmitGuestClaim;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreGuestClaimRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * La reclamation d'un invite (decision du 2026-10-09). Meme absence d'authentification et meme
 * cloisonnement par sous-domaine que le reste du parcours invite.
 */
class GuestClaimController extends Controller
{
    /**
     * Store the claim of the guest whose signed link this is.
     *
     * Le jeton et l'inscription se lisent par leur nom sur la route : les arguments d'une route a
     * domaine se lient par position (CLAUDE.md, « Lien public de l'evenement »). La signature est la
     * meme que celle du lien de retour, comparee en temps constant ; un dossier d'un autre
     * evenement, une signature alteree ou absente recoivent le meme 404.
     */
    public function store(StoreGuestClaimRequest $request): RedirectResponse
    {
        $tenant = Tenant::current();
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', (string) $request->route('token'))->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        // Sur une route a domaine, la liaison implicite peut rendre l'identifiant brut plutot que le
        // modele (voir CLAUDE.md, « Lien public de l'evenement ») : on resout nous-memes, dans la base de
        // l'organisation deja initialisee.
        $parameter = $request->route('registration');
        $registration = $parameter instanceof Registration ? $parameter : Registration::query()->find($parameter);

        abort_unless($registration instanceof Registration && $registration->event_id === $event->id, 404);
        abort_unless(
            hash_equals($registration->notificationToken(), (string) $request->validated('signature')),
            404,
        );

        $claim = app(SubmitGuestClaim::class)->handle(
            $registration,
            $request->category(),
            (string) $request->validated('message'),
        );

        Inertia::flash('toast', $claim !== null
            ? ['type' => 'success', 'message' => __('guest.claim.sent')]
            : ['type' => 'error', 'message' => __('guest.claim.too_many')]);

        return back();
    }
}
