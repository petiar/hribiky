<?php

namespace App\Tests\Controller;

use App\Entity\Feedback;
use App\Service\SpamGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Formulár spätnej väzby (/feedback, odkaz z App Store) a jeho ochrana proti spamu.
 */
class FeedbackControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = static::mockTime('2026-06-15 12:00:00');
        $this->client = static::createClient();
        // Počítadlo odoslaní (rate limiter) je v cache — každý test začína od nuly.
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->createQuery('DELETE FROM App\Entity\Feedback f')->execute();
    }

    public function testPageLoadsWithProtectionFields(): void
    {
        $crawler = $this->client->request('GET', '/feedback');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('textarea[name="feedback[text]"]'));
        $this->assertCount(1, $crawler->filter('input[name="feedback[email]"]'));
        // Skryté pole je mimo obrazovky a nedá sa naň dostať tabulátorom
        $this->assertCount(1, $crawler->filter('.hp-field input[name="feedback[website]"][tabindex="-1"]'));
        $this->assertMatchesRegularExpression('/^\d+\.[0-9a-f]{64}$/', $crawler->filter('input[name="feedback[started]"]')->attr('value'));
    }

    public function testHumanSubmissionIsSavedAndAdminNotified(): void
    {
        $this->submit(['feedback[text]' => 'Na mape nejde zoom.', 'feedback[email]' => 'jana@example.com'], waitSeconds: 10);

        $this->assertResponseRedirects('/feedback');
        $this->assertEmailCount(1);
        $saved = $this->savedFeedback();
        $this->assertCount(1, $saved);
        $this->assertSame('Na mape nejde zoom.', $saved[0]->getText());
        $this->assertSame('jana@example.com', $saved[0]->getEmail());

        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Ďakujem! Správa prišla');
    }

    public function testEmailIsOptional(): void
    {
        $this->submit(['feedback[text]' => 'Bez e-mailu, len poďakovanie.'], waitSeconds: 10);

        $this->assertResponseRedirects('/feedback');
        $this->assertNull($this->savedFeedback()[0]->getEmail());
    }

    public function testFilledHoneypotIsSilentlyDropped(): void
    {
        $this->submit(['feedback[text]' => 'Buy cheap pills', 'feedback[website]' => 'https://spam.example'], waitSeconds: 10);

        // Robot dostane rovnakú odpoveď ako človek…
        $this->assertResponseRedirects('/feedback');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'Ďakujem');
        // …ale nič sa neuloží ani neodošle.
        $this->assertSame([], $this->savedFeedback());
    }

    public function testTooFastSubmissionIsSilentlyDropped(): void
    {
        $this->submit(['feedback[text]' => 'Odoslané hneď po načítaní.'], waitSeconds: SpamGuard::MIN_SECONDS - 1);

        $this->assertResponseRedirects('/feedback');
        $this->assertSame([], $this->savedFeedback());
    }

    public function testForgedTimestampIsSilentlyDropped(): void
    {
        $crawler = $this->client->request('GET', '/feedback');
        $form = $crawler->selectButton('feedback[submit]')->form([
            'feedback[text]' => 'Podvrhnutá časová značka.',
            // Robot si „posunie“ čas dozadu, ale podpis nesedí
            'feedback[started]' => '1000000000.' . str_repeat('a', 64),
        ]);
        $this->clock->sleep(10);
        $this->client->submit($form);

        $this->assertResponseRedirects('/feedback');
        $this->assertSame([], $this->savedFeedback());
    }

    public function testEmptyTextShowsValidationError(): void
    {
        $this->submit(['feedback[text]' => ''], waitSeconds: 10);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([], $this->savedFeedback());
    }

    public function testInvalidEmailShowsValidationError(): void
    {
        $this->submit(['feedback[text]' => 'Správa s chybným e-mailom.', 'feedback[email]' => 'nie-je-email'], waitSeconds: 10);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([], $this->savedFeedback());
    }

    public function testRateLimitBlocksSixthMessageFromSameIp(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit(['feedback[text]' => "Správa číslo $i"], waitSeconds: 10);
            $this->assertResponseRedirects('/feedback', message: "Správa $i mala prejsť");
        }

        $this->submit(['feedback[text]' => 'Šiesta správa'], waitSeconds: 10);

        $this->assertResponseStatusCodeSame(429);
        $this->assertSelectorTextContains('.alert-danger', 'priveľa správ');
        $this->assertCount(5, $this->savedFeedback());
    }

    // --- Pomocné metódy ---

    /**
     * Načíta formulár, „počká“ (posunie hodiny) a odošle ho ako prehliadač.
     */
    private function submit(array $values, int $waitSeconds): void
    {
        $crawler = $this->client->request('GET', '/feedback');
        $form = $crawler->selectButton('feedback[submit]')->form($values);
        $this->clock->sleep($waitSeconds);
        $this->client->submit($form);
    }

    /**
     * @return Feedback[]
     */
    private function savedFeedback(): array
    {
        $this->em->clear();

        return $this->em->getRepository(Feedback::class)->findBy([], ['id' => 'ASC']);
    }
}
