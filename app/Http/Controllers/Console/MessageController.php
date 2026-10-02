<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Console\MessageJournal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * L'ecran « Envois » de la console (README section 3) : les courriels et messages WhatsApp partis,
 * par canal et par type, et les canaux qui n'envoient pas encore reellement. La zone `messages` de
 * la route l'ouvre aux Fondateurs et au Support, qui repond a « le message est-il parti ? ».
 */
class MessageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('console/messages', [
            'isSample' => false,
            ...MessageJournal::overview(),
        ]);
    }
}
