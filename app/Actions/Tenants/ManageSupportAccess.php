<?php

namespace App\Actions\Tenants;

use App\Enums\ConsoleArea;
use App\Models\SupportAccessGrant;
use App\Models\SupportAccessRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\SupportAccessEnded;
use App\Notifications\Tenants\SupportAccessOpened;
use App\Notifications\Tenants\SupportAccessRequested;
use App\Notifications\Tenants\SupportAccessRequestTaken;
use App\Support\Console\ConsoleAccess;
use App\Support\Console\ConsoleJournal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * L'acces du support (README section 3 et ecran 25) : ouverture par un Proprietaire, fermeture
 * (revocation, « j'ai termine » de la personne de l'equipe Convive, ou echeance), et trace de
 * chaque page consultee. Chaque geste s'ecrit au journal de l'organisation, qui le relit ; la
 * trace centrale est la table `support_access_views`.
 *
 * Deux messages encadrent l'acces : la personne de l'equipe Convive est prevenue a l'ouverture,
 * les Proprietaires quand il se termine. Quand personne de l'equipe n'est visible, une demande
 * d'aide la previent d'abord (`request`, `take`).
 */
class ManageSupportAccess
{
    /**
     * Pages reconnues dans la trace, de la plus precise a la plus generale : le premier prefixe
     * de nom de route qui correspond l'emporte.
     *
     * @var array<string, string>
     */
    private const Pages = [
        'tenants.events.registrations.' => 'registrations',
        'tenants.events.proofs.' => 'proofs',
        'tenants.events.seating.' => 'seating',
        'tenants.events.report.' => 'reports',
        'tenants.events.' => 'events',
        'tenants.audit.' => 'audit',
        'tenants.organisation.' => 'organisation',
        'dashboard' => 'dashboard',
    ];

    /**
     * Open a read-only access to the organisation for one member of the Convive team.
     *
     * Un seul acces a la fois. Le verrou couvre la verification et la creation : deux ouvertures
     * simultanees n'en laissent qu'une (SQLite n'offre pas de verrou de ligne, voir CLAUDE.md,
     * « Base de donnees »).
     *
     * @throws ValidationException
     */
    public function open(Tenant $tenant, User $operator, User $grantedBy, int $hours, string $reason): SupportAccessGrant
    {
        $grant = Cache::lock("support-access:{$tenant->id}", 10)->block(5, function () use ($tenant, $operator, $grantedBy, $hours, $reason) {
            if (SupportAccessGrant::where('tenant_id', $tenant->id)->active()->exists()) {
                throw ValidationException::withMessages([
                    'operator_id' => __('support_access.errors.already_open'),
                ]);
            }

            $grant = SupportAccessGrant::create([
                'tenant_id' => $tenant->id,
                'operator_id' => $operator->id,
                'granted_by_id' => $grantedBy->id,
                'reason' => $reason,
                'expires_at' => now()->addHours($hours),
            ]);

            // La demande d'aide a obtenu ce qu'elle demandait.
            SupportAccessRequest::where('tenant_id', $tenant->id)->pending()->update(['closed_at' => now()]);

            activity()
                ->performedOn($tenant)
                ->causedBy($grantedBy)
                ->event('created')
                ->withProperties([
                    'operator' => $operator->name,
                    'reason' => $reason,
                    'expires_at' => $grant->expires_at->toISOString(),
                    'tenant_id' => $tenant->id,
                ])
                ->log('support_access.opened');

            return $grant;
        });

        // Sans ce message, la personne ne le saurait qu'en ouvrant la console.
        $operator->notify(new SupportAccessOpened($grant));

        return $grant;
    }

    /**
     * Tell the Convive team the organisation wants to open its space, when nobody there is visible
     * to receive the access. N'ouvre rien : la demande previent, c'est tout.
     *
     * Une seule demande en attente par organisation, sous le meme verrou que l'ouverture.
     *
     * @throws ValidationException
     */
    public function request(Tenant $tenant, User $requestedBy, string $reason): SupportAccessRequest
    {
        $request = Cache::lock("support-access:{$tenant->id}", 10)->block(5, function () use ($tenant, $requestedBy, $reason) {
            if (SupportAccessRequest::where('tenant_id', $tenant->id)->pending()->exists()) {
                throw ValidationException::withMessages([
                    'reason' => __('support_access.errors.already_requested'),
                ]);
            }

            $request = SupportAccessRequest::create([
                'tenant_id' => $tenant->id,
                'requested_by_id' => $requestedBy->id,
                'reason' => $reason,
            ]);

            activity()
                ->performedOn($tenant)
                ->causedBy($requestedBy)
                ->event('created')
                ->withProperties(['reason' => $reason, 'tenant_id' => $tenant->id])
                ->log('support_access.requested');

            return $request;
        });

        ConsoleJournal::record('support_access_requested', $requestedBy, $tenant);

        // Toute l'equipe qui peut recevoir un acces, visible ou non : c'est justement parce que
        // personne n'est visible que la demande existe.
        $team = User::whereIn('email', ConsoleAccess::emailsAllowedTo(ConsoleArea::Support))->get();

        Notification::send($team, new SupportAccessRequested($tenant->name, $requestedBy->name, $reason));

        return $request;
    }

    /**
     * Withdraw a request the organisation no longer needs.
     */
    public function cancelRequest(SupportAccessRequest $request, User $cancelledBy): void
    {
        if (! $request->isPending()) {
            return;
        }

        $request->update(['closed_at' => now()]);

        activity()
            ->performedOn($request->tenant)
            ->causedBy($cancelledBy)
            ->event('deleted')
            ->withProperties(['tenant_id' => $request->tenant_id])
            ->log('support_access.request_cancelled');
    }

