<?php

namespace App\Tests\Service;

use App\Entity\Mushroom;
use App\Form\MushroomType;
use App\Service\TitleNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TitleNormalizerTest extends KernelTestCase
{
    #[DataProvider('titles')]
    public function testFixAllCaps(string $input, string $expected): void
    {
        $this->assertSame($expected, TitleNormalizer::fixAllCaps($input));
    }

    public static function titles(): array
    {
        return [
            // Celé veľkými → Veľké Prvé Písmená (aj s diakritikou)
            'diakritika' => ['LIPTOVSKÝ HRÁDOK, BELÁ', 'Liptovský Hrádok, Belá'],
            'začína diakritikou' => ['ŽIARSKA CHATA', 'Žiarska Chata'],
            'čeština' => ['HORNÍ ZÁBĚLÁ (ŠKOLKA)', 'Horní Zábělá (Školka)'],
            'predložky' => ['CHATA POD CHOPKOM', 'Chata Pod Chopkom'],
            'pomlčka' => ['SEDLO-HOLÁ', 'Sedlo-Holá'],
            'číslo' => ['ROZCESTIE 2', 'Rozcestie 2'],
            'krátke slovo' => ['HÁJ', 'Háj'],
            'medzery zostanú' => ['  CHOPOK  ', '  Chopok  '],
            // Ostatné sa nemenia
            'bežný názov' => ['Chata pod Chopkom', 'Chata pod Chopkom'],
            'skratka v názve' => ['Chopok, KST', 'Chopok, KST'],
            'malými písmenami' => ['chopok', 'chopok'],
            'zmiešané' => ['CHATA pod Chopkom', 'CHATA pod Chopkom'],
            'krátka skratka' => ['KÚ', 'KÚ'],
            'len čísla' => ['1234', '1234'],
            'prázdny' => ['', ''],
        ];
    }

    public function testWebFormNormalizesTitle(): void
    {
        self::bootKernel();
        $mushroom = new Mushroom();
        $form = static::getContainer()->get('form.factory')->create(MushroomType::class, $mushroom, ['csrf_protection' => false]);

        $form->submit(['title' => 'LIPTOVSKÝ HRÁDOK, BELÁ', 'name' => 'Tester'], false);

        $this->assertSame('Liptovský Hrádok, Belá', $mushroom->getTitle());
    }

    public function testWebFormKeepsNormalTitle(): void
    {
        self::bootKernel();
        $mushroom = new Mushroom();
        $form = static::getContainer()->get('form.factory')->create(MushroomType::class, $mushroom, ['csrf_protection' => false]);

        $form->submit(['title' => 'Chopok, KST', 'name' => 'Tester'], false);

        $this->assertSame('Chopok, KST', $mushroom->getTitle());
    }
}
