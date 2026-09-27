<?php

namespace Database\Seeders;

use App\Actions\Events\SaveEvent;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Annonce sur la vitrine du site produit les evenements publies et non clos de l'organisation de
 * demonstration, pour que « Evenements a la une » ait quelque chose a montrer. Passe par
 * `SaveEvent::announce()`, comme le bouton du back-office : la copie centrale (nom, date, lien,
 * visuel) est tenue par le meme code. Joue apres `EventVisualSeeder`, pour que le visuel suive.
 */
class ShowcaseSeeder extends Seeder
{
    public function run(SaveEvent $save): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant) {
            return;
        }

        $tenant->run(fn () => Event::query()
            ->published()
            ->where('status', '!=', EventStatus::Closed)
            ->whereNull('announced_at')
            ->each(fn (Event $event) => $save->announce($event)));
    }
}
