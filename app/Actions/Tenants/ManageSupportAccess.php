<?php

namespace App\Actions\Tenants;

use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * L'acces du support (README section 3 et ecran 25) : ouverture par un Proprietaire, revocation,
 * et trace de chaque page consultee. Chaque geste s'ecrit au journal de l'organisation, qui le
 * relit ; la trace centrale est la table `support_access_views`.
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
    public function open(Tenant $tenant, User $operator, User $grantedBy, int $hours): SupportAccessGrant
    {
        return Cache::lock("support-access:{$tenant->id}", 10)->block(5, function () use ($tenant, $operator, $grantedBy, $hours) {
            if (SupportAccessGrant::where('tenant_id', $tenant->id)->active()->exists()) {
                throw ValidationException::withMessages([
                    'operator_id' => __('support_access.errors.already_open'),
                ]);
            }

            $grant = SupportAccessGrant::create([
                'tenant_id' => $tenant->id,
                'operator_id' => $operator->id,
                'granted_by_id' => $grantedBy->id,
                'expires_at' => now()->addHours($hours),
            ]);

            activity()
                ->performedOn($tenant)
                ->causedBy($grantedBy)
                ->event('created')
                ->withProperties([
                    'operator' => $operator->name,
                    'expires_at' => $grant->expires_at->toISOString(),
                    'tenant_id' => $tenant->id,
                ])
                ->log('support_access.opened');

            return $grant;
        });
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

        return $grant;
    }

    /**
     * Record one page of the organisation consulted through the access, in the central trace
     * and in the organisation's own journal.
     */
    public function recordView(SupportAccessGrant $grant, ?string $routeName): void
    {
        $page = $this->pageOf($routeName);

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
