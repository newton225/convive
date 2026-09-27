<?php

namespace Tests\Feature\Mail;

use App\Actions\Tenants\CreateTenant;
use App\Mail\GuestNotificationMail;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\InvitationCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

/**
 * Les emails du parcours invite (carte d'invitation, rappels, README 2.7, etape 8) portent les
 * couleurs de marque du locataire (CLAUDE.md, « Les couleurs de marque du locataire
 * s'appliquent uniquement au parcours invite »), pas le gabarit texte par defaut des
 * notifications Laravel.
 */
class GuestNotificationMailTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function publishedEvent(Tenant $tenant): Event
    {
        $tenant->update(['subdomain' => 'convive-ci']);

        return $tenant->asCurrent(fn () => Event::factory()->published()->create([
            'primary_color' => '#123456',
            'secondary_color' => '#abcdef',
        ]));
    }

    public function test_la_carte_d_invitation_porte_les_couleurs_et_le_nom_de_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->brandingOrCreate()->fill(['display_name' => 'Convive Abidjan'])->save();
        $event = $this->publishedEvent($tenant);

        $mail = $tenant->asCurrent(function () use ($event) {
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'email' => 'invite@example.com',
            ]);

            $notification = new InvitationCard($registration, 'https://example.test/reprendre');

            return $notification->toMail(new AnonymousNotifiable);
        });

        $this->assertInstanceOf(GuestNotificationMail::class, $mail);
        $this->assertSame('Convive Abidjan', $mail->organisationName);
        $this->assertSame('#123456', $mail->primaryColor);
        $this->assertSame('#abcdef', $mail->secondaryColor);
    }

    public function test_le_rendu_html_contient_la_couleur_primaire_en_style_en_ligne(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
        ]));

        $mail = new GuestNotificationMail(
            organisationName: 'Convive Abidjan',
            primaryColor: '#123456',
            secondaryColor: '#abcdef',
            subjectLine: 'Sujet de test',
            lines: ['Une ligne de contenu.'],
            actionText: 'Voir le billet',
            actionUrl: 'https://example.test/billet',
        );

        $html = $mail->render();

        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('#abcdef', $html);
        $this->assertStringContainsString('Convive Abidjan', $html);
        $this->assertStringContainsString('Voir le billet', $html);
        $this->assertStringContainsString('https://example.test/billet', $html);
        // Aucune balise <style> externe : uniquement des styles en ligne (clients de messagerie).
        $this->assertStringNotContainsString('<style', $html);
    }
}
