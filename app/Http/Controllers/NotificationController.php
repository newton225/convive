<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * La cloche (README section 5) : ouvrir une alerte, ou tout marquer comme lu. Par utilisateur, pas
 * par organisation : une alerte d'un autre espace du meme membre reste ouvrable d'ici.
 */
class NotificationController extends Controller
{
    /**
     * Mark the alert as read and send the member to the screen it is about.
     *
     * Une alerte qui n'est pas la sienne n'existe pas : `findOrFail` sur la relation de
     * l'utilisateur renvoie 404, jamais 403.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $alert = $request->user()->notifications()->findOrFail($notification);

        $alert->markAsRead();

        $url = $alert->data['url'] ?? null;

        // Le lien est construit par le serveur a l'envoi, mais ne redirige jamais hors du site
        // meme si une donnee stockee etait alteree : chemin relatif seulement.
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : back();
    }

    /**
     * Mark every unread alert of the member as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
