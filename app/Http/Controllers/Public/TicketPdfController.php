<?php

namespace App\Http\Controllers\Public;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TicketQrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Les billets en PDF (README 2.8) : a garder hors ligne et a presenter a l'entree quand la
 * connexion de l'invite n'est pas fiable. Le QR imprime est le meme jeton signe que celui de la
 * page : le scan le verifie de la meme facon, et un billet deja scanne reste signale.
 *
 * Parametres lus sur la route plutot qu'injectes : sur ce groupe a domaine, l'injection se fait
 * par position et donnerait le sous-domaine a la place du jeton (CLAUDE.md, « Lien public de
 * l'evenement »). Signature, evenement et statut repondent tous le meme 404 qu'un jeton inconnu.
 */
class TicketPdfController extends Controller
{
    /**
     * One ticket alone, the one a companion received through its individual link.
     */
    public function single(Request $request): PdfBuilder
    {
        $event = $this->publishedEvent($request);

        $ticket = Ticket::with('registration.tableAssignment.seatingTable', 'registration.unit', 'registration.companions.unit', 'holderUnit')
            ->find((int) $request->route('ticket'));

        abort_if(
            ! $ticket
                || $ticket->registration->event_id !== $event->id
                || $ticket->registration->status !== RegistrationStatus::Confirmed
                || ! hash_equals($ticket->shareSignature(), (string) $request->query('signature')),
            404,
        );

        return $this->pdf($event, collect([$ticket]), "billet-{$ticket->id}.pdf");
    }

    /**
     * Every ticket of the group, the main guest's first, one per page.
     */
    public function group(Request $request): PdfBuilder
    {
        $event = $this->publishedEvent($request);

        $registration = Registration::with('tableAssignment.seatingTable', 'unit', 'companions.unit')
            ->find((int) $request->route('registration'));

        abort_if(
            ! $registration
                || $registration->event_id !== $event->id
                || $registration->status !== RegistrationStatus::Confirmed
                || ! hash_equals($registration->notificationToken(), (string) $request->query('signature')),
            404,
        );

        $tickets = Ticket::where('registration_id', $registration->id)
            ->with('holderUnit')
            ->orderBy('holder_position')
            ->get()
            ->each(fn (Ticket $ticket) => $ticket->setRelation('registration', $registration));

        abort_if($tickets->isEmpty(), 404);

        return $this->pdf($event, $tickets, 'billets-'.($registration->reference ?? $registration->id).'.pdf');
    }

    private function publishedEvent(Request $request): Event
    {
        abort_if(! Tenant::current(), 404);

        $event = Event::where('public_token', (string) $request->route('token'))->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        return $event;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     */
    private function pdf(Event $event, Collection $tickets, string $filename): PdfBuilder
    {
        $tenant = Tenant::current();
        $branding = $tenant?->brandingOrCreate();

        return Pdf::view('pdf.tickets', [
            'event' => [
                'name' => $event->name,
                'startsAt' => $event->starts_at,
                'venue' => $event->venue,
            ],
            'organisation' => $branding->display_name ?? $tenant?->name,
            // Couleurs de marque : le billet fait partie du parcours invite (CLAUDE.md, « Design »).
            'colors' => $event->colors(),
            'tickets' => $tickets->map(fn (Ticket $ticket) => [
                'name' => $ticket->holderName(),
                'unit' => $ticket->holderUnitName(),
                // L'invite principal voit qui l'accompagne, si le gabarit le prevoit (README ecran
                // 15) ; un accompagnateur voit qui l'invite, toujours : c'est ce qui le rattache a
                // son groupe a l'entree.
                'companions' => $branding?->ticket_element_companions ? $ticket->companionsOfHolder() : [],
                'host' => $ticket->host(),
                'qrImage' => TicketQrCode::dataUri($ticket->signedToken()),
                'tableNumber' => $ticket->registration->tableAssignment?->seatingTable->number,
            ])->values()->all(),
        ])
            ->format('a5')
            ->margins(10, 10, 10, 10)
            ->download($filename);
    }
}
