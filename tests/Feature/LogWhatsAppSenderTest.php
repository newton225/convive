<?php

namespace Tests\Feature;

use App\Support\LogWhatsAppSender;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * SECURITY.md M7 : en production, le journal applicatif ne porte ni le numero ni le lien signe
 * que contient le message.
 */
class LogWhatsAppSenderTest extends TestCase
{
    public function test_hors_production_le_message_complet_est_journalise(): void
    {
        Log::shouldReceive('info')->once()->with('WhatsApp (simule)', [
            'to' => '+225 07 00 00 00 00',
            'message' => 'Votre carte : https://exemple.test/e/abc?signature=secret',
        ]);

        (new LogWhatsAppSender)->send('+225 07 00 00 00 00', 'Votre carte : https://exemple.test/e/abc?signature=secret');
    }

    public function test_en_production_ni_le_numero_ni_le_lien_ne_sont_journalises(): void
    {
        $this->app['env'] = 'production';

        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) {
            return $message === 'WhatsApp (simule)'
                && $context['to'] === str_repeat('*', 17).'00'
                && $context['length'] === mb_strlen('Votre carte : https://exemple.test/e/abc?signature=secret')
                && ! array_key_exists('message', $context);
        });

        (new LogWhatsAppSender)->send('+225 07 00 00 00 00', 'Votre carte : https://exemple.test/e/abc?signature=secret');
    }
}
