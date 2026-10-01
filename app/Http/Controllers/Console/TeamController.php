<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\ManageConsoleTeam;
use App\Enums\ConsoleProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\InviteConsoleOperatorRequest;
use App\Models\ConsoleOperator;
use App\Models\User;
use App\Support\Console\ConsoleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'equipe editeur (README ecran 34) : ses membres, leurs profils, les invitations en attente.
 * Reservee aux Fondateurs (zone `team`, voir `ConsoleProfile`).
 *
 * Un membre est une adresse de `console_operators` qu'un compte porte deja ; une invitation en
 * attente, une adresse qu'aucun compte ne porte encore. Les Fondateurs de depart viennent de
 * `convive.console.operators` et ne se retirent pas d'ici.
 */
class TeamController extends Controller
{
    /**
     * Display the team and its pending invitations.
     */
    public function index(Request $request): Response
    {
        $invited = ConsoleOperator::orderBy('email')->get();
        $bootstrap = ConsoleAccess::bootstrapFounders();

        $users = User::whereIn('email', [...$bootstrap, ...$invited->pluck('email')->all()])
            ->get()
            ->keyBy(fn (User $user) => strtolower($user->email));

        // La derniere activite connue, lue sur les sessions ouvertes : la table des comptes ne
        // garde pas de date de connexion.
        $lastSeen = DB::connection('central')->table('sessions')
            ->whereIn('user_id', $users->pluck('id')->all())
            ->selectRaw('user_id, max(last_activity) as last_activity')
            ->groupBy('user_id')
            ->pluck('last_activity', 'user_id');

        $member = fn (User $user, ConsoleProfile $profile, ?int $operatorId) => [
            'id' => $user->id,
            'operatorId' => $operatorId,
            'name' => $user->name,
            'email' => $user->email,
            'profile' => $profile->value,
            'twoFactor' => $user->hasEnabledTwoFactorAuthentication(),
            'lastLoginAt' => isset($lastSeen[$user->id]) ? Carbon::createFromTimestamp((int) $lastSeen[$user->id])->toISOString() : null,
            // Ni un Fondateur de depart, ni soi-meme.
            'removable' => $operatorId !== null && $user->isNot($request->user()),
        ];

        $founders = collect($bootstrap)
            ->map(fn (string $email) => $users->get($email))
            ->filter()
            ->map(fn (User $user) => $member($user, ConsoleProfile::Founder, null));

        $members = $invited
            ->reject(fn (ConsoleOperator $operator) => in_array($operator->email, $bootstrap, true))
            ->filter(fn (ConsoleOperator $operator) => $users->has($operator->email))
            ->map(fn (ConsoleOperator $operator) => $member($users->get($operator->email), $operator->profile, $operator->id));

        return Inertia::render('console/team', [
            'isSample' => false,
            'operators' => $founders->concat($members)->values()->all(),
            'invitations' => $invited
                ->reject(fn (ConsoleOperator $operator) => $users->has($operator->email))
                ->map(fn (ConsoleOperator $operator) => [
                    'id' => $operator->id,
                    'email' => $operator->email,
                    'profile' => $operator->profile->value,
                    'sentAt' => $operator->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Invite a person to the team.
     */
    public function store(InviteConsoleOperatorRequest $request, ManageConsoleTeam $manage): RedirectResponse
    {
        $operator = $manage->invite(
            $request->validated('email'),
            ConsoleProfile::from($request->validated('profile')),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.team.flash.invited', ['email' => $operator->email])]);

        return to_route('console.team');
    }

    /**
     * Remove a member, or cancel a pending invitation.
     */
    public function destroy(Request $request, ConsoleOperator $operator, ManageConsoleTeam $manage): RedirectResponse
    {
        $manage->remove($operator, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.team.flash.removed', ['email' => $operator->email])]);

        return to_route('console.team');
    }
}
