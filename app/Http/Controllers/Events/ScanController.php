<?php

namespace App\Http\Controllers\Events;

use App\Actions\Scan\ScanTicket;
use App\Actions\Tickets\RotateTicketSigningKey;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ScanTicketRequest;
use App\Models\Event;
use App\Models\ScanEvent;
use App\Models\Tenant;
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
 * `verify()` rend la meme page que `index()`, sans redirection : un scan est une action repetee
 * en rafale, une navigation complete a chaque code lu romprait le flux de la camera. Le front
 * la declenche en rechargement partiel Inertia (`only`), ce qui reste une visite Inertia comme
 * une autre (CLAUDE.md, « Inertia ») : aucune API JSON parallele n'est ajoutee.
 */
class ScanController extends Controller
{
    /**
     * Display the scan screen for the given event.
     */
    public function index(Request $request, Tenant $tenant, Event $event): Response
    {
        Gate::authorize('viewAny', [ScanEvent::class, $tenant]);

        return Inertia::render('events/scan', $this->props($request, $tenant, $event));
    }

    /**
     * Verify a scanned code and record the attempt.
     */
    public function verify(ScanTicketRequest $request, Tenant $tenant, Event $event, ScanTicket $scan): Response
    {
        $outcome = $scan->handle($event, $request->validated('token'), $request->user(), $request->boolean('force'));

        return Inertia::render('events/scan', [
            ...$this->props($request, $tenant, $event),
            'result' => [
                'result' => $outcome['result']->value,
                'forced' => $outcome['forced'],
                'registration' => $outcome['registration'],
                'firstScannedAt' => $outcome['firstScannedAt']?->toISOString(),
                'firstScannedBy' => $outcome['firstScannedBy'],
            ],
        ]);
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
            'tenant' => ['slug' => $tenant->slug],
            // La cle publique (jamais la cle privee) permet de verifier un billet hors ligne, README
            // 2.8 : l'appareil de l'agent ne detient aucun secret de signature.
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'qrPublicKey' => $event->qr_public_key,
                'qrKeyVersion' => $event->qr_key_version,
                'ticketValidUntil' => $event->ticketValidUntil()?->getTimestamp(),
            ],
            // Relue par l'appareil a chaque retour du reseau (SECURITY.md C2) ; null tant
            // qu'aucun billet n'a ete emis, donc aucune cle generee.
            'revocationList' => $event->qr_public_key !== null ? TicketRevocationList::signedFor($event) : null,
            'canRotateKey' => Gate::allows('update', [$event, $tenant]),
            'tenantId' => $tenant->id,
            'permissions' => $request->user()->toTenantPermissions($tenant),
            'recent' => $canViewLog ? $this->recentScans($event) : [],
            'acceptedCount' => ScanEvent::where('event_id', $event->id)
                ->where('result', ScanResult::Accepted)
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentScans(Event $event): array
    {
        return ScanEvent::where('event_id', $event->id)
            ->with('ticket.registration')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ScanEvent $scan) => [
                'id' => $scan->id,
                'result' => $scan->result->value,
                'forced' => $scan->forced,
                'name' => $scan->ticket?->registration?->name,
                'scannedAt' => $scan->created_at?->toISOString(),
            ])
            ->all();
    }
}
