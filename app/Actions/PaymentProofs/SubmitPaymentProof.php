<?php

namespace App\Actions\PaymentProofs;

use App\Actions\Notifications\SendAlert;
use App\Enums\EventStatus;
use App\Enums\NotificationType;
use App\Enums\RegistrationStatus;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Support\PerceptualHash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

/**
 * Depot d'une preuve de paiement (README ecran 6, 2.1), etape 6 de « Ordre de construction ».
 *
 * Fait passer l'inscription de `Held` a `ProofSubmitted`. Refuse toute soumission hors de cet
 * etat : une reservation deja confirmee, expiree, ou dont le decompte est ecoule sans que la
 * tache planifiee ne soit encore passee (README 2.1, « refuse une preuve deposee apres
 * expiration du decompte »).
 */
class SubmitPaymentProof
{
    /**
     * Attempt to record a payment proof for the given registration.
     *
     * @param  array{payment_account_id: int, channel: string, reference: ?string, guest_note: ?string}  $data
     * @return PaymentProof|null null quand la soumission est refusee : l'appelant decide de la
     *                           reponse (l'etat de l'inscription, deja a jour, la reflete).
     */
    public function handle(Registration $registration, array $data, UploadedFile $receipt, string $idempotencyKey): ?PaymentProof
    {
        // Idempotence (CLAUDE.md, « Securite ») : un double clic ou un rejeu reseau renvoie la
        // preuve deja creee plutot que d'en creer une seconde.
        $existing = PaymentProof::where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        // Un evenement cloture n'accepte plus de preuve (decision du 2026-10-08), meme pendant un
        // decompte qui court encore.
        if ($registration->status !== RegistrationStatus::Held || $registration->holdHasExpired()
            || $registration->event->status === EventStatus::Closed) {
            return null;
        }

        $reencodedPath = $this->reencode($receipt);
        $reencodedContents = file_get_contents($reencodedPath);

        if ($reencodedContents === false) {
            throw new RuntimeException('Capture reencodee illisible.');
        }

        $perceptualHash = PerceptualHash::forImageContents($reencodedContents);

        try {
            $proof = DB::transaction(function () use ($registration, $data, $reencodedPath, $perceptualHash, $idempotencyKey, $receipt) {
                $proof = PaymentProof::create([
                    'registration_id' => $registration->id,
                    'payment_account_id' => $data['payment_account_id'],
                    'channel' => $data['channel'],
                    'reference' => $data['reference'],
                    'guest_note' => $data['guest_note'],
                    'perceptual_hash' => $perceptualHash,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $proof->addMedia($reencodedPath)
                    ->usingFileName(Str::uuid()->toString().'.'.$this->extension($receipt))
                    ->usingName('receipt')
                    ->toMediaCollection(PaymentProof::ReceiptCollection);

                $registration->update(['status' => RegistrationStatus::ProofSubmitted]);

                return $proof;
            });
        } finally {
            if (is_file($reencodedPath)) {
                unlink($reencodedPath);
            }
        }

        // Apres la transaction et apres le retour anticipe de l'idempotence : un rejeu reseau
        // de la meme preuve ne previent pas deux fois l'equipe.
        app(SendAlert::class)->toTenantMembers(
            NotificationType::ProofReceived,
            ['name' => $registration->name, 'event' => $registration->event->name],
            route('tenants.events.proofs.index', [Tenant::current(), $registration->event], absolute: false),
        );

        return $proof;
    }

    /**
     * Re-encode the upload to a temporary file, dropping every metadata block (CLAUDE.md,
     * « Fichiers deposes » ; SECURITY.md H1) : une capture d'ecran porte rarement des donnees
     * sensibles dans ses metadonnees, mais le reencodage reste ce qui detruit un polyglotte
     * image plus script, quel que soit le contenu declare.
     *
     * Moteur GD force explicitement (SECURITY.md H1, « delegue Ghostscript ») : GD ne s'appuie
     * jamais sur ImageMagick ni sur son delegue Ghostscript, la faille classique de conversion
     * d'image. Explicite plutot que laisse au hasard de l'extension `imagick` presente ou non
     * sur le serveur : Spatie\Image choisirait Imagick des qu'elle est installee.
     */
    private function reencode(UploadedFile $upload): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'proof').'.'.$this->extension($upload);

        Image::useImageDriver(ImageDriver::Gd)
            ->loadFile($upload->getRealPath())
            ->save($destination);

        return $destination;
    }

    private function extension(UploadedFile $upload): string
    {
        return match ($upload->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
