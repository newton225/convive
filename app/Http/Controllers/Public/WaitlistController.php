<?php

namespace App\Http\Controllers\Public;

use App\Actions\Waitlist\FinalizeWaitlistEntry;
use App\Actions\Waitlist\JoinWaitlist;
use App\Actions\Waitlist\PromoteNextWaitlistEntry;
use App\Enums\BrandFile;
use App\Enums\WaitlistStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreWaitlistEntryRequest;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La liste d'attente (README 2.3, ecran 10) : rejoindre quand l'evenement est complet, suivre sa
 * position, finaliser des qu'invite. Etape 5 de « Ordre de construction », le dernier morceau
 * du noyau de reservation.
 */
class WaitlistController extends Controller
{
    /**
     * Join the waitlist of a full event.
     */
    public function store(StoreWaitlistEntryRequest $request, string $token): RedirectResponse
    {
        $event = $this->publishedEvent($token);

        if (! $event->isFull()) {
            return redirect($event->publicUrl());
        }

        $joined = app(JoinWaitlist::class)->handle($event, [
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'unit_id' => (int) $request->validated('unit_id'),
            'companions' => $request->companions(),
        ]);

        return to_route('public.waitlist.show', [
            'token' => $token,
            'resume' => $joined['resumeToken'],
        ]);
    }

    /**
     * Show the status of a waitlist entry : position while waiting, invite once a seat frees
     * up, or the outcome once the window has closed or it has been finalized.
     */
    public function show(string $token, string $resume): Response
    {
        $event = $this->publishedEvent($token);
        $entry = $this->entryForResumeToken($event, $resume);

        // Relance paresseuse, comme `RegistrationController::show()` : le delai peut avoir
        // expire depuis la derniere lecture, sans que la tache planifiee ne soit encore passee.
        if ($entry->inviteHasExpired()) {
            $entry->update(['status' => WaitlistStatus::Expired]);
            app(PromoteNextWaitlistEntry::class)->handle($event);
        }

        return Inertia::render('public/waitlist-show', [
            'token' => $token,
            'resume' => $resume,
            'event' => ['name' => $event->name],
            'entry' => [
                'name' => $entry->name,
                'status' => $entry->status->value,
                'position' => $entry->position(),
                'expiresAt' => $entry->expires_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Finalize an invited entry : create and hold the registration it describes.
     */
    public function finalize(string $token, string $resume): RedirectResponse
    {
        $event = $this->publishedEvent($token);
        $entry = $this->entryForResumeToken($event, $resume);

        if ($entry->inviteHasExpired()) {
            $entry->update(['status' => WaitlistStatus::Expired]);
            app(PromoteNextWaitlistEntry::class)->handle($event);
        }

        abort_unless($entry->status === WaitlistStatus::Invited, 404);

        $result = app(FinalizeWaitlistEntry::class)->handle($entry);

        if ($result === null) {
            return redirect($event->publicUrl());
        }

        return to_route('public.registrations.show', [
            'token' => $token,
            'resume' => $result['resumeToken'],
        ]);
    }

    /**
     * Show the form to join the waitlist (README ecran 10).
     */
    public function create(string $token): Response|RedirectResponse
    {
        $event = $this->publishedEvent($token);

        if (! $event->isFull()) {
            return redirect($event->publicUrl());
        }

        $tenant = Tenant::current();

        return Inertia::render('public/waitlist-join', [
            'token' => $token,
            'event' => [
                'name' => $event->name,
                'companionLimit' => $event->companion_limit,
            ],
            'tenant' => [
                'displayName' => $tenant->branding->display_name ?? $tenant->name,
                'colors' => $tenant->brandingOrCreate()->colors(),
                'logoUrl' => $tenant->branding?->brandFileUrl(BrandFile::Logo),
            ],
            'units' => Unit::active()->ordered()->get()->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
            ]),
        ]);
    }

    /**
     * Resolve the entry a resume token points to, scoped to this event.
     */
    private function entryForResumeToken(Event $event, string $resume): WaitlistEntry
    {
        $entry = WaitlistEntry::where('event_id', $event->id)
            ->where('resume_token_hash', WaitlistEntry::hashResumeToken($resume))
            ->first();

        abort_if(! $entry, 404);

        return $entry;
    }

    /**
     * Resolve the published event of a public link, or fail with the same 404 as an unknown
     * token (see `Public\EventController::show`).
     */
    private function publishedEvent(string $token): Event
    {
        $tenant = Tenant::current();
        abort_if(! $tenant, 404);

        $event = Event::where('public_token', $token)->first();
        abort_if(! $event || ! $event->isPublished(), 404);

        return $event;
    }
}
