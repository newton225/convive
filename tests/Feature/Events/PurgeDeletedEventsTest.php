<?php

namespace Tests\Feature\Events;

use App\Actions\Events\PurgeDeletedEvents;
use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un evenement supprime (un brouillon : un evenement publie se cloture) reste trente jours dans la
 * corbeille, puis s'efface pour de bon avec son visuel et ses tables. Sans cela il resterait dans
 * la base indefiniment, sans ecran pour le revoir ni le retirer.
 */
class PurgeDeletedEventsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('tenant_media');

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
    }

    public function test_un_evenement_supprime_depuis_plus_de_trente_jours_est_efface_avec_son_visuel(): void
    {
        [$event, $path] = $this->tenant->asCurrent(function () {
            $event = Event::factory()->create(['tables' => null]);
            SeatingTable::create(['event_id' => $event->id, 'number' => 1, 'capacity' => 8]);
            $media = $event->addMedia(UploadedFile::fake()->image('visuel.jpg', 600, 400))
                ->toMediaCollection(Event::VisualCollection);
            $event->delete();

            return [$event, $media->getPathRelativeToRoot()];
        });

        $this->travel(31)->days();

        $purged = $this->tenant->asCurrent(fn () => app(PurgeDeletedEvents::class)->handle());

        $this->assertSame(1, $purged);
        Storage::disk('tenant_media')->assertMissing($path);
        $this->tenant->asCurrent(function () use ($event) {
            $this->assertNull(Event::withTrashed()->find($event->id));
            $this->assertSame(0, SeatingTable::where('event_id', $event->id)->count());
        });
    }

    public function test_un_evenement_supprime_recemment_reste_dans_la_corbeille(): void
    {
        $event = $this->tenant->asCurrent(function () {
            $event = Event::factory()->create();
            $event->delete();

            return $event;
        });

        $this->travel(29)->days();

        $this->assertSame(0, $this->tenant->asCurrent(fn () => app(PurgeDeletedEvents::class)->handle()));
        $this->tenant->asCurrent(fn () => $this->assertNotNull(Event::withTrashed()->find($event->id)));
    }

    public function test_un_evenement_non_supprime_n_est_jamais_touche(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->published()->create());

        $this->travel(400)->days();

        $this->assertSame(0, $this->tenant->asCurrent(fn () => app(PurgeDeletedEvents::class)->handle()));
        $this->tenant->asCurrent(fn () => $this->assertNotNull(Event::find($event->id)));
    }
}
