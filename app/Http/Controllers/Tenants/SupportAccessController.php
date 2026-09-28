<?php

namespace App\Http\Controllers\Tenants;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'acces du support (README ecran 25 et section 3) : un Proprietaire ouvre a un membre nomme de
 * l'equipe Convive un acces en lecture seule, de 24 heures au plus, qu'il peut revoquer, et relit
 * ce qui a ete consulte.
 *
 * PROVISOIRE : rendu sur un jeu d'exemple, sans ouverture ni revocation reelles. Remplace, pas
 * complete, quand `SupportAccessGrant` et les comptes editeur arrivent (etape 11).
 */
class SupportAccessController extends Controller
{
    /**
     * Durees proposees, en heures. Le plafond de 24 heures est une decision du proprietaire du
     * projet (2026-09-28), il ne se saisit pas en texte libre.
     */
    private const DurationsInHours = [1, 4, 12, 24];

    public function show(Tenant $tenant): Response
    {
        Gate::authorize('manageSupportAccess', $tenant);

        return Inertia::render('tenants/support-access', [
            'tenant' => ['slug' => $tenant->slug, 'name' => $tenant->name],
            'isSample' => true,
            'durations' => self::DurationsInHours,
            // PROVISOIRE : l'equipe Convive a qui un acces peut etre ouvert.
            'operators' => [
                ['id' => 2, 'name' => 'Awa Traoré'],
                ['id' => 4, 'name' => 'Serge Kouadio'],
            ],
            // PROVISOIRE : un acces en cours, pour juger l'ecran dans cet etat.
            'activeAccess' => [
                'operator' => 'Awa Traoré',
                'grantedBy' => $tenant->owner()?->name,
                'grantedAt' => Carbon::now()->subHours(7)->toISOString(),
                'expiresAt' => Carbon::now()->addHours(17)->toISOString(),
                'views' => [
                    ['at' => Carbon::now()->subHours(2)->toISOString(), 'page' => 'events'],
                    ['at' => Carbon::now()->subHours(2)->addMinutes(3)->toISOString(), 'page' => 'registrations'],
                    ['at' => Carbon::now()->subHours(2)->addMinutes(9)->toISOString(), 'page' => 'audit'],
                ],
            ],
            'pastAccesses' => [
                [
                    'id' => 1,
                    'operator' => 'Serge Kouadio',
                    'grantedAt' => Carbon::now()->subDays(40)->toISOString(),
                    'endedAt' => Carbon::now()->subDays(40)->addHours(4)->toISOString(),
                    'endReason' => 'expired',
                    'viewsCount' => 6,
                ],
                [
                    'id' => 2,
                    'operator' => 'Awa Traoré',
                    'grantedAt' => Carbon::now()->subDays(75)->toISOString(),
                    'endedAt' => Carbon::now()->subDays(75)->addMinutes(50)->toISOString(),
                    'endReason' => 'revoked',
                    'viewsCount' => 2,
                ],
            ],
        ]);
    }
}
