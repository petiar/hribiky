# API: Rebríček a odznaky

Dokumentácia endpointov rebríčka pre mobilnú aplikáciu. Spoločné informácie (autentifikácia, chyby, konvencie) sú v [README](README.md).

- **Base URL:** `https://hribiky.sk` (rovnaké API beží aj na `https://hribiky.cz`)
- **Autentifikácia:** žiadna. Endpointy rebríčka nevyžadujú hlavičku `Api-Key` (na rozdiel od `/api/mushrooms*`).
- **Formát:** JSON, `Content-Type: application/json`, kódovanie UTF-8.

| Metóda | Cesta | Popis | Od verzie |
|---|---|---|---|
| `GET` | `/api/leaderboard` | Top 100 prispievateľov a ich odznaky | pôvodný, pole `badges` pribudlo s odznakmi |
| `GET` | `/api/leaderboard/badges` | Zoznam všetkých odznakov (vysvetlivky) | nový s odznakmi |

## Spätná kompatibilita

Odpovede sa **len rozširujú**: existujúce polia sa nemenia, neodoberajú a nemenia typ, a tvar odpovede zostáva rovnaký (pole na najvyššej úrovni). Staršie verzie aplikácie preto fungujú bez zmeny, nové polia ignorujú.

Pre aplikáciu z toho vyplýva:

- Neznáme kľúče ignoruj. V JavaScripte sa to deje samo, len ich nevaliduj ako chybu (napr. pri `zod` nepoužívaj `.strict()`).
- Nové polia ber ako voliteľné (`?` v type, `?? []` pri čítaní). Aplikácia tak zvládne aj server, ktorý ich ešte nemá.
- Neznámy `type` odznaku nesmie rozbiť zobrazenie, lebo v budúcnosti môžu pribudnúť nové odznaky. Pri `switch` vždy maj vetvu `default` (viď príklad nižšie).

---

## `GET /api/leaderboard`

Vráti najviac 100 prispievateľov zoradených podľa skóre zostupne (pri zhode podľa ID). Zobrazujú sa len useri s vyplneným menom, ktorí majú aspoň jeden schválený hríbik alebo komentár.

Dáta sa prepočítavajú **raz denne** (cron `app:count-mushrooms` a `app:calculate-badges`), nie v reálnom čase. Odpoveď si preto pokojne cachuj.

### Odpoveď `200 OK`

Pole objektov:

| Pole | Typ | Popis |
|---|---|---|
| `name` | `string` | Zobrazované meno prispievateľa |
| `mushroom_count` | `int` | Počet schválených hríbikov |
| `comment_count` | `int` | Počet schválených komentárov |
| `score` | `int` | Skóre v rebríčku: `mushroom_count × 2 + comment_count` |
| `badges` | `[UserBadge]` | **Nové.** Odznaky usera, vždy pole, prázdne ako `[]` |

`UserBadge`:

| Pole | Typ | Popis |
|---|---|---|
| `type` | `string` | Identifikátor odznaku, napr. `"Vyskar"` (viď [Typy odznakov](#typy-odznakov)) |
| `label` | `string` | Zobrazovaný názov, napr. `"Výškar"` |
| `icon_url` | `string` | Absolútna URL ikony (SVG), viď [Ikony](#ikony) |
| `value` | `number \| null` | Hodnota, za ktorú bol odznak udelený. Jednotka závisí od typu, viď [Typy odznakov](#typy-odznakov) |
| `awarded_at` | `string` | Kedy bol odznak udelený alebo naposledy aktualizovaný, ISO 8601 (`2026-09-03T21:43:54+00:00`) |

Odznaky sú v poli zoradené v rovnakom poradí ako v `/api/leaderboard/badges`, teda v poradí zobrazenia na webe.

### Príklad

```json
[
  {
    "name": "Petiar",
    "mushroom_count": 30,
    "comment_count": 2,
    "score": 62,
    "badges": [
      {
        "type": "Cestovatel",
        "label": "Cestovateľ",
        "icon_url": "https://hribiky.sk/images/badges/cestovatel.svg?0.98.3",
        "value": 289892.92136549,
        "awarded_at": "2026-09-03T21:43:54+00:00"
      },
      {
        "type": "Rozpravac",
        "label": "Rozprávač",
        "icon_url": "https://hribiky.sk/images/badges/rozpravac.svg?0.98.3",
        "value": 5.29,
        "awarded_at": "2026-11-26T14:39:41+00:00"
      }
    ]
  },
  {
    "name": "Jana",
    "mushroom_count": 4,
    "comment_count": 0,
    "score": 8,
    "badges": []
  }
]
```

---

## `GET /api/leaderboard/badges`

Vráti zoznam všetkých existujúcich odznakov, napríklad pre obrazovku „Čo znamenajú odznaky“. Obsah sa mení len pri nasadení novej verzie servera, takže ho stačí načítať zriedka (napr. raz za spustenie aplikácie).

### Odpoveď `200 OK`

Pole objektov:

| Pole | Typ | Popis |
|---|---|---|
| `type` | `string` | Identifikátor odznaku, zhodný s `UserBadge.type` |
| `label` | `string` | Zobrazovaný názov |
| `description` | `string` | Vysvetlenie, za čo sa odznak udeľuje |
| `is_trophy` | `bool` | `true` = trofej, `false` = úspech (viď nižšie) |
| `icon_url` | `string` | Absolútna URL ikony (SVG) |

### Príklad

```json
[
  {
    "type": "Vyskar",
    "label": "Výškar",
    "description": "Najvyššie položený hríbik v databáze.",
    "is_trophy": true,
    "icon_url": "https://hribiky.sk/images/badges/vyskar.svg?0.98.3"
  }
]
```

---

## Typy odznakov

Sú dva druhy odznakov:

- **Trofej** (`is_trophy: true`) má vždy najviac **jeden** aktuálny držiteľ. Keď ho niekto prekoná, trofej prejde na neho a pôvodnému držiteľovi zmizne. Pri remíze si ju súčasný držiteľ ponechá. Trofej môže byť aj bez držiteľa.
- **Úspech** (`is_trophy: false`) môže mať viacero userov naraz. Raz získaný sa už nestráca.

| `type` | Názov | Druh | Za čo | `value` |
|---|---|---|---|---|
| `Vyskar` | Výškar | trofej | najvyššie položený hríbik | nadmorská výška v **metroch** |
| `Krtko` | Krtko | trofej | najnižšie položený hríbik | nadmorská výška v **metroch** |
| `Cestovatel` | Cestovateľ | trofej | dva vlastné hríbiky najďalej od seba | vzdialenosť v **metroch** (na km: `/ 1000`) |
| `Rozpravac` | Rozprávač | trofej | najlepšie priemerné AI hodnotenie popisov za posledný rok (aspoň 3 popisy) | priemerné skóre **1–10**, 2 desatinné miesta |
| `Fotograf` | Fotograf | trofej | najviac fotiek k vlastným hríbikom | **počet** fotiek |
| `Kronikar` | Kronikár | trofej | najviac komentárov k hríbikom | **počet** komentárov |
| `SezonnyZberatel` | Sezónny zberateľ | úspech | hríbik pridaný v každom zo štyroch ročných období | vždy `null` |
| `Vytrvalec` | Vytrvalec | úspech | séria aspoň 8 týždňov po sebe s pridaným hríbikom | dĺžka série v **týždňoch** v čase udelenia |

Poznámky:

- `value` je v JSON číslo, ale môže prísť ako celé (`2`) aj desatinné (`5.29`). V JavaScripte je to v oboch prípadoch `number`.
- `label` a `description` sú zatiaľ **len po slovensky**, aj na `hribiky.cz`. Ak aplikácia potrebuje iný jazyk, preklad si môže urobiť sama podľa `type`.

## Ikony

- Ikony sú **SVG** (64×64, kruhové), `Content-Type: image/svg+xml`.
- URL obsahuje verziu assetov ako query parameter (napr. `?0.98.4`). Keď sa ikona zmení, zmení sa aj URL, takže ikonu môžeš cachovať podľa celej URL natrvalo.
- **React (web):** `<img src={badge.icon_url} />` zobrazí SVG priamo.
- **React Native:** `<Image>` SVG nezobrazí, treba knižnicu, napr. `react-native-svg` (`<SvgUri uri={badge.icon_url} />`) alebo `expo-image`. Prípadne treba na serveri doplniť PNG verzie.

---

## Typy (TypeScript)

```ts
export interface LeaderboardEntry {
  name: string;
  mushroom_count: number;
  comment_count: number;
  score: number;
  /** Pridané s odznakmi — starší server ho nemá, preto voliteľné. */
  badges?: UserBadge[];
}

/** Známe typy + `string`, aby nový odznak na serveri nerozbil appku. */
export type BadgeType =
  | 'Vyskar' | 'Krtko' | 'Cestovatel' | 'Rozpravac'
  | 'Fotograf' | 'Kronikar' | 'SezonnyZberatel' | 'Vytrvalec'
  | (string & {});

export interface UserBadge {
  type: BadgeType;
  label: string;
  icon_url: string;
  value: number | null;
  /** ISO 8601, napr. "2026-09-03T21:43:54+00:00" — `new Date(awarded_at)` */
  awarded_at: string;
}

export interface BadgeDefinition {
  type: BadgeType;
  label: string;
  description: string;
  is_trophy: boolean;
  icon_url: string;
}
```

### Použitie

```ts
const BASE_URL = 'https://hribiky.sk';

export async function fetchLeaderboard(): Promise<LeaderboardEntry[]> {
  const res = await fetch(`${BASE_URL}/api/leaderboard`);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const entries: LeaderboardEntry[] = await res.json();
  return entries.map(e => ({ ...e, badges: e.badges ?? [] }));
}

export async function fetchBadgeDefinitions(): Promise<BadgeDefinition[]> {
  const res = await fetch(`${BASE_URL}/api/leaderboard/badges`);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}
```

### Formátovanie `value` podľa typu (príklad)

```ts
export function formatBadgeValue(badge: UserBadge): string | null {
  const v = badge.value;
  if (v === null) return null;
  switch (badge.type) {
    case 'Vyskar':
    case 'Krtko':      return `${Math.round(v)} m n. m.`;
    case 'Cestovatel': return `${Math.round(v / 1000)} km`;
    case 'Rozpravac':  return `${v.toFixed(1)} / 10`;
    case 'Fotograf':   return `${Math.round(v)} fotiek`;
    case 'Kronikar':   return `${Math.round(v)} komentárov`;
    case 'Vytrvalec':  return `${Math.round(v)} týždňov`;
    default:           return null; // SezonnyZberatel a neznáme typy
  }
}
```