    /**
     * Let a member of the Convive team take a request : ils deviennent visibles de cette
     * organisation, et d'elle seule (`SupportAccessController::operators()`), dont les
     * Proprietaires sont prevenus. L'acces reste a ouvrir par l'un d'eux.
     *
     * Appelee depuis la console, donc hors de la tenancy de l'organisation : la ligne de son
     * journal s'ecrit sous `run()`.
     */
    public function take(SupportAccessRequest $request, User $operator): void
    {
        if (! $request->isPending() || $request->taken_by_id === $operator->id) {
            return;
        }

        $request->update(['taken_by_id' => $operator->id, 'taken_at' => now()]);

        $tenant = $request->tenant;

        $tenant->run(fn () => activity()
            ->performedOn($tenant)
            ->causedBy($operator)
            ->event('updated')
            ->withProperties(['operator' => $operator->name, 'tenant_id' => $tenant->id])
            ->log('support_access.request_taken'));

        ConsoleJournal::record('support_access_request_taken', $operator, $tenant);

        Notification::send($tenant->owners(), new SupportAccessRequestTaken($tenant->name, $tenant->slug, $operator->name));
    }

    /**
     * Close an access before its term : its holder reads nothing from the next request on.
     */
    public function revoke(SupportAccessGrant $grant, User $revokedBy): SupportAccessGrant
    {
        if (! $grant->isActive()) {
            return $grant;
        }

        $grant->update(['revoked_at' => now(), 'revoked_by_id' => $revokedBy->id]);

        activity()
            ->performedOn($grant->tenant)
            ->causedBy($revokedBy)
            ->event('deleted')
            ->withProperties([
                'operator' => $grant->operator->name,
                'tenant_id' => $grant->tenant_id,
            ])
            ->log('support_access.revoked');

        // Celui qui revoque sait ce qu'il vient de faire : seuls les autres Proprietaires sont
        // prevenus.
        $this->announceEnd($grant, 'revoked', $revokedBy);

        return $grant;
    }

    /**
     * Let the member of the Convive team close the access once done, with what they found.
     *
     * Appelee depuis la console, donc hors de la tenancy de l'organisation : la ligne de son
     * journal s'ecrit sous `run()`.
     */
    public function finish(SupportAccessGrant $grant, string $note): SupportAccessGrant
    {
        if (! $grant->isActive()) {
            return $grant;
        }

        $grant->update(['finished_at' => now(), 'closing_note' => $note]);

        $grant->tenant->run(fn () => activity()
            ->performedOn($grant->tenant)
            ->causedBy($grant->operator)
            ->event('deleted')
            ->withProperties([
                'operator' => $grant->operator->name,
                'closing_note' => $note,
                'tenant_id' => $grant->tenant_id,
            ])
            ->log('support_access.finished'));

        ConsoleJournal::record('support_access_finished', $grant->operator, $grant->tenant);

        $this->announceEnd($grant, 'finished');

        return $grant;
    }

    /**
     * Tell the owners about every access that reached its term since the last run. Jouee par une
     * tache planifiee : un acces expire sans que personne n'agisse, donc sans requete pour le dire.
     */
    public function announceExpired(): int
    {
        return SupportAccessGrant::query()
            ->whereNull('ended_notified_at')
            ->whereNull('revoked_at')
            ->whereNull('finished_at')
            ->where('expires_at', '<=', now())
            // Une organisation mise a la corbeille n'a plus personne a prevenir.
            ->whereHas('tenant')
            ->with('tenant', 'operator')
            ->get()
            ->each(fn (SupportAccessGrant $grant) => $this->announceEnd($grant, 'expired'))
            ->count();
    }

    /**
     * Record one page of the organisation consulted through the access, in the central trace
     * and in the organisation's own journal.
     */
    public function recordView(SupportAccessGrant $grant, ?string $routeName): void
    {
        $page = $this->pageOf($routeName);

        // Le journal central retient que l'acces a servi, une fois : le detail des pages est dans
        // `support_access_views` et au journal de l'organisation.
        if (! $grant->views()->exists()) {
            ConsoleJournal::record('support_access_used', $grant->operator, $grant->tenant);
        }

        $grant->views()->create([
            'page' => $page,
            'route' => $routeName,
            'viewed_at' => now(),
        ]);

        activity()
            ->performedOn($grant->tenant)
            ->causedBy($grant->operator)
            ->event('viewed')
            ->withProperties([
                'page' => $page,
                'route' => $routeName,
                'tenant_id' => $grant->tenant_id,
            ])
            ->log('support_access.page_viewed');
    }

    /**
     * Tell the owners the access is over, once. `ended_notified_at` est pose avant l'envoi : mieux
     * vaut un message manque qu'un message envoye deux fois par une tache rejouee.
     *
     * @param  string  $endReason  `finished`, `revoked` ou `expired`
     */
    private function announceEnd(SupportAccessGrant $grant, string $endReason, ?User $except = null): void
    {
        if ($grant->ended_notified_at !== null) {
            return;
        }

        $grant->forceFill(['ended_notified_at' => now()])->save();

        $tenant = $grant->tenant;

        $recipients = $tenant->owners()->reject(fn (User $owner) => $except !== null && $owner->is($except));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SupportAccessEnded(
            $tenant->name,
            $tenant->slug,
            $grant->operator->name,
            $endReason,
            $grant->views()->count(),
            $grant->closing_note,
        ));
    }

    private function pageOf(?string $routeName): string
    {
        foreach (self::Pages as $prefix => $page) {
            if ($routeName !== null && str_starts_with($routeName, $prefix)) {
                return $page;
            }
        }

        return 'other';
    }
}
