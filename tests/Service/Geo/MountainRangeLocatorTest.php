<?php

namespace App\Tests\Service\Geo;

use App\Service\Geo\MountainRangeLocator;
use PHPUnit\Framework\TestCase;

/**
 * Pohorie z lokálnych dát data/geomorfologia/units.json (výrez z OpenStreetMap).
 */
class MountainRangeLocatorTest extends TestCase
{
    private MountainRangeLocator $locator;

    protected function setUp(): void
    {
        $this->locator = new MountainRangeLocator(dirname(__DIR__, 3) . '/data/geomorfologia/units.json');
    }

    public function testPicksWholeMountainRangeNotItsPart(): void
    {
        // Chopok leží v Nízkych Tatrách > Ďumbierskych Tatrách > Ďumbieri — chceme celok.
        $this->assertSame(['sk' => 'Nízke Tatry', 'cs' => 'Nízke Tatry', 'distance' => 0], $this->locator->locate(48.9436, 19.5925));
    }

    public function testKnownRanges(): void
    {
        $this->assertSame('Malá Fatra', $this->locator->locate(49.2247, 19.1041)['sk']); // Sedlo Medziholie
        $this->assertSame('Veľká Fatra', $this->locator->locate(48.98, 19.13)['sk']);
        $this->assertSame('Bílé Karpaty', $this->locator->locate(48.8578, 17.6747)['cs']); // Veľká Javorina
    }

    public function testNearbyRangeWhenJustOutside(): void
    {
        // Rázcestie pod Furkotou — Podtatranská kotlina v OSM chýba, Tatry sú 430 m odtiaľ.
        $result = $this->locator->locate(49.1324575106, 20.036994014);

        $this->assertSame('Tatry', $result['sk']);
        $this->assertGreaterThan(0, $result['distance']);
        $this->assertLessThanOrEqual(MountainRangeLocator::NEAR_METERS, $result['distance']);
    }

    public function testNothingFarFromMountains(): void
    {
        $this->assertNull($this->locator->locate(50.0875, 14.4213)); // Praha
        $this->assertNull($this->locator->locate(48.7334927, 18.759529)); // námestie v Handlovej, ~2,8 km od pohoria
    }
}
