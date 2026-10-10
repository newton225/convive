<?php

namespace Tests\Security;

use App\Actions\Scan\ScanTicket;
use App\Actions\Seating\AssignTable;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatingTable;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test par mutation du jeton QR (SECURITY.md C2) : un faussaire qui a vu un billet valide le modifie de
 * toutes les facons imaginables, octet par octet. Aucune mutation ne doit etre acceptee, et le billet
 * d'origine doit rester accepte une fois (preuve que le test ne refuse pas tout par defaut).
 */
class TicketTokenMutationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $agent;

    private Event $event;

    private Ticket $ticket;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->agent, 'Association Convive');

        $this->tenant->asCurrent(function () {
            $this->event = Event::factory()->open()->create(['starts_at' => now()->addDays(10)]);
            SeatingTable::factory()->create(['event_id' => $this->event->id, 'capacity' => 4]);
            $registration = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'party_size' => 1]);
            app(AssignTable::class)->handle($registration);
            $this->ticket = app(IssueTicket::class)->handle($registration);
            $this->token = $this->ticket->signedToken();
            $this->event = $this->event->fresh();
        });
    }

    private function decode(string $part): string|false
    {
        return base64_decode(strtr($part, '-_', '+/'), false);
    }

    private function scan(string $token): ScanResult
    {
        return $this->tenant->asCurrent(
            fn () => app(ScanTicket::class)->handle($this->event, $token, $this->agent)['result'],
        );
    }

    public function test_chaque_octet_modifie_du_jeton_est_refuse(): void
    {
        [$payload, $signature] = explode('.', $this->token);
        $accepted = [];

        // Chaque caractere de la charge utile et de la signature est remplace, un a un.
        foreach ([[0, $payload], [1, $signature]] as [$part, $value]) {
            for ($index = 0; $index < strlen($value); $index++) {
                $replacement = $value[$index] === 'A' ? 'B' : 'A';
                $mutated = substr($value, 0, $index).$replacement.substr($value, $index + 1);

                // Les derniers caracteres d'un base64 portent des bits de bourrage : les changer ne
                // change pas les octets decodes. Ce n'est pas une modification du jeton.
                if ($this->decode($mutated) === $this->decode($value)) {
                    continue;
                }

                $token = $part === 0 ? "{$mutated}.{$signature}" : "{$payload}.{$mutated}";

                if ($this->scan($token) === ScanResult::Accepted) {
                    $accepted[] = "partie {$part}, position {$index}";
                }
            }
        }

        $this->assertSame([], $accepted, "Jetons modifies acceptes :\n".implode("\n", $accepted));
    }

    public function test_le_billet_d_origine_reste_accepte_une_fois_puis_signale(): void
    {
        $this->assertSame(ScanResult::Accepted, $this->scan($this->token));
        $this->assertSame(ScanResult::AlreadyScanned, $this->scan($this->token));
    }

    public function test_les_formes_malformees_sont_toutes_refusees_sans_erreur(): void
    {
        [$payload, $signature] = explode('.', $this->token);

        $forms = [
            '',
            '.',
            '..',
            'a.b.c',
            $payload,
            $payload.'.',
            '.'.$signature,
            $signature.'.'.$payload,
            "{$payload}.{$signature}.extra",
            strtolower($payload).'.'.strtolower($signature),
            strtoupper($payload).'.'.strtoupper($signature),
            "{$payload}.{$signature}\0",
            "{$payload}\n.{$signature}",
            str_repeat('A', 100000).'.'.str_repeat('B', 100000),
            '{"event_id":1}.'.$signature,
            base64_encode('{"tenant_id":1,"event_id":1,"registration_id":1,"nonce":"x","not_after":null}').'.'.$signature,
            "{$payload}.".base64_encode(str_repeat("\0", 64)),
            "{$payload}.".rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '='),
        ];

        foreach ($forms as $index => $form) {
            $this->assertSame(ScanResult::Refused, $this->scan($form), "Forme malformee n°{$index} non refusee");
        }
    }

    public function test_un_jeton_valide_signe_par_une_autre_cle_est_refuse(): void
    {
        // Un faussaire signe, avec SA cle, une charge identique a celle du vrai billet.
        [$payload] = explode('.', $this->token);
        $forger = sodium_crypto_sign_keypair();
        $json = base64_decode(strtr($payload, '-_', '+/'));
        $signature = sodium_crypto_sign_detached($json, sodium_crypto_sign_secretkey($forger));
        $forged = $payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $this->assertSame(ScanResult::Refused, $this->scan($forged));
    }

    public function test_une_echeance_repoussee_dans_la_charge_invalide_la_signature(): void
    {
        [$payload, $signature] = explode('.', $this->token);
        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $data['not_after'] = time() + 86400 * 3650;
        $reencoded = rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        $this->assertSame(ScanResult::Refused, $this->scan("{$reencoded}.{$signature}"));
    }

    public function test_un_billet_d_un_autre_evenement_signe_correctement_est_refuse_ici(): void
    {
        $other = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create(['starts_at' => now()->addDays(10)]);
            SeatingTable::factory()->create(['event_id' => $event->id, 'capacity' => 4]);
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'party_size' => 1]);
            app(AssignTable::class)->handle($registration);

            return app(IssueTicket::class)->handle($registration)->signedToken();
        });

        $this->assertSame(ScanResult::Refused, $this->scan($other));
    }
}
