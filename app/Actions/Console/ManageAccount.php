<?php

namespace App\Actions\Console;

use App\Models\User;
use App\Notifications\Console\TwoFactorResetByEditor;
use App\Support\Console\ConsoleJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ce que l'equipe Convive fait d'un compte depuis la console (README section 3) : le bloquer quand
 * il est compromis ou abusif, le debloquer, et reinitialiser sa double authentification quand la
 * personne a perdu son telephone. Chaque geste s'ecrit au journal central.
 *
 * Aucun de ces gestes ne donne acces au compte ni a son contenu : le mot de passe n'est jamais lu
 * ni change d'ici.
 */
class ManageAccount
{
    /**
     * Block the account : it can no longer sign in, and its open sessions end at once.
     *
     * @throws ValidationException
     */
    public function block(User $user, string $reason, User $actor): void
    {
        // Personne ne se bloque soi-meme : un geste malheureux fermerait la console a son auteur.
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['reason' => __('console.accounts.errors.self')]);
        }

        if ($user->isBlocked()) {
            throw ValidationException::withMessages(['reason' => __('console.accounts.errors.already_blocked')]);
        }

        $user->forceFill(['blocked_at' => now(), 'blocked_reason' => $reason])->save();

        $this->closeSessions($user);

        ConsoleJournal::record('account_blocked', $actor, null, ['user_id' => $user->id, 'email' => $user->email, 'reason' => $reason]);
    }

    public function unblock(User $user, User $actor): void
    {
        if (! $user->isBlocked()) {
            return;
        }

        $user->forceFill(['blocked_at' => null, 'blocked_reason' => null])->save();

        ConsoleJournal::record('account_unblocked', $actor, null, ['user_id' => $user->id, 'email' => $user->email]);
    }

    /**
     * Remove the two-factor authentication of the account, for someone who lost their device. La
     * personne est prevenue par courriel : si elle n'a rien demande, elle le saura. Les profils
     * qui exigent la double authentification l'obligeront a la reconfigurer a sa prochaine visite.
     *
     * @throws ValidationException
     */
    public function resetTwoFactor(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['account' => __('console.accounts.errors.self')]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Les sessions ouvertes sont fermees : si le compte etait entre de mauvaises mains, elles
        // ne survivent pas a la reinitialisation.
        $this->closeSessions($user);

        ConsoleJournal::record('two_factor_reset', $actor, null, ['user_id' => $user->id, 'email' => $user->email]);

        $user->notify(new TwoFactorResetByEditor);
    }

    private function closeSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('tenancy.database.central_connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
