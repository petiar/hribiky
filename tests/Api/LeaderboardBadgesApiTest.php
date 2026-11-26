<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\BadgeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LeaderboardBadgesApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\UserBadge b')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    // --- Spätná kompatibilita /api/leaderboard ---

    public function testLeaderboardKeepsOriginalShapeForOldApps(): void
    {
        $this->createUser('a@test.sk', 'Anna', mushrooms: 5, comments: 2);

        $data = $this->getJson('/api/leaderboard');

        // Na najvyššej úrovni je stále pole (nie objekt).
        $this->assertTrue(array_is_list($data));
        $this->assertCount(1, $data);

        // Pôvodné polia s pôvodnými typmi a hodnotami.
        $this->assertSame('Anna', $data[0]['name']);
        $this->assertSame(5, $data[0]['mushroom_count']);
        $this->assertSame(2, $data[0]['comment_count']);
        $this->assertSame(12, $data[0]['score']);
    }

    public function testUserWithoutBadgesHasEmptyBadgesList(): void
    {
        $this->createUser('a@test.sk', 'Anna', mushrooms: 1);

        $raw = $this->getRaw('/api/leaderboard');

        // Prázdny zoznam musí byť JSON pole [], nie objekt {} — inak by ho appka nedekódovala.
        $this->assertStringContainsString('"badges":[]', $raw);
    }

    public function testLeaderboardIncludesUserBadgesInEnumOrder(): void
    {
        $user = $this->createUser('a@test.sk', 'Anna', mushrooms: 3);
        // Zámerne v opačnom poradí, než je v BadgeType.
        $this->createBadge($user, BadgeType::Fotograf, 12);
        $this->createBadge($user, BadgeType::Vyskar, 1930);

        $badges = $this->getJson('/api/leaderboard')[0]['badges'];

        $this->assertSame(['Vyskar', 'Fotograf'], array_column($badges, 'type'));
        $this->assertSame('Výškar', $badges[0]['label']);
        $this->assertEquals(1930, $badges[0]['value']);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/images/badges/vyskar\.svg(\?.*)?$#', $badges[0]['icon_url']);
        $this->assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $badges[0]['awarded_at']));
    }

    // --- Nový endpoint /api/leaderboard/badges ---

    public function testBadgesEndpointListsAllBadgeTypes(): void
    {
        $data = $this->getJson('/api/leaderboard/badges');

        $this->assertSame(array_map(fn(BadgeType $t) => $t->value, BadgeType::cases()), array_column($data, 'type'));
        foreach ($data as $item) {
            $this->assertNotEmpty($item['label']);
            $this->assertNotEmpty($item['description']);
            $this->assertIsBool($item['is_trophy']);
            // Za .svg môže byť verzia assetu (?0.98.3) kvôli cache.
            $this->assertMatchesRegularExpression('#^https?://.+\.svg(\?.*)?$#', $item['icon_url']);
        }
    }

    public function testBadgesEndpointDoesNotRequireApiKey(): void
    {
        $this->client->request('GET', '/api/leaderboard/badges');

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    // --- Pomocné metódy ---

    private function getRaw(string $url): string
    {
        $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();

        return $this->client->getResponse()->getContent();
    }

    private function getJson(string $url): array
    {
        return json_decode($this->getRaw($url), true);
    }

    private function createUser(string $email, string $name, int $mushrooms = 0, int $comments = 0): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setName($name)
            ->setPassword('disabled')
            ->setMushroomCount($mushrooms)
            ->setCommentCount($comments);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createBadge(User $user, BadgeType $type, ?float $value): void
    {
        $badge = new UserBadge();
        $badge->setUser($user);
        $badge->setType($type);
        $badge->setValue($value);

        $this->em->persist($badge);
        $this->em->flush();
    }
}
