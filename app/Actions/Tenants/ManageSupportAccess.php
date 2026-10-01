<?php

namespace App\Actions\Tenants;

use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\SupportAccessEnded;
use App\Notifications\Tenants\SupportAccessOpened;
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
 * les Proprietaires quand il se termine.
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
