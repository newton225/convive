<?php

namespace Tests\Feature;

use App\Enums\ProductTour;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Les visites guidees tiennent a trois endroits qui doivent rester d'accord : le catalogue ferme
 * (`App\Enums\ProductTour`), les etapes (`resources/js/lib/product-tours.ts`) et les textes
 * (`lang/{fr,en}/tours.php`). Une etape sans texte s'afficherait avec sa cle brute.
 */
class ProductTourTextsTest extends TestCase
{
    /**
     * @return array<string, list<string>> les etapes de chaque visite, lues dans le fichier TypeScript
     */
    private function steps(): array
    {
        $source = (string) file_get_contents(resource_path('js/lib/product-tours.ts'));

        preg_match_all('/^    (\w+): \[(.*?)^    \],/ms', $source, $tours, PREG_SET_ORDER);

        $steps = [];

        foreach ($tours as $tour) {
            preg_match_all("/key: '(\w+)'/", $tour[2], $keys);
            $steps[$tour[1]] = $keys[1];
        }

        return $steps;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function texts(string $locale): array
    {
        return require lang_path($locale.'/tours.php');
    }

    public function test_le_catalogue_et_les_etapes_parlent_des_memes_visites(): void
    {
        $enum = collect(ProductTour::cases())->map->value->sort()->values()->all();
        $steps = collect($this->steps())->keys()->sort()->values()->all();

        $this->assertSame($enum, $steps);
    }

    public function test_chaque_etape_a_un_titre_et_un_texte_dans_les_deux_langues(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $texts = $this->texts($locale);

            foreach ($this->steps() as $tour => $keys) {
                foreach ($keys as $key) {
                    foreach (['title', 'body'] as $part) {
                        $this->assertNotEmpty(
                            Arr::get($texts, "$tour.$key.$part"),
                            "Texte manquant en $locale : tours.$tour.$key.$part",
                        );
                    }
                }
            }
        }
    }

    public function test_les_deux_langues_ont_les_memes_cles(): void
    {
        $this->assertSame(
            array_keys(Arr::dot($this->texts('fr'))),
            array_keys(Arr::dot($this->texts('en'))),
        );
    }

    public function test_aucun_texte_ne_contient_de_tiret_cadratin(): void
    {
        foreach (['fr', 'en'] as $locale) {
            foreach (Arr::dot($this->texts($locale)) as $key => $text) {
                $this->assertStringNotContainsString("\u{2014}", (string) $text, "Tiret cadratin en $locale : $key");
            }
        }
    }

    public function test_chaque_repere_d_etape_existe_dans_une_page(): void
    {
        $files = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js'))),
            '/\.tsx$/',
        );

        $pages = '';

        foreach ($files as $file) {
            $pages .= file_get_contents($file->getPathname())."\n";
        }

        $source = (string) file_get_contents(resource_path('js/lib/product-tours.ts'));
        preg_match_all("/anchor: '([\w-]+)'/", $source, $anchors);

        foreach (array_unique($anchors[1]) as $anchor) {
            // Un repere s'ecrit en attribut (`data-tour="x"`), dans un menu (`tourId: 'x'`) ou, pour une
            // suite numerotee (`event-step-2`), dans un gabarit (`data-tour={`event-step-${step}`}`).
            $prefix = preg_replace('/\d+$/', '', $anchor);

            $this->assertTrue(
                str_contains($pages, '"'.$anchor.'"')
                    || str_contains($pages, "'".$anchor."'")
                    || ($prefix !== $anchor && str_contains($pages, '`'.$prefix.'$')),
                "Repere sans page : $anchor",
            );
        }
    }
}
