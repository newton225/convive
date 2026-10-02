<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTenantSlugs;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Support\Subdomain;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\InvalidatesResolverCache;
use Stancl\Tenancy\Database\Concerns\TenantRun;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Events;

/**
 * L'organisation, et le locataire au sens de stancl/tenancy : un enregistrement de ce modele
 * decrit une base de donnees separee, physiquement isolee des autres locataires. Ce modele
 * lui-meme vit dans la base centrale (voir CLAUDE.md, section « Multi-locataire »).
 *
 * Ce n'est deliberement pas le modele `Tenant` fourni par le paquet : celui-ci ajoute la
 * generation d'UUID et le stockage des attributs personnalises dans une colonne `data` JSON,
 * deux choix qui ne correspondent pas a ce que le reste du produit a deja construit (cle
 * entiere auto-incrementee, colonnes reelles). L'implementation du contrat suffit.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $deletion_scheduled_at
 * @property Carbon|null $trial_started_at
 * @property Carbon|null $trial_ends_at
 * @property-read Domain|null $domain
 * @property-read Collection<int, TenantInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read TenantBranding|null $branding
 * @property-read TenantSuspension|null $suspension
 * @property-read TenantUsage|null $usage
 * @property-read TenantLimit|null $limits
 */
#[Fillable(['name', 'slug', 'is_personal', 'subdomain'])]
#[Hidden(['tenancy_db_name', 'tenancy_db_username', 'tenancy_db_password'])]
class Tenant extends Model implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use CentralConnection,
        GeneratesUniqueTenantSlugs,
        HasDatabase,
        HasFactory,
        HasInternalKeys,
        InvalidatesResolverCache,
        SoftDeletes,
        TenantRun;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = static::generateUniqueTenantSlug($tenant->name);
            }

            // Tout espace neuf s'ouvre a l'essai (README section 3). Sans duree configuree,
            // l'essai n'a pas de date de fin.
            $tenant->trial_started_at ??= Carbon::now();

            if ($tenant->trial_ends_at === null && is_numeric(config('convive.trial.days'))) {
                $tenant->trial_ends_at = Carbon::now()->addDays((int) config('convive.trial.days'));
            }
        });

        static::updating(function (Tenant $tenant) {
            // `TenantCreated` (voir `TenancyServiceProvider`) est envoye de facon synchrone
            // depuis `performInsert()`, avant que Eloquent n'ait resynchronise `$original` sur
            // l'insertion : un `save()` imbrique declenche ici, comme celui de
            // `DatabaseConfig::makeCredentials()` qui ecrit les identifiants de connexion du
            // locataire juste apres sa creation, voit alors `isDirty('name')` a vrai pour
            // toutes les colonnes deja posees, y compris `slug` lui-meme. Exiger que `slug` ne
            // soit pas simultanement modifie ecarte cette fausse alerte sans dependre de cet
            // ordonnancement : une vraie demande de renommage ne touche jamais `slug` a la
            // main, elle laisse cette methode le deriver.
            if ($tenant->isDirty('name') && ! $tenant->isDirty('slug')) {
                $tenant->slug = static::generateUniqueTenantSlug($tenant->name, $tenant->id);
            }
        });
    }

    public function getTenantKeyName(): string
    {
        return 'id';
    }

    public function getTenantKey(): int
    {
        return (int) $this->getAttribute('id');
    }

    /**
     * Bind Eloquent's lifecycle to the events stancl/tenancy listens on for provisioning
     * (creating the physical database, running its migrations). Ce modele n'etend pas la
     * classe de base du paquet, cette table doit donc etre redeclaree explicitement, sinon
     * rien ne se declenche a la creation d'un locataire.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'saving' => Events\SavingTenant::class,
        'saved' => Events\TenantSaved::class,
        'creating' => Events\CreatingTenant::class,
        'created' => Events\TenantCreated::class,
        'updating' => Events\UpdatingTenant::class,
        'updated' => Events\TenantUpdated::class,
        'deleting' => Events\DeletingTenant::class,
        'deleted' => Events\TenantDeleted::class,
    ];

    /**
     * Get the tenant resolved for the current request, if any.
     */
    public static function current(): ?self
    {
        /** @var self|null $tenant */
        $tenant = tenant();

        return $tenant;
    }

    /**
     * Run the callback with this tenant as the current one.
     *
     * Alias de `run()` (fourni par stancl/tenancy) : conserve pour ne pas renommer tous les
     * appels ecrits avant l'adoption du paquet.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public function asCurrent(callable $callback): mixed
    {
        return $this->run($callback);
    }

    /**
     * Override de `TenantRun::run()` (stancl/tenancy) : la version du paquet ne revient au
     * contexte precedent qu'apres un retour normal du callback, jamais dans un `finally`. Un
     * callback qui leve laisse alors `database.default` bascule sur ce locataire pour tout le
     * reste du processus PHP, ce qui corrompt silencieusement le test (ou la requete, sous
     * Octane) suivant : d'ou la meme garantie que celle deja posee dans `EnsureTenantMembership`.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        $originalTenant = tenant();

        tenancy()->initialize($this);

        try {
            return $callback($this);
        } finally {
            if ($originalTenant) {
                tenancy()->initialize($originalTenant);
            } else {
                tenancy()->end();
            }
        }
    }

    /**
     * All domains of this tenant. `Stancl\Tenancy\Resolvers\DomainTenantResolver` requires this
     * exact relation name (plural, `HasMany`) to identify a tenant by subdomain, meme si un
     * locataire de Convive n'en porte jamais qu'un seul (voir `domain()` ci-dessous).
     *
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    /**
     * Get the primary domain of this tenant.
     *
     * @return HasOne<Domain, $this>
     */
    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class);
    }

    /**
     * Get the subdomain label of this tenant, if it has one.
     *
     * `stancl/tenancy` identifie le locataire par une ligne dans `domains`, pas par une
     * colonne sur `tenants`. Cet accesseur et son mutateur gardent l'API `$tenant->subdomain`
     * et `$tenant->update(['subdomain' => ...])` inchangee pour le reste du code : seule cette
     * paire de methodes sait que la valeur vit ailleurs.
     */
    public function getSubdomainAttribute(): ?string
    {
        return $this->domain?->domain;
    }

    /**
     * Write the subdomain through to the tenant's primary domain row.
     *
     * N'est jamais appele a la creation : un locataire nait sans sous-domaine, il en recoit un
     * plus tard via le formulaire d'organisation, sur une instance deja persistee.
     */
    public function setSubdomainAttribute(?string $value): void
    {
        if ($value === null) {
            $this->domain?->delete();
            $this->unsetRelation('domain');

            return;
        }

        $normalised = Subdomain::normalise($value);

        if ($this->domain) {
            $this->domain->update(['domain' => $normalised]);
        } else {
            $this->domain()->create(['domain' => $normalised]);
        }

        $this->unsetRelation('domain');
    }

    /**
     * Get the tenant owner, that is the member carrying the system owner profile.
     */
    public function owner(): ?User
    {
        return $this->members()->get()->first(
            fn (User $member) => $member->ownsTenant($this),
        );
    }

    /**
     * Get the members carrying the system owner profile.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function owners(): \Illuminate\Support\Collection
    {
        return $this->members()->get()->filter(
            fn (User $member) => $member->ownsTenant($this),
        )->values();
    }

    /**
     * Add the given user to the tenant with the given profile.
     *
     * L'appartenance (centrale) et l'affectation du profil (dans la base du locataire) ne
     * partagent pas de connexion : chacune est ecrite dans la sienne.
     */
    public function addMember(User $user, Profile $profile): Membership
    {
        $membership = $this->memberships()->firstOrCreate(['user_id' => $user->id]);

        $this->run(fn () => $user->assignTenantProfile($this, $profile));

        return $membership;
    }

    /**
     * Get the tenant's legal identity and brand.
     *
     * @return HasOne<TenantBranding, $this>
     */
    public function branding(): HasOne
    {
        return $this->hasOne(TenantBranding::class);
    }

    /**
     * Get the tenant's subscription, absent for an organisation still on the default plan.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get the invoices issued to this tenant.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the plan the tenant runs on : its subscription's, the trial plan while it is on trial,
     * or the default one (README section 3). Une organisation n'a donc jamais besoin d'une ligne d'abonnement pour
     * etre limitee : le plan par defaut s'applique des sa creation.
     */
    public function plan(): Plan
    {
        return $this->subscription->plan
            ?? Plan::ensure($this->isOnTrial() ? self::trialPlanCode() : PlanCode::default());
    }

    /**
     * Determine whether the tenant is on its trial period (README section 3) : un essai ouvert, pas
     * encore echu, et aucun abonnement. Un abonnement l'emporte toujours sur l'essai ; un essai
     * echu rend la main au plan par defaut, sans rien supprimer.
     */
    public function isOnTrial(): bool
    {
        return config('convive.trial.enabled')
            && $this->trial_started_at !== null
            && ($this->trial_ends_at === null || $this->trial_ends_at->isFuture())
            && $this->subscription === null;
    }

    /**
     * Get the plan a tenant runs on during its trial.
     */
    public static function trialPlanCode(): PlanCode
    {
        return PlanCode::tryFrom((string) config('convive.trial.plan')) ?? PlanCode::default();
    }

    /**
     * Determine whether the tenant is suspended (README section 3) : automatiquement a J+10
     * d'impaye, ou a la main par l'editeur, avec un motif.
     */
    public function isSuspended(): bool
    {
        return $this->subscription?->status === SubscriptionStatus::Suspended
            || $this->isSuspendedByEditor();
    }

    /**
     * Determine whether the editor suspended the tenant by hand. Le paiement ne leve pas cette
     * suspension : seul l'editeur la leve.
     */
    public function isSuspendedByEditor(): bool
    {
        return $this->suspension !== null;
    }

    /**
     * Get the manual suspension in progress, if any.
     *
     * @return HasOne<TenantSuspension, $this>
     */
    public function suspension(): HasOne
    {
        return $this->hasOne(TenantSuspension::class)->whereNull('lifted_at');
    }

    /**
     * Get the limits set for this tenant alone, if any (README section 3).
     *
     * @return HasOne<TenantLimit, $this>
     */
    public function limits(): HasOne
    {
        return $this->hasOne(TenantLimit::class);
    }

    /**
     * Get the ceiling that applies to this tenant for the given quota (`max_active_events`...),
     * null when unlimited : la limite propre a l'organisation quand l'editeur en a regle une,
     * sinon celle de son plan.
     */
    public function limit(string $quota): ?int
    {
        return $this->limitUnder($this->plan(), $quota);
    }

    /**
     * Get the ceiling this tenant would have on the given plan : ses limites propres le suivent
     * d'un plan a l'autre.
     */
    public function limitUnder(Plan $plan, string $quota): ?int
    {
        return $this->limits?->getAttribute($quota) ?? $plan->getAttribute($quota);
    }

    /**
     * Get the consumption counters the console keeps for this tenant.
     *
     * @return HasOne<TenantUsage, $this>
     */
    public function usage(): HasOne
    {
        return $this->hasOne(TenantUsage::class);
    }

    /**
     * Get the tenant's branding, creating an empty one on first access.
     *
     * Relue apres creation : les valeurs par defaut des colonnes (`ticket_model`, elements du
     * billet...) sont posees par la base, et le modele tout juste insere ne les connait pas. Sans
     * cette relecture, le gabarit du billet d'un espace neuf levait une erreur 500.
     */
    public function brandingOrCreate(): TenantBranding
    {
        $branding = $this->branding()->firstOrCreate([]);

        return $branding->wasRecentlyCreated ? $branding->refresh() : $branding;
    }

    /**
     * Determine whether the tenant may publish a public registration link.
     *
     * L'espace personnel est un espace d'essai : on n'exige ses papiers qu'au moment ou il
     * publie, pas a l'inscription.
     */
    public function isReadyToPublish(): bool
    {
        return $this->subdomain !== null
            && ($this->branding?->isReadyToPublish() ?? false);
    }

    /**
     * Determine whether a public link of this tenant has ever been handed out.
     *
     * Des qu'un lien public existe, le sous-domaine se fige : le changer casserait des liens
     * deja entre les mains des invites.
     */
    public function hasPublishedEvent(): bool
    {
        return $this->run(fn () => Event::published()->exists());
    }

    /**
     * Get all members of this tenant.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_members', 'tenant_id', 'user_id')
            ->using(Membership::class)
            ->withTimestamps();
    }

    /**
     * Get the members whose profile holds the given permission in this tenant.
     *
     * C'est la permission qui decide, pas le nom du profil : un profil remanie par l'organisation
     * continue de recevoir ce qu'il peut traiter. Utilisable hors de toute tenancy (les
     * affectations sont lues sous `run()`), donc aussi depuis une tache planifiee.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function membersWithPermission(TenantPermission $permission, ?User $except = null): \Illuminate\Support\Collection
    {
        return $this->members()->get()
            ->reject(fn (User $member) => $except !== null && $member->is($except))
            ->filter(fn (User $member) => $member->hasTenantPermission($this, $permission))
            ->values();
    }

    /**
     * Get all memberships for this tenant.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this tenant.
     *
     * @return HasMany<TenantInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
            'deletion_scheduled_at' => 'datetime',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
