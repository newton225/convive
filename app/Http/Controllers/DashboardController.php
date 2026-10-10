<?php

namespace App\Http\Controllers;

use App\Enums\TenantPermission;
use App\Models\PaymentAccount;
use App\Models\TenantInvitation;
use App\Support\DashboardOverview;
use App\Support\GettingStarted;
use App\Support\ProofQueueEvents;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TenantInvitation::query()
            ->with(['inviter', 'tenant'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TenantInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'tenant' => [
                    'name' => $invitation->tenant->name,
                    'slug' => $invitation->tenant->slug,
                ],
            ]);

        // L'evenement choisi dans le selecteur, sinon celui que retient la regle automatique.
        $chosenId = $request->integer('event') ?: null;
        $event = DashboardOverview::chosenEvent($chosenId);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'paymentAccountNotice' => $this->paymentAccountNotice($request),
            'overview' => $event ? DashboardOverview::for($event) : null,
            // Le bouton des preuves annonce le total de tous les evenements, pas celui du seul evenement affiche.
            'proofsToCheck' => ProofQueueEvents::summary($event?->id),
            'eventChoices' => $event ? DashboardOverview::choices() : [],
            // Nul quand le choix est automatique, ou qu'un evenement inconnu a ete demande.
            'selectedEventId' => $chosenId !== null && $event?->id === $chosenId ? $chosenId : null,
            'gettingStarted' => $request->user()->currentTenant
                ? GettingStarted::for($request->user()->currentTenant, $request->user())
                : null,
        ]);
    }

    /**
     * Signal a recent payment account change on the dashboard.
     *
     * Le delai d'activation et l'alerte par email ne servent que si quelqu'un regarde. Le
     * bandeau est la pour que le changement se voie meme si l'email est passe inapercu
     * (SECURITY.md C1).
     *
     * @return array{changed: bool, days: int}|null
     */
    private function paymentAccountNotice(Request $request): ?array
    {
        $tenant = $request->user()->currentTenant;

        if (! $tenant || ! $request->user()->hasTenantPermission($tenant, TenantPermission::TenantPaymentAccounts)) {
            return null;
        }

        $changed = PaymentAccount::query()
            ->where('last_changed_at', '>', now()->subDays(PaymentAccount::ChangeNoticeDays))
            ->exists();

        return $changed
            ? ['changed' => true, 'days' => PaymentAccount::ChangeNoticeDays]
            : null;
    }
}
