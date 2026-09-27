<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ScanEvent;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le raccourci « Controle a l'entree » du menu (README ecran 26) : le soir de l'evenement,
 * l'hotesse arrive sur le scan sans passer par la liste des evenements. Un seul evenement du jour
 * ouvre directement son scan, plusieurs proposent un choix.
 */
class EntryControlController extends Controller
{
    public function __invoke(Tenant $tenant): Response|RedirectResponse
    {
        Gate::authorize('viewAny', [ScanEvent::class, $tenant]);

        $events = Event::query()->checkInToday()->orderBy('starts_at')->get();

        if ($events->count() === 1) {
            return redirect()->route('tenants.events.scan.index', [$tenant, $events->first()]);
        }

        return Inertia::render('events/entry-control', [
            'tenant' => ['slug' => $tenant->slug],
            'events' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'startsAt' => $event->starts_at?->toISOString(),
                'venue' => $event->venue,
                'statusLabel' => $event->status->label(),
            ])->all(),
        ]);
    }
}
