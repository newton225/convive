<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Les documents juridiques du site : politique de confidentialite, conditions d'utilisation,
 * mentions legales. Les textes vivent dans `lang/{fr,en}/legal.php` ; l'identite de l'editeur et de
 * ses prestataires vient de `config('convive.legal')`, pour ne jamais etre ecrite en dur.
 *
 * Une information d'identite manquante s'affiche comme telle (« [a completer] ») plutot que de
 * disparaitre : le trou se voit, et `convive:production-check` refuse de partir en production avec.
 */
class LegalDocument
{
    /**
     * Date de la version en vigueur, enregistree avec le compte qui l'accepte. Elle avance a
     * chaque changement de fond des textes.
     */
    public const Version = '2026-10-03';

    /**
     * Les documents et l'adresse sous laquelle chacun est publie.
     *
     * @var array<string, string>
     */
    public const Slugs = [
        'privacy' => 'confidentialite',
        'terms' => 'conditions',
        'notice' => 'mentions-legales',
    ];

    /**
     * Ce qu'il faut savoir de l'editeur pour que les documents soient complets : la cle de
     * configuration de chaque jeton utilise dans les textes.
     *
     * @var array<string, string>
     */
    private const Identity = [
        'editor' => 'editor_name',
        'editor_address' => 'editor_address',
        'legal_form' => 'legal_form',
        'share_capital' => 'share_capital',
        'registration_number' => 'registration_number',
        'tax_number' => 'tax_number',
        'publication_director' => 'publication_director',
        'contact_email' => 'contact_email',
        'privacy_email' => 'privacy_email',
        'host' => 'host_name',
        'host_address' => 'host_address',
        'mail_provider' => 'mail_provider',
    ];

    /**
     * Get the document in the current language, with the editor's identity filled in.
     *
     * @return array{slug: string, title: string, summary: string, sections: array<int, array{id: string, title: string, paragraphs: array<int, string>, items: array<int, string>, after: array<int, string>}>}
     */
    public static function get(string $document): array
    {
        /** @var array{title: string, summary: string, sections: array<int, array<string, mixed>>} $source */
        $source = trans("legal.documents.{$document}");
        $fill = fn (string $text) => strtr($text, self::replacements());

        return [
            'slug' => $document,
            'title' => $fill($source['title']),
            'summary' => $fill($source['summary']),
            'sections' => array_map(fn (array $section) => [
                'id' => Str::slug($section['title']),
                'title' => $fill($section['title']),
                'paragraphs' => array_map($fill, $section['paragraphs'] ?? []),
                'items' => array_map($fill, $section['items'] ?? []),
                'after' => array_map($fill, $section['paragraphs_after'] ?? []),
            ], $source['sections']),
        ];
    }

    /**
     * Get the configuration keys of the editor's identity still left empty.
     *
     * @return array<int, string>
     */
    public static function missingIdentity(): array
    {
        return array_values(array_filter(
            array_unique(array_values(self::Identity)),
            fn (string $key) => blank(config("convive.legal.{$key}")),
        ));
    }

    /**
     * Determine whether a lawyer has validated the documents (`CONVIVE_LEGAL_REVIEWED`).
     */
    public static function isReviewed(): bool
    {
        return (bool) config('convive.legal.reviewed');
    }

    /**
     * Get the address a document is published at, on the central domain. Composee depuis
     * `app.url` et non depuis la requete : le parcours invite tourne sur le sous-domaine d'une
     * organisation, d'ou le lien doit ramener au site de l'editeur.
     */
    public static function url(string $document): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::Slugs[$document];
    }

    /**
     * @return array<string, string>
     */
    private static function replacements(): array
    {
        $placeholder = __('legal.placeholder');
        $replacements = [':app' => (string) config('app.name')];

        // `strtr()` remplace le jeton le plus long d'abord : `:editor_address` n'est jamais lu
        // comme `:editor` suivi de `_address`.
        foreach (self::Identity as $token => $key) {
            $value = config("convive.legal.{$key}");
            $replacements[':'.$token] = is_string($value) && $value !== '' ? $value : $placeholder;
        }

        return $replacements;
    }
}
