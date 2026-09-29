<?php

namespace Tests\Unit\Support;

use App\Support\ReadableTextColor;
use Tests\TestCase;

/**
 * Le texte pose sur une couleur de marque (bouton et en-tete des emails invites) reste lisible
 * quelle que soit la couleur choisie par l'organisation.
 */
class ReadableTextColorTest extends TestCase
{
    public function test_un_fond_clair_recoit_un_texte_fonce(): void
    {
        $this->assertSame(ReadableTextColor::Dark, ReadableTextColor::on('#00e639'));
        $this->assertSame(ReadableTextColor::Dark, ReadableTextColor::on('#c9a227'));
        $this->assertSame(ReadableTextColor::Dark, ReadableTextColor::on('#ffffff'));
    }

    public function test_un_fond_fonce_recoit_un_texte_clair(): void
    {
        $this->assertSame(ReadableTextColor::Light, ReadableTextColor::on('#7b1e3a'));
        $this->assertSame(ReadableTextColor::Light, ReadableTextColor::on('#1b1917'));
        $this->assertSame(ReadableTextColor::Light, ReadableTextColor::on('#1d4ed8'));
    }

    public function test_la_notation_courte_et_les_majuscules_sont_acceptees(): void
    {
        $this->assertSame(ReadableTextColor::Dark, ReadableTextColor::on('#FFF'));
        $this->assertSame(ReadableTextColor::Light, ReadableTextColor::on('#000'));
    }
}
