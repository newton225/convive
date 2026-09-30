<?php

namespace App\Http\Controllers\Public;

use App\Enums\BrandFile;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketQrCode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le lien individuel d'un billet (README 2.8, ecran 7) : ce que l'invite transmet a un
 * accompagnateur qui arrivera sans lui. Il montre ce seul billet, jamais le dossier ni les autres
 * billets du groupe.
 */
class TicketController extends Controller
{
    /**
     * Display a single ticket reached through its signed link.
     *
     * Parametres lus sur la route plutot qu'injectes : sur ce groupe a domaine, l'injection se fait
     * par position et donnerait le sous-domaine a la place du jeton (CLAUDE.md, « Lien public de
     * l'evenement »). Signature, evenement et statut de l'inscription repondent tous le meme 404
     * qu'un jeton inconnu : rien ne doit permettre de deviner ce qui existe.
     */
    public function show(Request $request): Response
    {
        $tenant = Tenant::current();
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', (string) $request->route('token'))->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        $ticket = Ticket::with('registration.unit', 'registration.tableAssignment.seatingTable', 'holderUnit')
            ->find((int) $request->route('ticket'));

        abort_if(
            ! $ticket
                || $ticket->registration->event_id !== $event->id
                || $ticket->registration->status !== RegistrationStatus::Confirmed
                || ! hash_equals($ticket->shareSignature(), (string) $request->query('signature')),
            404,
        );

        $branding = $tenant->brandingOrCreate();

        return Inertia::render('public/ticket-show', [
            'event' => [
                'name' => $event->name,
                'startsAt' => $event->starts_at?->toISOString(),
            ],
            'tenant' => [
                'displayName' => $branding->display_name ?? $tenant->name,
                'colors' => $event->colors(),
                'logoUrl' => $branding->brandFileUrl(BrandFile::Logo),
            ],
            'ticket' => [
                'name' => $ticket->holderName(),
                'unit' => $ticket->holderUnitName(),
                'guestOf' => $ticket->isCompanion() ? $ticket->registration->name : null,
                'qrImage' => TicketQrCode::dataUri($ticket->signedToken()),
                'tableNumber' => $ticket->registration->tableAssignment?->seatingTable->number,
                'pdfUrl' => $ticket->pdfUrl(),
            ],
        ]);
    }
}
