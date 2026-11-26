# API: Hríbiky

Všetky endpointy vyžadujú hlavičku `Api-Key` (viď [README](README.md#autentifikácia)).

## Objekt `Mushroom`

Vracajú ho `GET /api/mushrooms` a `GET /api/mushrooms/{id}`.

| Pole | Typ | Popis |
|---|---|---|
| `id` | `int` | ID hríbika |
| `title` | `string` | Názov miesta (napr. `"Liptovský Hrádok, Belá"`) |
| `name` | `string` | Meno autora, ako ho zadal |
| `description` | `string \| null` | Popis miesta |
| `latitude` | `number` | Zemepisná šírka |
| `longitude` | `number` | Zemepisná dĺžka |
| `altitude` | `int \| null` | Nadmorská výška v metroch |
| `photos` | `[string]` | Absolútne URL fotiek, môže byť prázdne |
| `createdAt` | `string` | Dátum pridania, ISO 8601 |

E-mail autora sa v týchto odpovediach nevracia.

---

## `GET /api/mushrooms`

Zoznam **schválených** hríbikov, zoradený od najnovšieho (`id` zostupne).

### Parametre (query)

| Parameter | Typ | Predvolené | Popis |
|---|---|---|---|
| `since_id` | `int` | – | Vráti len hríbiky s `id` **väčším** ako táto hodnota (na synchronizáciu) |
| `limit` | `int` | bez obmedzenia | Maximálny počet záznamov. Bez parametra (alebo `0`) vráti **všetky** hríbiky. |

> **Zmena:** do novembra 2026 bol predvolený limit `100`, takže appka bez parametra `limit` nedostala staršie hríbiky. Teraz sa bez `limit` vracajú všetky.

Na synchronizáciu **neposielaj `limit`**: `?since_id=<najvyššie známe id>` vráti všetky novšie hríbiky. Ak `limit` pošleš, výsledky sú zoradené od najnovšieho a orezané. Ak od posledného `since_id` pribudlo viac hríbikov ako `limit`, staršie z nich sa **nevrátia**.

### Odpoveď `200 OK`

Pole objektov [`Mushroom`](#objekt-mushroom).

```json
[
  {
    "id": 205,
    "title": "Liptovský Hrádok, Belá",
    "name": "Petiar",
    "description": "Na prechádzke popri Belej smerom od lávky…",
    "latitude": 49.0400884793,
    "longitude": 19.7159638327,
    "altitude": 638,
    "photos": [
      "https://hribiky.sk/uploads/photos/69f5f2bc1957b.jpg",
      "https://hribiky.sk/uploads/photos/69f5f2bc24a87.jpg"
    ],
    "createdAt": "2026-05-02T14:49:00+00:00"
  }
]
```

---

## `GET /api/mushrooms/{id}`

Detail hríbika vrátane schválených komentárov.

### Odpoveď

- `200 OK` s objektom `Mushroom` a navyše poľom `comments`, ak je hríbik schválený.
- `200 OK` s telom `[]` (prázdne **pole**, nie objekt), ak hríbik existuje, ale nie je schválený.
- `404 Not Found` s **HTML** telom, ak hríbik neexistuje.

Keďže úspešná odpoveď je objekt a neschválený hríbik vráti pole, dekóduj odpoveď opatrne. Napríklad najprv skontroluj, či telo nie je `[]`.

`comments` je pole objektov:

| Pole | Typ | Popis |
|---|---|---|
| `id` | `int` | ID komentára |
| `name` | `string` | Meno autora |
| `description` | `string \| null` | Text komentára |
| `photos` | `[string]` | Absolútne URL fotiek |
| `createdAt` | `string` | Dátum pridania, ISO 8601 |

Poradie komentárov nie je zaručené.

```json
{
  "id": 205,
  "title": "Liptovský Hrádok, Belá",
  "name": "Petiar",
  "description": "Na prechádzke popri Belej…",
  "latitude": 49.0400884793,
  "longitude": 19.7159638327,
  "altitude": 638,
  "photos": ["https://hribiky.sk/uploads/photos/69f5f2bc1957b.jpg"],
  "createdAt": "2026-05-02T14:49:00+00:00",
  "comments": [
    {
      "id": 31,
      "name": "Jana",
      "description": "Značka je už opravená.",
      "photos": [],
      "createdAt": "2026-06-10T08:12:00+00:00"
    }
  ]
}
```

---

## `GET /api/mushrooms/nearby`

Schválené hríbiky v zadanom okruhu od bodu, zoradené od najbližšieho. Typické použitie: pri pridávaní nového hríbika skontrolovať, či na mieste už nejaký nie je.

### Parametre (query)

| Parameter | Typ | Povinný | Predvolené | Popis |
|---|---|---|---|---|
| `latitude` | `number` | áno | – | Zemepisná šírka bodu |
| `longitude` | `number` | áno | – | Zemepisná dĺžka bodu |
| `radius` | `int` | nie | `100` | Polomer v **metroch** (desatinná časť sa odreže) |

Bez súradníc vráti `400` s `{"error": "Missing coordinates"}`. Pozor, hodnota `0` sa tiež berie ako chýbajúca.

### Odpoveď `200 OK`

Objekt s kľúčom `hribiky`. **Formát položiek sa líši od ostatných endpointov:** dátum je v snake_case a v inom formáte a fotky sa nevracajú.

| Pole | Typ | Popis |
|---|---|---|
| `id` | `int` | ID hríbika |
| `title` | `string` | Názov miesta |
| `name` | `string` | Meno autora |
| `description` | `string \| null` | Popis |
| `latitude` | `number` | Zemepisná šírka |
| `longitude` | `number` | Zemepisná dĺžka |
| `altitude` | `int \| null` | Nadmorská výška v metroch |
| `created_at` | `string` | Dátum pridania vo formáte `YYYY-MM-DD HH:MM:SS`, bez časovej zóny |
| `distance` | `number` | Vzdialenosť od zadaného bodu v metroch |

Iné polia odpoveď neobsahuje.

```json
{
  "hribiky": [
    {
      "id": 205,
      "title": "Liptovský Hrádok, Belá",
      "name": "Petiar",
      "description": "Na prechádzke popri Belej…",
      "latitude": 49.0400884793,
      "longitude": 19.7159638327,
      "altitude": 638,
      "created_at": "2026-05-02 14:49:00",
      "distance": 12
    }
  ]
}
```

---

## `POST /api/mushrooms`

Pridá nový hríbik. Hríbik sa uloží ako **neschválený** a zobrazí sa až po schválení administrátorom. Server pošle notifikáciu administrátorovi a ak je zadaný e-mail, aj poďakovanie autorovi.

### Telo: `multipart/form-data`

| Pole | Typ | Povinné | Popis |
|---|---|---|---|
| `title` | text | áno | Názov miesta |
| `name` | text | áno | Meno autora (zobrazuje sa verejne a v rebríčku) |
| `latitude` | číslo | áno | Zemepisná šírka, desatinná bodka |
| `longitude` | číslo | áno | Zemepisná dĺžka, desatinná bodka |
| `description` | text | nie | Popis miesta |
| `altitude` | číslo | nie | Nadmorská výška v metroch (zaokrúhli sa na celé) |
| `email` | text | nie | E-mail autora. Nezobrazuje sa, ale **podľa neho sa hríbik započíta do rebríčka**, preto je dobré posielať vždy ten istý. |
| `country` | text | nie | Kód krajiny (max. 16 znakov), napr. `SK` |
| `photos[]` | súbor(y) | nie | Fotky, pole môže obsahovať viac súborov (funguje aj `photos` s jedným súborom). Pred odoslaním ich zmenši (viď [README](README.md#veľkosť-fotiek)). |

`latitude`, `longitude` a `altitude` musia byť číselné hodnoty. Nečíselná hodnota spôsobí chybu servera (`500`).

### Odpovede

- `201 Created`: `{"status": "ok", "id": 206}`
- `422`: chýba povinné pole, napr. `{"status": "error", "message": "Missing required field: latitude", "id": null}`
- `413`: fotka alebo celý request je priveľký, `code` je `photo_too_large` alebo `request_too_large` (viď [README](README.md#chybové-odpovede)). Hríbik sa **neuloží**, odošli ho znova s menšími fotkami.
- `400`: fotku sa nepodarilo nahrať, `code: "photo_upload_failed"`

### Príklad (curl)

```bash
curl -X POST https://hribiky.sk/api/mushrooms \
  -H "Api-Key: $API_KEY" \
  -F "title=Chata pod Chlebom" \
  -F "name=Jana" \
  -F "email=jana@example.com" \
  -F "latitude=49.1912" \
  -F "longitude=19.0423" \
  -F "altitude=1423" \
  -F "description=Rozcestník pri chate." \
  -F "photos[]=@foto1.jpg" \
  -F "photos[]=@foto2.jpg"
```
