<?php

namespace App\Tests\Controller;

use App\Entity\ApiKey;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /generate-key — vygenerovanie osobného API kľúča.
 * Po prechode na Doctrine DBAL 4 tu ukladanie padalo (DateTimeImmutable do stĺpca „datetime“).
 */
class ApiKeyControllerTest extends WebTestCase
{
    public function testGeneratesAndEmailsNewKey(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\ApiKey k')->execute();

        $client->request('POST', '/generate-key', ['email' => 'vyvojar@example.com']);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(1);
        $em->clear();
        $key = $em->getRepository(ApiKey::class)->findOneBy(['email' => 'vyvojar@example.com']);
        $this->assertNotNull($key);
        $this->assertSame(32, strlen($key->getKeyValue()));
        $this->assertGreaterThan(new \DateTime('+364 days'), $key->getValidUntil());
    }

    public function testRegeneratingReplacesExistingKey(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\ApiKey k')->execute();

        $client->request('POST', '/generate-key', ['email' => 'vyvojar@example.com']);
        $em->clear();
        $first = $em->getRepository(ApiKey::class)->findOneBy(['email' => 'vyvojar@example.com'])->getKeyValue();

        $client->request('POST', '/generate-key', ['email' => 'vyvojar@example.com']);
        $em->clear();
        $keys = $em->getRepository(ApiKey::class)->findBy(['email' => 'vyvojar@example.com']);

        $this->assertCount(1, $keys);
        $this->assertNotSame($first, $keys[0]->getKeyValue());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/generate-key', ['email' => 'nie-je-email']);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Zadajte platný e-mail', $client->getResponse()->getContent());
        $this->assertEmailCount(0);
    }
}
