<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Console\ConsoleJournal;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Efface les organisations dont la suppression programmee est arrivee a echeance (README
 * section 3) : leur base, leurs fichiers, puis leur enregistrement central. Le journal central
 * garde la trace de l'operation, sans les donnees.
 *
 * Sans `--force`, la commande ne fait que lister ce qu'elle effacerait : l'effacement ne se
 * rattrape pas. Elle n'est volontairement pas planifiee tant que ses tests n'ont pas ete executes ;
 * d'ici la, elle se lance a la main.
 */
#[Signature('tenants:erase-scheduled {--force : Efface reellement, au lieu de lister}')]
#[Description('Efface les organisations dont la suppression programmee est arrivee a echeance')]
class EraseScheduledTenantsCommand extends Command
{
    public function handle(): int
    {
        $due = Tenant::query()
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->components->info('Aucune organisation a effacer.');

            return self::SUCCESS;
        }

        foreach ($due as $tenant) {
            if (! $this->option('force')) {
                $this->components->warn("A effacer : {$tenant->name} (echeance {$tenant->deletion_scheduled_at?->toDateString()}).");

                continue;
            }

            $this->erase($tenant);
            $this->components->task("Effacee : {$tenant->name}");
        }

        return self::SUCCESS;
    }

    private function erase(Tenant $tenant): void
    {
        // La trace d'abord, avec le nom recopie : elle doit survivre a l'enregistrement.
        ConsoleJournal::record('tenant_erased', null, $tenant, [
            'scheduled_for' => $tenant->deletion_scheduled_at?->toISOString(),
        ]);

        Storage::disk('tenant_media')->deleteDirectory("tenants/{$tenant->id}");

        $database = $tenant->database();

        if ($database->manager()->databaseExists($database->getName())) {
            $database->manager()->deleteDatabase($tenant);
        }

        // Suppression reelle, pas la corbeille : les lignes centrales de l'organisation partent
        // avec elle par leurs cles etrangeres.
        $tenant->forceDelete();
    }
}
