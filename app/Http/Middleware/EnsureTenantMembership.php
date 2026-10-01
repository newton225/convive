<?php

namespace App\Http\Middleware;

use App\Actions\Tenants\ManageSupportAccess;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resout le locataire du back-office par son slug (segment `{tenant}` ou `{current_tenant}`
 * de l'URL), verifie l'appartenance, puis initialise la tenancy : c'est ce dernier appel qui
 * bascule la connexion de base de donnees par defaut vers celle du locataire (voir CLAUDE.md,
 * « Multi-locataire »). Prioritaire, prepose avant `SubstituteBindings` dans `bootstrap/app.php`,
 * pour que les sous-modeles ({event}, {unit}, {profile}...) se resolvent deja dans la bonne
 * base.
 */
class EnsureTenantMembership
{
    /**
     * Attribut de requete qui porte l'acces de support sous lequel la page est consultee.
     */
    public const SupportAccessAttribute = 'support_access_grant';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$user, $tenant] = [$request->user(), $this->tenant($request)];

        // Un locataire tiers ne doit pas apprendre que la ressource existe : 404, jamais 403.
        abort_if(! $user || ! $tenant, 404);

        // Acces de support (README section 3) : un compte de l'equipe Convive qui n'est pas membre
        // n'entre que si un Proprietaire lui a ouvert un acces encore en cours. Sans cela, meme
        // reponse qu'a un locataire tiers.
        $member = $user->belongsToTenant($tenant);
        $supportAccess = $member ? null : $user->supportAccessTo($tenant);

        abort_if(! $member && $supportAccess === null, 404);

        // Lecture seule, quelles que soient les permissions verifiees plus loin : aucune requete
        // qui modifie ne passe sous un acces de support.
        abort_if($supportAccess !== null && ! $request->isMethodSafe(), 403, __('support_access.errors.read_only'));

        tenancy()->initialize($tenant);

        if ($supportAccess !== null) {
            $request->attributes->set(self::SupportAccessAttribute, $supportAccess);
        } elseif ($request->route('current_tenant') && ! $user->isCurrentTenant($tenant)) {
            $user->switchTenant($tenant);
        }

        // `finally` : une exception levee plus loin dans la pile (controleur, validation...) ne
        // doit pas laisser `database.default` bascule sur le locataire. Sans cette garantie, un
        // seul test en echec suffit a corrompre la connexion centrale pour tous ceux qui suivent
        // dans le meme processus (voir CLAUDE.md, « Multi-locataire »).
        try {
            $response = $next($request);

            // Chaque page effectivement servie est journalisee, tant que la base de
            // l'organisation est encore ouverte : c'est son journal qui la garde. Un rechargement
            // partiel d'Inertia rafraichit une page deja comptee.
            if ($supportAccess !== null && $response->isSuccessful() && ! $request->headers->has('X-Inertia-Partial-Data')) {
                app(ManageSupportAccess::class)->recordView($supportAccess, $request->route()?->getName());
            }

            return $response;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Get the tenant associated with the request.
     */
    protected function tenant(Request $request): ?Tenant
    {
        $tenant = $request->route('current_tenant') ?? $request->route('tenant');

        if (is_string($tenant)) {
            $tenant = Tenant::where('slug', $tenant)->first();
        }

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
