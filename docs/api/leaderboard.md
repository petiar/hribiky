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

- Neznáme kľúče ignoruj. `JSONDecoder` to robí štandardne.
- Nové polia dekóduj ako voliteľné (`decodeIfPresent`) alebo s predvolenou hodnotou. Aplikácia tak zvládne aj server, ktorý ich ešte nemá.
- Neznámy `type` odznaku nesmie zhodiť dekódovanie celej odpovede, lebo v budúcnosti môžu pribudnúť nové odznaky. Nepoužívaj preto pre `type` striktný Swift `enum`, alebo pridaj prípad pre neznámu hodnotu (viď príklad nižšie).

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

- `value` je v JSON číslo, ale môže prísť ako celé (`2`) aj desatinné (`5.29`). V Swifte ho dekóduj ako `Double?`, ten zvládne oba prípady.
- `label` a `description` sú zatiaľ **len po slovensky**, aj na `hribiky.cz`. Ak aplikácia potrebuje iný jazyk, preklad si môže urobiť sama podľa `type`.

## Ikony

- Ikony sú **SVG** (64×64, kruhové), `Content-Type: image/svg+xml`.
- URL obsahuje verziu assetu ako query parameter (`?0.98.3`). Keď sa ikona zmení, zmení sa aj URL, takže ikonu môžeš cachovať podľa celej URL natrvalo.
- iOS (`UIImage`, `AsyncImage`) nevie zobraziť SVG zo vzdialenej URL priamo, potrebuješ knižnicu (napr. SVGKit alebo SDWebImageSVGCoder). Prípadne treba na serveri doplniť PNG verzie.

---

## Príklad modelov (Swift)

```swift
import Foundation

struct LeaderboardEntry: Decodable {
    let name: String
    let mushroomCount: Int
    let commentCount: Int
    let score: Int
    let badges: [UserBadge]

    enum CodingKeys: String, CodingKey {
        case name, score, badges
        case mushroomCount = "mushroom_count"
        case commentCount = "comment_count"
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        name = try c.decode(String.self, forKey: .name)
        mushroomCount = try c.decodeIfPresent(Int.self, forKey: .mushroomCount) ?? 0
        commentCount = try c.decodeIfPresent(Int.self, forKey: .commentCount) ?? 0
        score = try c.decode(Int.self, forKey: .score)
        // Starší server pole badges nemá → prázdny zoznam.
        badges = try c.decodeIfPresent([UserBadge].self, forKey: .badges) ?? []
    }
}

struct UserBadge: Decodable {
    let type: BadgeType
    let label: String
    let iconUrl: URL
    let value: Double?
    let awardedAt: Date

    enum CodingKeys: String, CodingKey {
        case type, label, value
        case iconUrl = "icon_url"
        case awardedAt = "awarded_at"
    }
}

struct BadgeDefinition: Decodable {
    let type: BadgeType
    let label: String
    let description: String
    let isTrophy: Bool
    let iconUrl: URL

    enum CodingKeys: String, CodingKey {
        case type, label, description
        case isTrophy = "is_trophy"
        case iconUrl = "icon_url"
    }
}

/// Neznámy typ (nový odznak na serveri) nezhodí dekódovanie.
enum BadgeType: Decodable, Hashable {
    case vyskar, krtko, cestovatel, rozpravac, fotograf, kronikar, sezonnyZberatel, vytrvalec
    case unknown(String)

    init(from decoder: Decoder) throws {
        let raw = try decoder.singleValueContainer().decode(String.self)
        switch raw {
        case "Vyskar": self = .vyskar
        case "Krtko": self = .krtko
        case "Cestovatel": self = .cestovatel
        case "Rozpravac": self = .rozpravac
        case "Fotograf": self = .fotograf
        case "Kronikar": self = .kronikar
        case "SezonnyZberatel": self = .sezonnyZberatel
        case "Vytrvalec": self = .vytrvalec
        default: self = .unknown(raw)
        }
    }
}

// Dekódovanie
let decoder = JSONDecoder()
decoder.dateDecodingStrategy = .iso8601
// let entries = try decoder.decode([LeaderboardEntry].self, from: data)
// let definitions = try decoder.decode([BadgeDefinition].self, from: data)
```

### Formátovanie `value` podľa typu (príklad)

```swift
func formattedValue(_ badge: UserBadge) -> String? {
    guard let v = badge.value else { return nil }
    switch badge.type {
    case .vyskar, .krtko: return "\(Int(v)) m n. m."
    case .cestovatel:     return String(format: "%.0f km", v / 1000)
    case .rozpravac:      return String(format: "%.1f / 10", v)
    case .fotograf:       return "\(Int(v)) fotiek"
    case .kronikar:       return "\(Int(v)) komentárov"
    case .vytrvalec:      return "\(Int(v)) týždňov"
    case .sezonnyZberatel, .unknown: return nil
    }
}
```
