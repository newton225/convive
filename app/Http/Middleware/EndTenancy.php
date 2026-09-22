<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rend la main a la base centrale une fois la reponse construite. `InitializeTenancyBySubdomain`
 * (stancl/tenancy) initialise la tenancy mais ne la termine jamais elle meme : c'est a
 * l'application de le faire, comme le fait `EnsureTenantMembership` pour le back-office. Sans
 * cela, `database.default` reste bascule sur le locataire au dela de cette requete, un risque
 * reel en file d'attente ou sous Octane, et une source de faux positifs en test.
 *
 * La coupure se fait dans `terminate()`, jamais dans un `finally` autour de `handle()`.
 * `StartSession` (groupe `web`) est plus « exterieur » que ce middleware dans la pile triee : sa
 * sauvegarde de session s'execute apres que `handle()` ait rendu la main, donc apres un `finally`
 * pose ici. `SESSION_DRIVER=database` sans `session.connection` explicite suit `database.default`,
 * lui meme bascule sur le locataire pendant la requete : couper la connexion `tenant` avant cette
 * sauvegarde fait echouer toute premiere visite du lien public d'un evenement (« Database
 * connection [tenant] not configured », decouvert le 2026-09-22). `terminate()` s'execute apres
 * que Laravel ait entierement deroule `handle()`, session sauvegardee comprise, quel que soit le
 * SAPI : la coupure arrive alors, jamais avant.
 */
class EndTenancy
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
