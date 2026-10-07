<?php

namespace App\Models;

use App\Enums\StarterProfile;
use App\Enums\TenantPermission;
use Carbon\CarbonImmutable;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Un profil est un role spatie/laravel-permission, dans la base du locataire qui le possede
 * (voir CLAUDE.md, « Multi-locataire ») : deux organisations peuvent avoir un profil du meme
 * nom sans que rien ne les relie, elles ne partagent litteralement aucune ligne.
 *
 * `members()` n'est plus une relation Eloquent standard : `User` vit dans la base centrale,
 * une jointure SQL entre deux bases separees est impossible. La table pivot
 * `model_has_profiles`, elle, vit ici ; c'est elle qui est interrogee directement, puis
 * `User` est resolu a part, sur sa propre connexion.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property bool $requires_two_factor
 * @property StarterProfile|null $starter
 * @property CarbonImmutable|null $hidden_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'guard_name'])]
class Profile extends Role
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * The name of the system profile every tenant keeps.
     */
    public const Owner = 'Proprietaire';

    /**
     * Get the users carrying this profile.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        $userIds = DB::table('model_has_profiles')
            ->where('profile_id', $this->id)
            ->where('model_type', (new User)->getMorphClass())
            ->pluck('model_id');

        return User::query()->whereIn('id', $userIds)->get();
    }

    /**
     * Get the number of users carrying this profile.
     */
    public function memberCount(): int
    {
        return DB::table('model_has_profiles')
            ->where('profile_id', $this->id)
            ->where('model_type', (new User)->getMorphClass())
            ->count();
    }

    /**
     * Override de `Role::users()` (spatie/laravel-permission) : la relation d'origine resout
     * la connexion de la table pivot `model_has_profiles` via le modele `User`, central (voir
     * CLAUDE.md, « Multi-locataire »), alors que cette table vit dans la base du locataire.
     * Sans ce correctif, `bootHasPermissions()` de Spatie (qui appelle `$model->users()->detach()`
     * a la suppression d'un profil) chercherait la table sur la mauvaise connexion.
     *
     * Seul `detach()` sans argument est appele par le paquet a ce point, et il n'interroge
     * jamais la table `users` elle-meme (voir `InteractsWithPivotTable::newPivotStatement()`) :
     * forcer la connexion de la requete pivot suffit, sans avoir a resoudre `User` autrement.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        $relation = parent::users();

        $relation->getQuery()->getQuery()->connection = $this->getConnection();

        return $relation;
    }

    /**
     * Determine whether this profile is the tenant's system owner profile.
     */
    public function isOwner(): bool
    {
        return $this->is_system && $this->name === self::Owner;
    }

    /**
     * Determine whether this profile is one of the starter profiles (Tresorier, Hotesse, Lecture).
     */
    public function isStarter(): bool
    {
        return $this->starter !== null;
    }

    /**
     * Get the starter profile this profile stands for, if any.
     */
    public function starter(): ?StarterProfile
    {
        return $this->starter;
    }

    /**
     * Determine whether the profile can neither be edited nor deleted : the system owner profile
     * and the starter profiles.
     */
    public function isLocked(): bool
    {
        return $this->is_system || $this->isStarter();
    }

    /**
     * Determine whether the organisation has hidden this profile from every choice.
     */
    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * Scope the query to the profiles offered when inviting a person or changing a member's
     * profile. Un profil masque n'est propose dans aucun formulaire ; qui le porte deja le garde.
     *
     * @param  Builder<Profile>  $query
     */
    public function scopeAssignable(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * Determine whether carriers of this profile must have two-factor authentication.
     *
     * Le profil systeme l'exige toujours : il detient tout le catalogue, y compris les
     * comptes de versement.
     */
    public function demandsTwoFactor(): bool
    {
        return $this->isOwner() || $this->requires_two_factor;
    }

    /**
     * Get the permission values held by this profile.
     *
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return $this->permissions->pluck('name')->all();
    }

    /**
     * Determine whether this profile holds the given permission.
     */
    public function holds(TenantPermission $permission): bool
    {
        return in_array($permission->value, $this->permissionValues(), true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'requires_two_factor' => 'boolean',
            'starter' => StarterProfile::class,
            'hidden_at' => 'datetime',
        ];
    }
}
