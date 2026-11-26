# Hríbiky API

REST API pre mobilnú aplikáciu Hríbiky (iOS). Popisuje **skutočné správanie servera** vrátane jeho nekonzistencií, aby sa appka dala napísať správne.

- **Base URL:** `https://hribiky.sk` (to isté API beží aj na `https://hribiky.cz`)
- **Formát:** JSON (UTF-8), pokiaľ nie je uvedené inak
- **Zápis (POST):** `multipart/form-data`, nie JSON

## Endpointy

| Metóda | Cesta | API kľúč | Popis | Dokumentácia |
|---|---|---|---|---|
| `GET` | `/api/mushrooms` | áno | Zoznam hríbikov | [mushrooms.md](mushrooms.md#get-apimushrooms) |
| `GET` | `/api/mushrooms/{id}` | áno | Detail hríbika s komentármi | [mushrooms.md](mushrooms.md#get-apimushroomsid) |
| `GET` | `/api/mushrooms/nearby` | áno | Hríbiky v okolí bodu | [mushrooms.md](mushrooms.md#get-apimushroomsnearby) |
| `POST` | `/api/mushrooms` | áno | Pridanie hríbika | [mushrooms.md](mushrooms.md#post-apimushrooms) |
| `GET` | `/api/mushrooms_comments` | áno | Zoznam komentárov | [comments.md](comments.md#get-apimushrooms_comments) |
| `GET` | `/api/mushrooms_comments/{id}` | áno | Detail komentára | [comments.md](comments.md#get-apimushrooms_commentsid) |
| `POST` | `/api/mushrooms_comments` | áno | Pridanie komentára | [comments.md](comments.md#post-apimushrooms_comments) |
| `GET` | `/api/leaderboard` | nie | Rebríček prispievateľov s odznakmi | [leaderboard.md](leaderboard.md#get-apileaderboard) |
| `GET` | `/api/leaderboard/badges` | nie | Zoznam všetkých odznakov | [leaderboard.md](leaderboard.md#get-apileaderboardbadges) |
| `GET` | `/api/privacy/{lang}` | nie | Text zásad ochrany súkromia (HTML) | [nižšie](#get-apiprivacylang) |
| `GET` | `/api/nearby` | nie | Hríbiky v okolí, **interný endpoint webu** | [nižšie](#get-apinearby-interný-pre-web) |

## Autentifikácia

Všetky cesty začínajúce na `/api/mushrooms` (teda aj `/api/mushrooms_comments`) vyžadujú hlavičku:

```
Api-Key: <kľúč>
```

Platné sú dva druhy kľúčov:

1. **Kľúč aplikácie.** Je nastavený na serveri v premennej `IOS_API_KEY` a nemá expiráciu.
2. **Osobný kľúč.** Vygeneruje sa cez webový formulár `https://hribiky.sk/generate-key` (zadáš e-mail, kľúč príde mailom). Platí **1 rok**. Opätovné vygenerovanie pre ten istý e-mail starý kľúč nahradí.

Rebríček (`/api/leaderboard*`) a zásady ochrany súkromia (`/api/privacy/*`) kľúč nevyžadujú.

## Chybové odpovede

Formát chýb **nie je jednotný**. Appka sa má rozhodovať podľa HTTP status kódu a pri `200` skontrolovať aj pole `status`, ak v odpovedi je.

| Situácia | Status | Telo |
|---|---|---|
| Chýba `Api-Key` | `401` | `{"error": "Missing API key"}` |
| Neplatný alebo expirovaný kľúč | `403` | `{"status": "error", "message": "Invalid API key", "id": null}` |
| IP adresa je zablokovaná | `403` | text `Access forbidden.` (nie JSON), platí pre všetky endpointy |
| Záznam s daným `{id}` neexistuje | `404` | **HTML stránka**, nie JSON |
| Záznam existuje, ale nie je schválený | `200` | `[]` (prázdne JSON pole) |
| `nearby` bez súradníc | `400` | `{"error": "Missing coordinates"}` |
| `POST /api/mushrooms`, chýba povinné pole | `422` | `{"status": "error", "message": "Missing required field: title", "id": null}` |
| `POST /api/mushrooms_comments`, chýba povinné pole | **`200`** | `{"status": "error", "message": "Missing required field: mushroom_id", "id": null}` |
| Neočakávaná chyba servera | `500` | HTML stránka |

## Konvencie

- **Moderovanie:** nové hríbiky a komentáre z API sú **neschválené** (`published = false`). Vo výpisoch sa objavia až po schválení administrátorom. `POST` preto vráti `id`, ale `GET /…/{id}` na tento záznam vráti `[]`, kým nie je schválený.
- **Dátumy:**
  - `createdAt` (camelCase) v hríbikoch a komentároch je ISO 8601 s časovou zónou, napr. `2026-05-02T14:49:00+00:00`. V Swifte stačí `JSONDecoder.dateDecodingStrategy = .iso8601`.
  - **Výnimka:** `/api/mushrooms/nearby` vracia `created_at` (snake_case) vo formáte `2026-05-02 14:49:00` bez časovej zóny, v čase servera.
- **Názvy polí** sú zmiešané: `createdAt` v camelCase, ostatné v snake_case (`mushroom_count`, `icon_url`, …). Mapuj ich explicitne cez `CodingKeys`.
- **Fotky** sa vracajú ako pole absolútnych URL (`https://hribiky.sk/uploads/photos/<súbor>.jpg`).
- **Súradnice** sú WGS84 v stupňoch (`latitude` −90 až 90, `longitude` −180 až 180), desatinná bodka. **Nadmorská výška** (`altitude`) je celé číslo v metroch alebo `null`.

## Spätná kompatibilita

Odpovede sa **len rozširujú**: existujúce polia sa nemenia, neodoberajú a nemenia typ. Staršie verzie appky preto fungujú ďalej. Pre appku z toho vyplýva:

- Neznáme kľúče ignoruj. `JSONDecoder` to robí štandardne.
- Polia, ktoré pribudli neskôr, dekóduj ako voliteľné (`decodeIfPresent`) alebo s predvolenou hodnotou.
- Pri výpočtových hodnotách (napr. typ odznaku) počítaj s tým, že môžu pribudnúť nové, a neznámu hodnotu nenechaj zhodiť dekódovanie.

## `GET /api/privacy/{lang}`

Text zásad ochrany súkromia ako **HTML fragment** (nie JSON), napríklad na zobrazenie vo `WKWebView`.

| Parameter | Hodnoty | Predvolené |
|---|---|---|
| `lang` (v ceste) | `sk`, `cs`, `en` | `sk` (aj pre neznámu hodnotu) |

Príklad: `GET /api/privacy/en`

## `GET /api/nearby` (interný, pre web)

Používa ho webový formulár na pridanie hríbika (`public/js/hribiky.js`). **Mobilná aplikácia má používať [`/api/mushrooms/nearby`](mushrooms.md#get-apimushroomsnearby).**

Funguje rovnako ako `/api/mushrooms/nearby` a vracia rovnaké polia. Rozdiely:

- nevyžaduje API kľúč,
- parametre sa volajú `lat` a `lng` (namiesto `latitude` a `longitude`), `radius` je rovnaký.

Keďže je verejný, smie vracať len verejné údaje. Zoznam polí stráži test `NearbyApiTest`.
