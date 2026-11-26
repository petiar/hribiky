<?php

namespace App\Enum;

enum BadgeType: string
{
    case Vyskar = 'Vyskar';
    case Krtko = 'Krtko';
    case Cestovatel = 'Cestovatel';
    case Rozpravac = 'Rozpravac';
    case Fotograf = 'Fotograf';
    case Kronikar = 'Kronikar';
    case SezonnyZberatel = 'SezonnyZberatel';
    case Vytrvalec = 'Vytrvalec';

    public function label(): string
    {
        return match ($this) {
            self::Vyskar => 'Výškar',
            self::Krtko => 'Krtko',
            self::Cestovatel => 'Cestovateľ',
            self::Rozpravac => 'Rozprávač',
            self::Fotograf => 'Fotograf',
            self::Kronikar => 'Kronikár',
            self::SezonnyZberatel => 'Sezónny zberateľ',
            self::Vytrvalec => 'Vytrvalec',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Vyskar => 'Najvyššie položený hríbik v databáze.',
            self::Krtko => 'Najnižšie položený hríbik v databáze.',
            self::Cestovatel => 'Dva vlastné hríbiky najviac vzdialené od seba.',
            self::Rozpravac => 'Najlepšie priemerné AI hodnotenie popisov za posledný rok (aspoň 3 popisy).',
            self::Fotograf => 'Najviac nahraných fotiek k vlastným hríbikom.',
            self::Kronikar => 'Najviac komentárov a updatov k hríbikom.',
            self::SezonnyZberatel => 'Hríbik pridaný v každom zo štyroch ročných období.',
            self::Vytrvalec => 'Séria pravidelného pridávania hríbikov bez prerušenia.',
        };
    }

    /**
     * Trofej = má vždy len 1 aktuálneho držiteľa (prepisuje sa).
     * Úspech = môže mať viacero userov naraz, raz získaný sa nestráca.
     */
    public function isTrophy(): bool
    {
        return match ($this) {
            self::Vyskar, self::Krtko, self::Cestovatel, self::Rozpravac, self::Fotograf, self::Kronikar => true,
            self::SezonnyZberatel, self::Vytrvalec => false,
        };
    }

    /**
     * Názov súboru ikony v public/images/badges/{slug}.svg.
     */
    public function iconSlug(): string
    {
        return match ($this) {
            self::Vyskar => 'vyskar',
            self::Krtko => 'krtko',
            self::Cestovatel => 'cestovatel',
            self::Rozpravac => 'rozpravac',
            self::Fotograf => 'fotograf',
            self::Kronikar => 'kronikar',
            self::SezonnyZberatel => 'sezonny-zberatel',
            self::Vytrvalec => 'vytrvalec',
        };
    }
}
