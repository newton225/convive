<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nettoyage unique d'une organisation (decision du 2026-11-01, donnees d'essai du pilote) : efface pour de bon
 * tous ses evenements sauf ceux a conserver, avec ce qui s'y rattache. Le produit ne supprime jamais un
 * evenement publie (il se cloture) : ce n'est pas une fonction de l'interface, c'est un geste de maintenance
 * lance sur le serveur.
 *
 * Sans `--force`, la commande ne fait que lister ce qu'elle effacerait : l'effacement ne se rattrape pas.
 * `--keep` est obligatoire, pour qu'un oubli n'efface jamais tout.
 *
 * Le journal d'audit garde la trace de chaque evenement efface (son nom et ses effectifs, aucune donnee
 * personnelle). Les sauvegardes deja faites gardent ces donnees jusqu'au terme de leur conservation, un an au plus
 * (`config/backup.php`).
 */
#[Signature('convive:erase-events {tenant : Adresse (slug) ou identifiant de l\'organisation} {--keep=* : Identifiant d\'un evenement a conserver (au moins un)} {--force : Efface reellement, au lieu de lister}')]
#[Description('Efface les evenements d\'une organisation, sauf ceux a conserver, avec leurs inscriptions et leurs fichiers')]
class EraseEventsCommand extends Command
{
    public function handle(): int
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            $this->components->error('Organisation introuvable.');

            return self::FAILURE;
        }

        $keep = array_values(array_unique(array_map('intval', (array) $this->option('keep'))));

        if ($keep === []) {
            $this->components->error('Indiquez au moins un evenement a conserver avec --keep=ID : sans cela, tous seraient effaces.');

            return self::FAILURE;
        }

        return $tenant->run(fn () => $this->clean($tenant, $keep));
    }

    /**
     * @param  array<int, int>  $keep
     */
    private function clean(Tenant $tenant, array $keep): int
    {
        $kept = Event::withTrashed()->whereIn('id', $keep)->get();

        if ($kept->count() !== count($keep)) {
            $missing = array_diff($keep, $kept->pluck('id')->all());

            $this->components->error('Evenement a conserver introuvable dans cette organisation : '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        $targets = Event::withTrashed()->whereNotIn('id', $keep)->orderBy('id')->get();

        $this->components->info("Organisation : {$tenant->name}");
        $this->components->twoColumnDetail('A conserver', $kept->map(fn (Event $event) => "#{$event->id} {$event->name}")->implode(', '));

        if ($targets->isEmpty()) {
            $this->components->info('Aucun autre evenement : rien a effacer.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Evenement', 'Publie', 'Inscriptions', 'Preuves', 'Liste d\'attente'],
            $targets->map(fn (Event $event) => [
                $event->id,
                $event->name,
                $event->isPublished() ? 'oui' : 'non',
                ...array_values($this->counts($event)),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->components->warn('Simulation : rien n\'a ete efface. Relancez avec --force pour effacer pour de bon.');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->components->confirm('Effacer definitivement ces '.$targets->count().' evenement(s) et tout ce qui s\'y rattache ? Cela ne se rattrape pas.')) {
            $this->components->warn('Abandon : rien n\'a ete efface.');

            return self::FAILURE;
        }

        foreach ($targets as $event) {
            $this->erase($tenant, $event);
            $this->components->task("Efface : #{$event->id} {$event->name}");
        }

        $this->components->info('Termine. Les sauvegardes deja faites gardent ces donnees jusqu\'au terme de leur conservation.');

        return self::SUCCESS;
    }

    /**
     * L'organisation designee par son adresse (slug) ou, a defaut, par son identifiant.
     */
    private function tenant(): ?Tenant
    {
        $argument = (string) $this->argument('tenant');

        return Tenant::where('slug', $argument)->first()
            ?? (ctype_digit($argument) ? Tenant::find((int) $argument) : null);
    }

    /**
     * @return array{registrations: int, proofs: int, waitlist: int}
     */
    private function counts(Event $event): array
    {
        $registrationIds = DB::table('registrations')->where('event_id', $event->id)->select('id');

        return [
            'registrations' => DB::table('registrations')->where('event_id', $event->id)->count(),
            'proofs' => PaymentProof::whereIn('registration_id', $registrationIds)->count(),
            'waitlist' => WaitlistEntry::where('event_id', $event->id)->count(),
        ];
    }

    /**
     * Efface un evenement : l'annonce sur la vitrine, les captures de preuve une par une (la suppression d'un
     * modele retire sa capture du disque, ce que la cascade de la base ne ferait pas), la trace au journal, puis
     * l'evenement lui-meme, dont la cascade emporte inscriptions, billets, tables, liste d'attente, passages
     * scannes et releves importes.
     */
    private function erase(Tenant $tenant, Event $event): void
    {
        $counts = $this->counts($event);

        ShowcaseEvent::where('tenant_id', $tenant->id)->where('event_id', $event->id)->delete();

        PaymentProof::whereIn('registration_id', DB::table('registrations')->where('event_id', $event->id)->select('id'))
            ->get()
            ->each(fn (PaymentProof $proof) => $proof->delete());

        // Avant l'effacement, et sans nom ni numero d'invite : le nom de l'evenement et ses effectifs suffisent.
        activity()
            ->event('deleted')
            ->withProperties(['old' => ['name' => $event->name], ...$counts, 'cleanup' => true])
            ->log('event.erased');

        $event->forceDelete();
    }
}
