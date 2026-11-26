# API: Komentáre k hríbikom

Komentár je update alebo poznámka k existujúcemu hríbiku (napr. „značka je opravená“). Všetky endpointy vyžadujú hlavičku `Api-Key` (viď [README](README.md#autentifikácia)).

## Objekt `Comment`

| Pole | Typ | Popis |
|---|---|---|
| `id` | `int` | ID komentára |
| `name` | `string` | Meno autora |
| `description` | `string \| null` | Text komentára |
| `photos` | `[string]` | Absolútne URL fotiek, môže byť prázdne |
| `createdAt` | `string` | Dátum pridania, ISO 8601 |

Objekt **neobsahuje ID hríbika**, ku ktorému komentár patrí. Ak ho potrebuješ, načítaj komentáre cez `?mushroom_id=` alebo cez detail hríbika (`GET /api/mushrooms/{id}`, pole `comments`).

---

## `GET /api/mushrooms_comments`

Zoznam **schválených** komentárov, zoradený od najnovšieho (`id` zostupne).

### Parametre (query)

| Parameter | Typ | Predvolené | Popis |
|---|---|---|---|
| `mushroom_id` | `int` | – | Len komentáre k danému hríbiku |
| `since_id` | `int` | – | Len komentáre s `id` **väčším** ako táto hodnota |
| `limit` | `int` | bez obmedzenia | Maximálny počet záznamov. Bez parametra (alebo `0`) vráti **všetky** komentáre. |

> **Zmena:** do novembra 2026 bol predvolený limit `100`. Teraz sa bez `limit` vracajú všetky komentáre.

Na synchronizáciu platí to isté ako pri hríbikoch: **neposielaj `limit`**, `?since_id=<najvyššie známe id>` vráti všetky novšie komentáre (viď [mushrooms.md](mushrooms.md#get-apimushrooms)).

### Odpoveď `200 OK`

Pole objektov [`Comment`](#objekt-comment).

```json
[
  {
    "id": 31,
    "name": "Jana",
    "description": "Značka je už opravená.",
    "photos": ["https://hribiky.sk/uploads/photos/6a1b2c3d4e5f6.jpg"],
    "createdAt": "2026-06-10T08:12:00+00:00"
  }
]
```

---

## `GET /api/mushrooms_comments/{id}`

Detail komentára. Vracia **len** `id`, `name` a `description`, bez fotiek a dátumu.

### Odpoveď

- `200 OK`: `{"id": 31, "name": "Jana", "description": "Značka je už opravená."}`
- `200 OK` s telom `[]`, ak komentár existuje, ale nie je schválený
- `404 Not Found` s **HTML** telom, ak komentár neexistuje

---

## `POST /api/mushrooms_comments`

Pridá komentár k hríbiku. Komentár sa uloží ako **neschválený** a zobrazí sa až po schválení administrátorom. Server pošle notifikáciu administrátorovi a ak je zadaný e-mail, aj poďakovanie autorovi.

### Telo: `multipart/form-data`

| Pole | Typ | Povinné | Popis |
|---|---|---|---|
| `mushroom_id` | číslo | áno | ID **existujúceho** hríbika. Neexistujúce ID spôsobí chybu servera (`500`). |
| `name` | text | áno | Meno autora |
| `description` | text | nie | Text komentára |
| `email` | text | nie | E-mail autora. Nezobrazuje sa, ale započítava sa do rebríčka. |
| `photo` | súbor | nie | **Jedna** fotka (pole sa volá `photo`, nie `photos[]` ako pri hríbiku) |

### Odpovede

- `201 Created`: `{"status": "ok", "id": 32}`
- **`200 OK`** pri chýbajúcom povinnom poli: `{"status": "error", "message": "Missing required field: mushroom_id", "id": null}`. Na rozdiel od hríbikov tu **nie je** status `422`, chybu treba rozpoznať podľa `"status": "error"` v tele.

### Príklad (curl)

```bash
curl -X POST https://hribiky.sk/api/mushrooms_comments \
  -H "Api-Key: $API_KEY" \
  -F "mushroom_id=205" \
  -F "name=Jana" \
  -F "email=jana@example.com" \
  -F "description=Značka je už opravená." \
  -F "photo=@foto.jpg"
```
