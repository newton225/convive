<?php

namespace App\Support;

use App\Models\TenantBranding;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Range les fichiers par locataire : `tenants/{tenant_id}/{collection}/{media_id}/`.
 *
 * Le chemin par defaut du paquet ne porte que l'identifiant du media. Ranger par locataire
 * rend le cloisonnement visible sur le disque, ce qui compte le jour d'une restauration
 * partielle ou d'une suppression sur demande.
 *
 * Necessaire, pas seulement lisible : le disque `tenant_media` est un stockage local partage
 * par tous les locataires, contrairement a leurs bases de donnees. Un modele qui vit dans la
 * base d'un locataire (`PaymentProof`, par exemple) y recommence son autoincrement a 1 : sans
 * ce prefixe, deux locataires dont un media porte le meme identifiant ecraseraient le fichier
 * l'un de l'autre sur le disque.
 */
class TenantMediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        // Comparer `model_type`, une simple colonne, avant de toucher `model` : cette derniere
        // charge la relation polymorphe, donc une requete sur la connexion du modele possede.
        // Pour `PaymentProof`, cette connexion est celle du locataire courant (« tenant »), qui
        // peut avoir ete purgee si la tenancy s'est terminee entre le depot et la lecture de
        // l'URL (par exemple une page qui affiche un recu apres la fin de la requete qui l'a
        // resolu) : `tenant('id')` n'a alors besoin d'aucune requete.
        $tenantId = $media->model_type === TenantBranding::class && ($branding = $media->model) instanceof TenantBranding
            ? $branding->tenant_id
            : tenant('id');

        return "tenants/{$tenantId}/{$media->collection_name}/{$media->getKey()}/";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }
}
