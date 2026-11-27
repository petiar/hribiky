<?php

namespace App\Service;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Neviditeľná ochrana formulárov pred spamom (bez captchy):
 *
 * 1. Skryté pole (honeypot) — človek ho nevidí, robot ho vyplní.
 * 2. Podpísaná časová značka — formulár odoslaný príliš rýchlo po načítaní je robot.
 *    Značka je podpísaná HMAC s kernel.secret, takže sa nedá podvrhnúť ani upraviť.
 *
 * Tretia vrstva (počet odoslaní z IP) je v controlleri cez Symfony Rate Limiter.
 */
final class SpamGuard
{
    /** Človek nenapíše správu rýchlejšie. */
    public const MIN_SECONDS = 3;
    /** Formulár otvorený dlhšie ako deň je podozrivý (a značka by sa dala zbierať a opakovať). */
    public const MAX_SECONDS = 86400;

    public const REASON_HONEYPOT = 'honeypot';
    public const REASON_INVALID_TOKEN = 'invalid_token';
    public const REASON_TOO_FAST = 'too_fast';
    public const REASON_EXPIRED = 'expired';

    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    /**
     * Značka do skrytého poľa formulára: „časová_značka.podpis“.
     */
    public function issueToken(): string
    {
        $timestamp = (string) $this->clock->now()->getTimestamp();

        return $timestamp . '.' . $this->sign($timestamp);
    }

    /**
     * @return string|null dôvod, prečo ide o spam (REASON_*), alebo null ak je všetko v poriadku
     */
    public function check(?string $token, ?string $honeypot): ?string
    {
        if (trim((string) $honeypot) !== '') {
            return self::REASON_HONEYPOT;
        }

        $parts = explode('.', (string) $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || !hash_equals($this->sign($parts[0]), $parts[1])) {
            return self::REASON_INVALID_TOKEN;
        }

        $elapsed = $this->clock->now()->getTimestamp() - (int) $parts[0];
        if ($elapsed < self::MIN_SECONDS) {
            return self::REASON_TOO_FAST;
        }
        if ($elapsed > self::MAX_SECONDS) {
            return self::REASON_EXPIRED;
        }

        return null;
    }

    private function sign(string $timestamp): string
    {
        return hash_hmac('sha256', 'spam-guard|' . $timestamp, $this->secret);
    }
}
