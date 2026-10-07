<?php

namespace App\Support;

use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La carte « Premiers pas » du tableau de bord : les etapes qui menent une organisation neuve
 * jusqu'a son premier lien public, dans l'ordre ou `Event::isReadyToPublish()` les exige.
 *
 * Chaque etape se coche d'apres les donnees reelles, jamais d'apres un clic : une etape
 * « faite » qui ne le serait pas bloquerait la publication sans que la carte l'explique. A lire
 * dans la tenancy de l'organisation (evenements et comptes vivent dans sa base).
 */
final class GettingStarted
{
    /**
     * Ajoute par la carte a l'adresse du formulaire de chaque etape, puis a celle de son envoi : il
     * dit que la personne vient de la carte et doit y revenir. Porte par l'adresse plutot que par la
     * session, il ne survit pas a une visite abandonnee : le meme formulaire ouvert ensuite depuis
     * le menu garde son comportement habituel.
     */
    public const ReturnQuery = ['via' => 'getting-started'];

    /**
     * @return array{steps: array<int, array{key: string, done: bool}>, completed: int}|null
     */
    public static function for(Tenant $tenant, User $user): ?array
    {
        // Seul qui peut creer un evenement a quelque chose a faire de cette carte.
        if (! $user->hasTenantPermission($tenant, TenantPermission::EventsCreate)) {
            return null;
        }

        $steps = [
            ['key' => 'identity', 'done' => $tenant->isReadyToPublish()],
            ['key' => 'payment_account', 'done' => PaymentAccount::publiclyVisible()->exists()],
            ['key' => 'event', 'done' => Event::query()->exists()],
            ['key' => 'publish', 'done' => Event::published()->exists()],
            ['key' => 'team', 'done' => $tenant->memberships()->count() > 1
                || $tenant->invitations()->whereNull('accepted_at')->exists()],
        ];

        $completed = count(array_filter($steps, fn (array $step) => $step['done']));

        return $completed === count($steps) ? null : ['steps' => $steps, 'completed' => $completed];
    }

    /**
     * Apres l'enregistrement d'une etape ouverte depuis la carte, le tableau de bord, qui montre la
     * progression et l'etape suivante ; sinon la destination habituelle du formulaire.
     */
    public static function redirect(Request $request, Tenant $tenant, RedirectResponse $default): RedirectResponse
    {
        return $request->query('via') === self::ReturnQuery['via']
            ? to_route('dashboard', $tenant)
            : $default;
    }
}
