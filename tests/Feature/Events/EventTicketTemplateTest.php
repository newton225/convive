<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\BrandFile;
use App\Enums\TenantPermission;
use App\Enums\TicketModel;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TicketCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le gabarit du billet propre a un evenement (README ecran 15) : quand il est active, il l'emporte
 * sur celui de l'organisation ; sinon c'est celui de l'organisation qui s'applique.
 */
class EventTicketTemplateTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaRoot = storage_path('framework/testing/tenant-media-'.Str::random(8));

        config(['filesystems.disks.tenant_media.root' => $this->mediaRoot]);
        Storage::forgetDisk('tenant_media');

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->tenant->brandingOrCreate()->update([
            'ticket_model' => TicketModel::Sober,
            'ticket_element_stamp' => false,
        ]);
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);

        parent::tearDown();
    }

    private function memberWith(TenantPermission ...$permissions): User
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, $permissions);

        return $member;
    }

    /**
     * @return array{model: string, elements: array<string, bool>, brand: array<string, mixed>}
     */
    private function design(): array
    {
        return $this->tenant->asCurrent(fn () => TicketCard::design($this->tenant, $this->event->fresh()));
    }

    public function test_sans_gabarit_propre_l_ecran_montre_celui_de_l_organisation(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.ticket-template.edit', [$this->tenant, $this->event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/ticket-template')
                ->where('enabled', false)
                ->where('model', 'sober')
                ->where('elements.stamp', false)
                ->where('event.id', $this->event->id),
            );
    }

    public function test_le_gabarit_active_sur_l_evenement_l_emporte_sur_celui_de_l_organisation(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('tenants.events.ticket-template.update', [$this->tenant, $this->event]), [
                'ticket_template_enabled' => true,
                'ticket_model' => 'elegant',
                'ticket_element_logo' => false,
                'ticket_element_stamp' => true,
                'ticket_element_signature' => true,
                'ticket_element_companions' => false,
            ])
            ->assertRedirect(route('tenants.events.ticket-template.edit', [$this->tenant, $this->event]));

        $design = $this->design();

        $this->assertSame('elegant', $design['model']);
        $this->assertSame(
            ['logo' => false, 'stamp' => true, 'signature' => true, 'companions' => false],
            $design['elements'],
        );
    }

    public function test_un_gabarit_desactive_rend_la_main_a_celui_de_l_organisation(): void
    {
        $this->tenant->asCurrent(fn () => $this->event->forceFill([
            'ticket_template_enabled' => true,
            'ticket_model' => TicketModel::Elegant,
        ])->save());

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.ticket-template.update', [$this->tenant, $this->event]), [
                'ticket_template_enabled' => false,
            ])
            ->assertSessionHasNoErrors();

        $design = $this->design();

        $this->assertSame('sober', $design['model']);
        $this->assertFalse($design['elements']['stamp']);
    }

    public function test_un_modele_inconnu_est_refuse(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('tenants.events.ticket-template.update', [$this->tenant, $this->event]), [
                'ticket_template_enabled' => true,
                'ticket_model' => 'baroque',
            ])
            ->assertSessionHasErrors('ticket_model');
    }

    public function test_le_fond_propre_a_l_evenement_l_emporte_quand_son_gabarit_est_active(): void
    {
        $this->actingAs($this->owner)->post(
            route('tenants.organisation.files.store', [$this->tenant, BrandFile::TicketBackground->value]),
            ['file' => UploadedFile::fake()->image('organisation.jpg', 1200, 400)],
        );

        $this->actingAs($this->owner)
            ->post(
                route('tenants.events.ticket-template.files.store', [$this->tenant, $this->event, BrandFile::TicketBackground->value]),
                ['file' => UploadedFile::fake()->image('evenement.jpg', 1200, 400)],
            )
            ->assertSessionHasNoErrors();

        $organisationUrl = $this->design()['brand']['backgroundUrl'];
        $this->assertNotNull($organisationUrl);

        $this->tenant->asCurrent(fn () => $this->event->forceFill(['ticket_template_enabled' => true])->save());

        $eventUrl = $this->design()['brand']['backgroundUrl'];
        $media = $this->tenant->asCurrent(fn () => $this->event->fresh()->getFirstMedia(BrandFile::TicketBackground->value));

        $this->assertNotNull($media);
        $this->assertStringContainsString((string) $media->id, (string) $eventUrl);
        $this->assertNotSame($organisationUrl, $eventUrl);
    }

    public function test_sans_fond_propre_celui_de_l_organisation_s_applique(): void
    {
        $this->actingAs($this->owner)->post(
            route('tenants.organisation.files.store', [$this->tenant, BrandFile::TicketBodyBackground->value]),
            ['file' => UploadedFile::fake()->image('organisation.jpg', 800, 1000)],
        );

        $this->tenant->asCurrent(fn () => $this->event->forceFill(['ticket_template_enabled' => true])->save());

        $this->assertNotNull($this->design()['brand']['bodyBackgroundUrl']);
    }

    public function test_seuls_les_fonds_du_billet_se_deposent_sur_un_evenement(): void
    {
        $this->actingAs($this->owner)
            ->post(
                route('tenants.events.ticket-template.files.store', [$this->tenant, $this->event, BrandFile::Logo->value]),
                ['file' => UploadedFile::fake()->image('logo.png')],
            )
            ->assertNotFound();
    }

    public function test_le_retrait_du_fond_propre_rend_la_main_a_celui_de_l_organisation(): void
    {
        $this->actingAs($this->owner)->post(
            route('tenants.events.ticket-template.files.store', [$this->tenant, $this->event, BrandFile::TicketBackground->value]),
            ['file' => UploadedFile::fake()->image('evenement.jpg', 1200, 400)],
        );

        $this->actingAs($this->owner)
            ->delete(route('tenants.events.ticket-template.files.destroy', [$this->tenant, $this->event, BrandFile::TicketBackground->value]))
            ->assertRedirect();

        $this->assertNull(
            $this->tenant->asCurrent(fn () => $this->event->fresh()->getFirstMedia(BrandFile::TicketBackground->value)),
        );
    }

    public function test_un_membre_sans_la_permission_de_modifier_l_evenement_est_refuse(): void
    {
        $member = $this->memberWith(TenantPermission::EventsView);

        $this->actingAs($member)
            ->get(route('tenants.events.ticket-template.edit', [$this->tenant, $this->event]))
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('tenants.events.ticket-template.update', [$this->tenant, $this->event]), [
                'ticket_template_enabled' => true,
                'ticket_model' => 'elegant',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(
                route('tenants.events.ticket-template.files.store', [$this->tenant, $this->event, BrandFile::TicketBackground->value]),
                ['file' => UploadedFile::fake()->image('evenement.jpg', 1200, 400)],
            )
            ->assertForbidden();
    }

    public function test_un_membre_d_une_autre_organisation_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.events.ticket-template.edit', [$this->tenant, $this->event]))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->patch(route('tenants.events.ticket-template.update', [$this->tenant, $this->event]), [
                'ticket_template_enabled' => true,
                'ticket_model' => 'elegant',
            ])
            ->assertNotFound();
    }
}
