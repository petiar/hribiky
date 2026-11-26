<?php

namespace App\Tests\Command;

use App\Command\OptimizePhotosCommand;
use App\Entity\BlogPost;
use App\Entity\Mushroom;
use App\Entity\Photo;
use App\Service\ImageLoader;
use App\Service\ImageOptimizer;
use App\Service\ThumbnailService;
use App\Tests\Service\TestImages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class OptimizePhotosCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $dir;
    private Mushroom $mushroom;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('Chýba rozšírenie GD.');
        }
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Photo p')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomComment c')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\MushroomEditLink l')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();

        $this->dir = sys_get_temp_dir() . '/hribiky-optimize-cmd-' . uniqid();
        mkdir($this->dir . '/thumbs', 0777, true);

        $this->mushroom = (new Mushroom())->setTitle('Test')->setName('Tester')->setLatitude(48.1)->setLongitude(17.1);
        $this->em->persist($this->mushroom);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function testDryRunChangesNothingButReportsSavings(): void
    {
        $big = $this->addPhoto('big.jpg', fn($p) => TestImages::jpeg($p, 3000, 2000, quality: 95));
        $before = md5_file($big);

        $tester = $this->runOptimize([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame($before, md5_file($big), 'Bez --force sa súbor nesmie zmeniť.');
        $this->assertStringContainsString('NANEČISTO', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/Fotky na úpravu: .* MB → .* MB \(ušetrí sa .* MB, −\d+ %\)/u', $tester->getDisplay());
        $this->assertSame([], glob(sys_get_temp_dir() . '/hribiky-dry-*big.jpg'), 'Dočasná kópia sa musí zmazať.');
    }

    public function testForceOptimizesOnlyPhotosThatNeedIt(): void
    {
        $big = $this->addPhoto('big.jpg', fn($p) => TestImages::jpeg($p, 3000, 2000, quality: 95));
        $exif = $this->addPhoto('exif.jpg', fn($p) => TestImages::jpegWithExif($p, 600, 400, orientation: 6, make: 'TestPhone'));
        $ok = $this->addPhoto('ok.jpg', fn($p) => TestImages::jpeg($p, 800, 600));
        $okHash = md5_file($ok);

        $tester = $this->runOptimize(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        // Veľká → zmenšená
        $this->assertSame([2000, 1333], array_slice(getimagesize($big), 0, 2));
        // S EXIF → otočená a bez EXIF
        $this->assertSame([400, 600], array_slice(getimagesize($exif), 0, 2));
        $this->assertStringNotContainsString('TestPhone', file_get_contents($exif));
        // Už v poriadku → nedotknutá (bez opakovaného ukladania)
        $this->assertSame($okHash, md5_file($ok));
        $this->assertMatchesRegularExpression('/upravené\s+2/u', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/preskočené.*\s+1/u', $tester->getDisplay());
    }

    public function testSecondRunSkipsEverything(): void
    {
        $big = $this->addPhoto('big.jpg', fn($p) => TestImages::jpeg($p, 3000, 2000));
        $this->runOptimize(['--force' => true]);
        $hash = md5_file($big);

        $tester = $this->runOptimize(['--force' => true]);

        $this->assertSame($hash, md5_file($big), 'Druhý beh nesmie fotku znova ukladať.');
        $this->assertMatchesRegularExpression('/upravené\s+0/u', $tester->getDisplay());
    }

    public function testLimitProcessesOnlyNPhotos(): void
    {
        $paths = [];
        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $name) {
            $paths[] = $this->addPhoto($name, fn($p) => TestImages::jpeg($p, 2500, 1500));
        }

        $this->runOptimize(['--force' => true, '--limit' => 2]);

        $resized = array_filter($paths, fn($p) => getimagesize($p)[0] === 2000);
        $this->assertCount(2, $resized);
    }

    public function testMissingAndNonImageFilesAreReportedAndLeftAlone(): void
    {
        $this->addPhotoRecord('chyba-na-disku.jpg');
        $text = $this->addPhoto('nie-obrazok.jpg', fn($p) => file_put_contents($p, 'text') ? $p : $p);

        $tester = $this->runOptimize(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('chyba-na-disku.jpg', $tester->getDisplay());
        $this->assertSame('text', file_get_contents($text));
    }

    public function testPhotoSharedByMushroomAndBlogPostIsProcessedOnce(): void
    {
        $path = $this->addPhoto('spolocna.jpg', fn($p) => TestImages::jpeg($p, 3000, 2000));
        // Blog post používa tú istú fotku (rovnaký súbor) — napr. pri generovaní článku z hríbika.
        $post = (new BlogPost())->setTitle('T')->setSlug('t-' . uniqid())->setShortDescription('P')->setText('T');
        $this->em->persist($post);
        $this->em->persist((new Photo())->setPath('spolocna.jpg')->setOwner(BlogPost::class)->setBlogPost($post));
        $this->em->flush();

        $tester = $this->runOptimize(['--force' => true]);

        $this->assertMatchesRegularExpression('/upravené\s+1/u', $tester->getDisplay());
        $this->assertSame(2000, getimagesize($path)[0]);
    }

    // --- Pomocné metódy ---

    private function runOptimize(array $options): CommandTester
    {
        $loader = new ImageLoader();
        $command = new OptimizePhotosCommand(
            $this->em,
            new ImageOptimizer($loader),
            new ThumbnailService($this->dir, $loader),
            $this->dir,
        );
        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }

    private function addPhoto(string $name, callable $create): string
    {
        $path = $this->dir . '/' . $name;
        $create($path);
        $this->addPhotoRecord($name);

        return $path;
    }

    private function addPhotoRecord(string $name): void
    {
        $photo = (new Photo())->setPath($name)->setOwner(Mushroom::class)->setMushroom($this->mushroom);
        $this->em->persist($photo);
        $this->em->flush();
    }
}
