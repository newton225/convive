<?php

namespace App\Http\Controllers\Events;

use App\Actions\Scan\ScanTicket;
use App\Actions\Tickets\RotateTicketSigningKey;
use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ScanTicketRequest;
use App\Models\Event;
use App\Models\ScanEvent;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketRevocationList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le controle a l'entree (README ecran 26), etape 7 de « Ordre de construction » : viseur,
 * compteur, trois resultats, derniers passages.
 *
 * `verify()` suit le schema POST puis redirection d'Inertia : le verdict passe par la session,
 * et l'adresse revient a celle de l'ecran de scan. Rendre la page directement sur l'adresse du
 * POST la laissait dans l'historique, et y revenir envoyait un GET refuse (« Method Not
 * Allowed »). Le front garde son rechargement partiel (`only`, `preserveState`), que la
 * redirection conserve : la camera n'est pas interrompue entre deux billets.
 */
class ScanController extends Controller
{
    /**
     * Cle de session du verdict du dernier scan, lu une seule fois par l'ecran de scan.
     */
    private const ResultKey = 'scan.result';

    /**
     * Display the scan screen for the given event.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [ScanEvent::class, $tenant]);

        return Inertia::render('events/scan', $this->props($request, $tenant, $event));
    }

    /**
     * Send a visit to the verification address back to the scan screen, which authorises it.
     */
    public function backToScan(Tenant $tenant, Event $event): RedirectResponse
    {
        return to_route('tenants.events.scan.index', [$tenant, $event]);
    }

    /**
     * Verify a scanned code and record the attempt.
     */
    public function verify(ScanTicketRequest $request, Tenant $tenant, Event $event, ScanTicket $scan): RedirectResponse
    {
        $outcome = $scan->handle(
            $event,
            $request->validated('token'),
            $request->user(),
            $request->boolean('force'),
            $request->validated('station'),
        );

        $request->session()->flash(self::ResultKey, [
            'result' => $outcome['result']->value,
            'forced' => $outcome['forced'],
            'registration' => $outcome['registration'],
            'firstScannedAt' => $outcome['firstScannedAt']?->toISOString(),
            'firstScannedBy' => $outcome['firstScannedBy'],
            'otherEvent' => $outcome['otherEvent'],
        ]);

        return to_route('tenants.events.scan.index', [$tenant, $event]);
    }

    /**
     * Replace the key that signs this event's tickets (SECURITY.md C2).
     */
    public function rotateKey(Tenant $tenant, Event $event, RotateTicketSigningKey $rotate): RedirectResponse
    {
        Gate::authorize('update', [$event, $tenant]);

        $rotate->handle($event);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('scan.rotate_key.flash')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function props(Request $request, Tenant $tenant, Event $event): array
    {
        $canViewLog = Gate::allows('viewLog', [ScanEvent::class, $tenant]);

        return [
            // Verdict du scan qui vient d'avoir lieu, pose par `verify()` avant sa redirection.
            'result' => $request->session()->get(self::ResultKey),
            'tenant' => ['slug' => $tenant->slug],
            // La cle publique (jamais la cle privee) permet de verifier un billet hors ligne, README
            // 2.8 : l'appareil de l'agent ne detient aucun secret de signature.
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                // Deux evenements le meme jour portent souvent le meme nom : le lieu et l'heure
                // disent a l'agent sur quel controle il se trouve (README ecran 26).
                'venue' => $event->venue,
                'startsAt' => $event->starts_at?->toISOString(),
                'qrPublicKey' => $event->qr_public_key,
                'qrKeyVersion' => $event->qr_key_version,
                // L'appareil efface sa file locale d'un evenement clos (SECURITY.md M8).
                'closed' => $event->status === EventStatus::Closed,
                'ticketValidUntil' => $event->ticketValidUntil()?->getTimestamp(),
            ],
            // Relue par l'appareil a chaque retour du reseau (SECURITY.md C2) ; null tant
            // qu'aucun billet n'a ete emis, donc aucune cle generee.
            'revocationList' => $event->qr_public_key !== null ? TicketRevocationList::signedFor($event) : null,
            'canRotateKey' => Gate::allows('update', [$event, $tenant]),
            // Empreinte du code de scan de l'agent connecte (jamais le code) : le verrouillage
            // de l'ecran se leve sur l'appareil, meme hors ligne (SECURITY.md M8).
            'scanPin' => $request->user()->scan_pin_verifier,
            'tenantId' => $tenant->id,
            'permissions' => $request->user()->toTenantPermissions($tenant),
            // Les autres controles du jour : l'ecran propose d'en changer, et le dit, quand l'agent
            // pourrait s'etre trompe de lieu.
            'otherEventsToday' => Event::query()->checkInToday()
                ->whereKeyNot($event->id)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Event $other) => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'venue' => $other->venue,
                    'startsAt' => $other->starts_at?->toISOString(),
                ])
                ->all(),
            'recent' => $canViewLog ? $this->recentScans($event) : [],
            'acceptedCount' => ScanEvent::where('event_id', $event->id)
                ->where('result', ScanResult::Accepted)
                ->count(),
            // Les personnes attendues a l'entree : un billet par personne (README 2.8), ceux des
            // inscriptions confirmees, les seuls qui peuvent ouvrir la porte.
            'expectedCount' => Ticket::whereHas('registration', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('status', RegistrationStatus::Confirmed))
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentScans(Event $event): array
    {
        return ScanEvent::where('event_id', $event->id)
            ->with('ticket.registration', 'ticket.holderUnit')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ScanEvent $scan) => [
                'id' => $scan->id,
                'result' => $scan->result->value,
                'forced' => $scan->forced,
                'station' => $scan->station,
                'name' => $scan->ticket?->holderName(),
                'scannedAt' => $scan->created_at?->toISOString(),
            ])
            ->all();
    }
}
