<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\WithdrawShowcaseAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\WithdrawAnnouncementRequest;
use App\Models\ConsoleActionLog;
use App\Models\ShowcaseEvent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La moderation de la vitrine (README ecran 32) : les annonces en ligne, lues dans la table
 * centrale de la vitrine, et les retraits deja faits, relus au journal central. La zone `showcase`
 * des routes la reserve aux Fondateurs.
 */
class ShowcaseController extends Controller
{
    /**
     * Nombre de retraits relus a l'ecran.
     */
    private const WithdrawnShown = 50;

    /**
     * Display the announcements in the showcase and the ones already withdrawn.
     */
    public function index(): Response
    {
        return Inertia::render('console/showcase', [
            'isSample' => false,
            'announcements' => ShowcaseEvent::query()
                ->latest('announced_at')
                ->get()
                ->map(fn (ShowcaseEvent $announcement) => [
                    'id' => $announcement->id,
                    'eventName' => $announcement->name,
                    'organisationName' => $announcement->organisation_name,
                    'announcedAt' => $announcement->announced_at->toISOString(),
                    'startsAt' => $announcement->starts_at?->toISOString(),
                    'publicUrl' => $announcement->public_url,
                ])
                ->all(),
            'withdrawn' => ConsoleActionLog::query()
                ->where('type', 'announcement_withdrawn')
                ->latest('created_at')
                ->limit(self::WithdrawnShown)
                ->get()
                ->map(fn (ConsoleActionLog $entry) => [
                    'id' => $entry->id,
                    'eventName' => $entry->properties['event_name'] ?? '',
                    'organisationName' => $entry->organisation,
                    'withdrawnAt' => $entry->created_at->toISOString(),
                    'actor' => $entry->actor_name,
                    'reason' => $entry->properties['reason'] ?? '',
                ])
                ->all(),
        ]);
    }

    /**
     * Withdraw the given announcement from the showcase.
     */
    public function withdraw(WithdrawAnnouncementRequest $request, ShowcaseEvent $announcement, WithdrawShowcaseAnnouncement $withdraw): RedirectResponse
    {
        $withdraw->handle($announcement, $request->validated('reason'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.showcase.flash.withdrawn', ['event' => $announcement->name])]);

        return to_route('console.showcase');
    }
}
