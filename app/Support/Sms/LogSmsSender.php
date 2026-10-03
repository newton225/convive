<?php

namespace App\Support\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Sans service SMS configure, les messages sont ecrits dans le journal applicatif, comme
 * `LogWhatsAppSender`. En production ni le numero ni le message n'y figurent (SECURITY.md M7) :
 * le message porte un code de verification.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        if (app()->isProduction()) {
            Log::info('SMS (simule)', ['to' => str_repeat('*', max(0, mb_strlen($to) - 2)).mb_substr($to, -2), 'length' => mb_strlen($message)]);

            return;
        }

        Log::info('SMS (simule)', ['to' => $to, 'message' => $message]);
    }

    public function delivers(): bool
    {
        return false;
    }
}
