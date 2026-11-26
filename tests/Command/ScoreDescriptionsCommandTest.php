<?php

namespace App\Tests\Command;

use App\Command\ScoreDescriptionsCommand;
use App\Entity\Mushroom;
use App\Repository\MushroomRepository;
use App\Service\MushroomDescriptionScorerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Volania AI nahrádza MockHttpClient — žiadne skutočné requesty, žiadne tokeny.
 */
class ScoreDescriptionsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock('2026-06-15 12:00:00');
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Photo p')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Mushroom m')->execute();
    }

    public function testSuccessfulScoreIsStored(): void
    {
        $mushroom = $this->createMushroom();
        $http = new MockHttpClient([$this->aiResponse('<score>7</score>')]);

        $this->runScoring($http);

        $mushroom = $this->reload($mushroom);
        $this->assertSame(7, $mushroom->getDescriptionScore());
        $this->assertEquals(new \DateTimeImmutable('2026-06-15 12:00:00'), $mushroom->getDescriptionScoredAt());
        $this->assertSame(0, $mushroom->getDescriptionScoreAttempts());
    }

    public function testFailedAttemptsAreCountedAndStopAfterLimit(): void
    {
        $mushroom = $this->createMushroom();
        // AI zakaždým vráti odpoveď bez <score> bloku.
        $http = new MockHttpClient(fn() => $this->aiResponse('Neviem ohodnotiť.'));

        for ($run = 1; $run <= 3; $run++) {
            $this->runScoring($http);
            $this->assertSame($run, $this->reload($mushroom)->getDescriptionScoreAttempts());
        }
        $this->assertSame(3, $http->getRequestsCount());

        // Štvrtý beh už hríbik neposiela na AI.
        $display = $this->runScoring($http);

        $this->assertSame(3, $http->getRequestsCount());
        $this->assertStringContainsString('Žiadne hríbiky na ohodnotenie', $display);
        $this->assertNull($this->reload($mushroom)->getDescriptionScore());
    }

    public function testHttpErrorCountsAsFailedAttempt(): void
    {
        $mushroom = $this->createMushroom();
        $http = new MockHttpClient([new MockResponse('{"error":"unauthorized"}', ['http_code' => 401])]);

        $this->runScoring($http);

        $this->assertSame(1, $this->reload($mushroom)->getDescriptionScoreAttempts());
    }

    private function runScoring(MockHttpClient $http): string
    {
        $scorer = new MushroomDescriptionScorerService($http, 'test-key', new NullLogger());
        $command = new ScoreDescriptionsCommand(
            static::getContainer()->get(MushroomRepository::class),
            $scorer,
            $this->em,
            $this->clock,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->em->clear();

        return $tester->getDisplay();
    }

    private function aiResponse(string $text): MockResponse
    {
        return new MockResponse(json_encode([
            'content' => [['type' => 'text', 'text' => $text]],
        ]), ['http_code' => 200]);
    }

    private function createMushroom(): Mushroom
    {
        $mushroom = (new Mushroom())
            ->setTitle('Hríbik ' . uniqid())
            ->setName('Tester')
            ->setEmail('a@test.sk')
            ->setLatitude(48.1486)
            ->setLongitude(17.1077)
            ->setDescription('Rozcestník pri potoku, odtiaľ vedie modrá značka na vrchol.')
            ->setPublished(true)
            ->setCreatedAt(\DateTime::createFromImmutable($this->clock->now()->modify('-2 months')));

        $this->em->persist($mushroom);
        $this->em->flush();

        return $mushroom;
    }

    private function reload(Mushroom $mushroom): Mushroom
    {
        $this->em->clear();

        return $this->em->getRepository(Mushroom::class)->find($mushroom->getId());
    }
}
