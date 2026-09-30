<?php

namespace Database\Seeders;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use Closure;
use Database\Factories\PaymentProofFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Preuves saines et preuves douteuses (README 2.9) : reference dupliquee, precision de l'invite,
 * capture deja vue. S'appuie sur l'evenement ouvert de `EventSeeder` et ses inscriptions en
 * attente de verification (`RegistrationSeeder::seedOpenEvent()`).
 */
class PaymentProofSeeder extends Seeder
{
    /**
     * Reference partagee par les deux preuves du scenario de doublon, et marqueur
     * d'idempotence (voir `alreadySeeded()`).
     */
    private const DuplicateReference = 'CI7788STV42B';

    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant) {
            return;
        }

        $tenant->run(function () {
            $event = Event::where('name', "Diner de Noel de l'Association")->first();
            $account = $event?->paymentAccounts()->first();

            if (! $event || ! $account) {
                return;
            }

            if (! $this->alreadySeeded($event)) {
                $this->healthyProofs($event, $account);
                $this->duplicateReference($event, $account);
                $this->guestNote($event, $account);
                $this->duplicateCapture($event, $account);
            }

            // Hors du garde : rattrape aussi les preuves semees avant que ce seeder ne joigne une
            // capture, sans rien recreer.
            $this->attachMissingCaptures($event);
        });
    }

    /**
     * Determine whether this seeder already ran for this event, using its dedicated
     * reference as the marker : plus precis qu'un garde sur la table entiere, dont cette base
     * de developpement porte aussi des preuves deposees a la main en testant l'interface.
     */
    private function alreadySeeded(Event $event): bool
    {
        return PaymentProof::where('reference', self::DuplicateReference)
            ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->exists();
    }

    /**
     * Une preuve saine pour chacune des inscriptions deja en attente de verification.
     */
    private function healthyProofs(Event $event, PaymentAccount $account): void
    {
        Registration::where('event_id', $event->id)
            ->where('status', RegistrationStatus::ProofSubmitted)
            ->get()
            ->each(fn (Registration $registration) => $this->proof($registration, $account));
    }

    /**
     * Deux preuves portant la meme reference de transaction (README 2.9) : le meme versement
     * declare deux fois, ou deux invites qui partagent une preuve.
     */
    private function duplicateReference(Event $event, PaymentAccount $account): void
    {
        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withReference(self::DuplicateReference));
        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withReference(self::DuplicateReference));
    }

    /**
     * Une precision laissee par l'invite, que le tresorier doit lire avant de valider.
     */
    private function guestNote(Event $event, PaymentAccount $account): void
    {
        PaymentProof::factory()->create([
            'registration_id' => $this->pendingRegistration($event)->id,
            'payment_account_id' => $account->id,
            'guest_note' => 'Paye en deux fois : le reste part demain depuis le numero de mon epoux.',
        ]);
    }

    /**
     * Deux preuves dont la capture du recu hache au meme motif (README 2.9) : la meme image
     * envoyee deux fois.
     */
    private function duplicateCapture(Event $event, PaymentAccount $account): void
    {
        $hash = str_repeat('a1', 8);

        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withPerceptualHash($hash));
        $this->proof($this->pendingRegistration($event), $account, fn ($proof) => $proof->withPerceptualHash($hash));
    }

    /**
     * @param  (Closure(PaymentProofFactory): PaymentProofFactory)|null  $state
     */
    private function proof(Registration $registration, PaymentAccount $account, ?Closure $state = null): void
    {
        $factory = $state ? $state(PaymentProof::factory()) : PaymentProof::factory();

        $factory->create([
            'registration_id' => $registration->id,
            'payment_account_id' => $account->id,
        ]);
    }

    /**
     * Joint une capture de recu simulee a chaque preuve de l'evenement qui n'en a pas : une preuve
     * reelle en porte toujours une (le depot l'exige), et sans elle le lien « Ouvrir le recu » de la
     * file de preuves et de la base d'inscrits n'a rien a montrer.
     *
     * Les deux preuves du scenario « capture deja vue » (meme empreinte) recoivent la meme image,
     * dessinee d'apres la premiere : c'est tout le scenario. Les empreintes ne sont pas
     * recalculees : sur des recus de meme mise en page, l'empreinte par moyenne signalerait toutes
     * les preuves semees comme une meme capture.
     */
    private function attachMissingCaptures(Event $event): void
    {
        /** @var array<string, string> $imagesByHash */
        $imagesByHash = [];

        PaymentProof::with('registration', 'paymentAccount')
            ->whereHas('registration', fn ($query) => $query->where('event_id', $event->id))
            ->whereDoesntHave('media')
            ->orderBy('id')
            ->get()
            ->each(function (PaymentProof $proof) use (&$imagesByHash) {
                $image = $proof->perceptual_hash !== null
                    ? $imagesByHash[$proof->perceptual_hash] ??= $this->receiptImage($proof)
                    : $this->receiptImage($proof);

                $path = tempnam(sys_get_temp_dir(), 'seed-receipt').'.png';
                file_put_contents($path, $image);

                $proof->addMedia($path)
                    ->usingFileName(Str::uuid()->toString().'.png')
                    ->usingName('receipt')
                    ->toMediaCollection(PaymentProof::ReceiptCollection);
            });
    }

    /**
     * Draw a plausible mobile money confirmation screen for the proof, as PNG bytes.
     *
     * Police bitmap de GD agrandie : aucune police vectorielle n'est garantie sur le poste, et la
     * lisibilite suffit pour une donnee de demonstration.
     */
    private function receiptImage(PaymentProof $proof): string
    {
        $width = 540;
        $height = 960;
        $image = imagecreatetruecolor($width, $height);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $header = (int) imagecolorallocate($image, 29, 200, 255);
        $ink = (int) imagecolorallocate($image, 20, 24, 32);
        $muted = (int) imagecolorallocate($image, 110, 116, 128);
        $line = (int) imagecolorallocate($image, 226, 230, 236);

        imagefill($image, 0, 0, $white);
        imagefilledrectangle($image, 0, 0, $width, 200, $header);

        $amount = number_format($proof->registration->amount_due, 0, ',', ' ').' F CFA';

        $this->text($image, $proof->channel->label(), 40, 50, 3, $white);
        $this->text($image, 'Paiement envoye', 40, 120, 3, $white);
        $this->text($image, $amount, 40, 260, 4, $ink);
        $this->text($image, 'A', 40, 380, 2, $muted);
        $this->text($image, Str::ascii($proof->paymentAccount->label), 40, 420, 2, $ink);
        imageline($image, 40, 490, $width - 40, 490, $line);
        $this->text($image, 'Reference', 40, 520, 2, $muted);
        $this->text($image, (string) $proof->reference, 40, 560, 2, $ink);
        imageline($image, 40, 630, $width - 40, 630, $line);
        $this->text($image, 'Date', 40, 660, 2, $muted);
        $this->text($image, $proof->created_at?->format('d/m/Y H:i') ?? '', 40, 700, 2, $ink);
        $this->text($image, 'Frais : 0 F CFA', 40, 800, 2, $muted);

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function text(\GdImage $image, string $text, int $x, int $y, int $scale, int $color): void
    {
        // Fond du texte pris sous son point d'ancrage : les zones de l'ecran sont unies, et la
        // copie agrandie ne gere pas la transparence d'une image en couleurs vraies.
        $glyphs = imagecreatetruecolor(max(1, strlen($text) * 9), 16);
        $under = imagecolorsforindex($image, (int) imagecolorat($image, $x, $y));
        imagefill($glyphs, 0, 0, (int) imagecolorallocate($glyphs, $under['red'], $under['green'], $under['blue']));

        $rgb = imagecolorsforindex($image, $color);
        imagestring($glyphs, 5, 0, 0, $text, (int) imagecolorallocate($glyphs, $rgb['red'], $rgb['green'], $rgb['blue']));

        imagecopyresized($image, $glyphs, $x, $y, 0, 0, imagesx($glyphs) * $scale, 16 * $scale, imagesx($glyphs), 16);
        imagedestroy($glyphs);
    }

    /**
     * Une inscription dediee a un scenario de preuve douteuse, distincte de celles deja
     * seminees par `RegistrationSeeder` pour ne pas dependre de leur nombre exact.
     */
    private function pendingRegistration(Event $event): Registration
    {
        return Registration::factory()->proofSubmitted()->create([
            'event_id' => $event->id,
            'amount_due' => $event->amountFor(0),
        ]);
    }
}
