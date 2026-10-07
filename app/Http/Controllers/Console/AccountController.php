<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\ManageAccount;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ListPage;
use App\Support\Search\UnaccentedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Les comptes vus par l'equipe Convive (README section 3) : retrouver une personne par son nom, son
 * adresse ou son telephone, bloquer ou debloquer son compte, reinitialiser sa double
 * authentification. La zone `accounts` des routes reserve l'ecran aux Fondateurs.
 *
 * Aucune liste complete : l'ecran ne montre que ce qu'une recherche precise ramene, et les comptes
 * actuellement bloques. Il ne donne ni le mot de passe ni le contenu des organisations.
 */
class AccountController extends Controller
{
    /**
     * Une recherche trop courte ramenerait la moitie des comptes.
     */
    private const MinimumSearchLength = 3;

    public function index(Request $request): Response
    {
        // Les jokers de recherche sont retires : « %%% » ne doit pas ramener tous les comptes.
        $search = trim(str_replace(['%', '_'], '', (string) $request->query('q', '')));
        $searchable = mb_strlen($search) >= self::MinimumSearchLength;

        // Les resultats sont pagines par le serveur (TODO du 2026-10-07, point 11) plutot que
        // tronques : un nom courant ne cache plus les comptes au-dela des premiers.
        $results = $searchable
            ? ListPage::of(
                User::query()
                    ->tap(fn (Builder $query) => UnaccentedSearch::apply($query, ['name', 'email', 'phone'], $search))
                    ->orderBy('name'),
                $request,
            )
            : null;

        return Inertia::render('console/accounts', [
            'isSample' => false,
            'search' => $search,
            'minimumSearchLength' => self::MinimumSearchLength,
            'results' => $results?->getCollection()->map(fn (User $user) => $this->summary($user))->values()->all() ?? [],
            'meta' => $results !== null ? ListPage::meta($results) : null,
            'blocked' => User::query()
                ->whereNotNull('blocked_at')
                ->orderByDesc('blocked_at')
                ->get()
                ->map(fn (User $user) => $this->summary($user))
                ->all(),
        ]);
    }

    public function block(Request $request, User $user, ManageAccount $manage): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:500']],
            ['reason.required' => __('console.accounts.errors.reason'), 'reason.min' => __('console.accounts.errors.reason')],
        );

        $manage->block($user, $validated['reason'], $request->user());

        return $this->done('blocked', $user);
    }

    public function unblock(Request $request, User $user, ManageAccount $manage): RedirectResponse
    {
        $manage->unblock($user, $request->user());

        return $this->done('unblocked', $user);
    }

    public function resetTwoFactor(Request $request, User $user, ManageAccount $manage): RedirectResponse
    {
        $manage->resetTwoFactor($user, $request->user());

        return $this->done('two_factor_reset', $user);
    }

    private function done(string $message, User $user): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __("console.accounts.flash.{$message}", ['name' => $user->name])]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'createdAt' => $user->created_at?->toISOString(),
            'hasTwoFactor' => $user->two_factor_confirmed_at !== null,
            // Les noms seulement : le profil porte dans chaque organisation vit dans sa base.
            'organisations' => $user->tenants()->orderBy('name')->get()
                ->map(fn (Tenant $tenant) => $tenant->name)
                ->all(),
            'blockedAt' => $user->blocked_at?->toISOString(),
            'blockedReason' => $user->blocked_reason,
        ];
    }
}
