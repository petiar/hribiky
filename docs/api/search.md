# API: Vyhľadávanie

Vyhľadávanie v hríbikoch a blogových článkoch. Používa rovnakú logiku ako vyhľadávanie na webe (`/hladat`), takže appka a web nájdu to isté v rovnakom poradí.

- **API kľúč:** netreba (vracia len verejné údaje, rovnako ako webové vyhľadávanie)
- Spoločné informácie (chyby, konvencie, spätná kompatibilita) sú v [README](README.md)

## `GET /api/search`

### Parametre (query)

| Parameter | Typ | Povinný | Predvolené | Popis |
|---|---|---|---|---|
| `q` | `string` | áno | – | Hľadaný text. Medzery na okrajoch sa orežú, dlhší ako 100 znakov sa skráti. |
| `limit` | `int` | nie | `20` | Maximálny počet výsledkov **v každej skupine** (hríbiky, články), 1 až 50 |

### Ako sa hľadá

- Hľadá sa v **schválených** hríbikoch (názov, popis) a článkoch (názov, perex, text).
- Dotaz musí mať aspoň **2 znaky** (`min_query_length`). Kratší dotaz vráti prázdne výsledky, nie chybu.
- **Diakritika a veľké písmená sa ignorujú:** `bela` nájde „Belá“.
- Pri viacerých slovách musia byť nájdené **všetky** (každé môže byť v inom poli). Do úvahy sa berie najviac 5 slov dlhých aspoň 2 znaky.
- **Skloňovanie sa nerozpoznáva:** `chopok` nenájde „pod Chopkom“. Hľadá sa časť textu, nie tvar slova.
- **Poradie:** presná zhoda názvu → názov začína dotazom → dotaz kdekoľvek v názve → zhoda len v texte. V rámci rovnakej úrovne od najnovšieho.

### Odpoveď `200 OK`

| Pole | Typ | Popis |
|---|---|---|
| `query` | `string` | Dotaz po úprave (orezaný) |
| `min_query_length` | `int` | Minimálna dĺžka dotazu (teraz `2`) |
| `mushrooms` | `[Mushroom]` | Hríbiky v **rovnakom tvare ako `GET /api/mushrooms`** (viď [mushrooms.md](mushrooms.md#objekt-mushroom)) |
| `mushrooms_has_more` | `bool` | `true`, ak existuje viac hríbikov, ako je `limit` |
| `posts` | `[Post]` | Blogové články |
| `posts_has_more` | `bool` | `true`, ak existuje viac článkov, ako je `limit` |

`Post`:

| Pole | Typ | Popis |
|---|---|---|
| `id` | `int` | ID článku |
| `title` | `string` | Názov článku |
| `short_description` | `string` | Perex (čistý text) |
| `published_at` | `string \| null` | Dátum publikovania, ISO 8601 |
| `url` | `string` | Absolútna URL článku na webe. Appka zatiaľ nemá API na čítanie článkov, článok otvor vo WebView alebo v prehliadači. |
| `image_url` | `string \| null` | Absolútna URL titulnej fotky, `null` ak článok fotku nemá |
| `thumbnail_url` | `string \| null` | Náhľad titulnej fotky (800 px), inak ako `image_url`. *(Nové, 2026-11.)* |

### Príklad

`GET /api/search?q=bela&limit=2`

```json
{
  "query": "bela",
  "min_query_length": 2,
  "mushrooms": [
    {
      "id": 121,
      "title": "Belá",
      "name": "Petiar",
      "description": "Rozcestník pri nádhernej riečke…",
      "latitude": 49.0455164554,
      "longitude": 19.7284203718,
      "altitude": null,
      "photos": ["https://hribiky.sk/uploads/photos/6967b025cf140.jpg"],
      "photo_thumbnails": ["https://hribiky.sk/uploads/photos/thumbs-800/6967b025cf140.jpg"],
      "createdAt": "2026-01-14T16:03:01+00:00"
    },
    {
      "id": 205,
      "title": "Liptovský Hrádok, Belá",
      "name": "Petiar",
      "description": "Na prechádzke popri Belej…",
      "latitude": 49.0400884793,
      "longitude": 19.7159638327,
      "altitude": 638,
      "photos": [],
      "photo_thumbnails": [],
      "createdAt": "2026-05-02T14:49:00+00:00"
    }
  ],
  "mushrooms_has_more": true,
  "posts": [
    {
      "id": 14,
      "title": "Belá: Rozcestník pri krásnej riečke a lávke do Liptovského Petra",
      "short_description": "Objavte čaro turistického rozcestníka…",
      "published_at": "2026-04-09T12:56:39+00:00",
      "url": "https://hribiky.sk/blog/bela-rozcestnik-pri-krasnej-riecke-a-lavke-do-liptovskeho-petra",
      "image_url": "https://hribiky.sk/uploads/photos/6967b025cf140.jpg",
      "thumbnail_url": "https://hribiky.sk/uploads/photos/thumbs-800/6967b025cf140.jpg"
    }
  ],
  "posts_has_more": true
}
```

## Typy a použitie (TypeScript)

```ts
export interface Mushroom {
  id: number;
  title: string;
  name: string;
  description: string | null;
  latitude: number;
  longitude: number;
  altitude: number | null;
  photos: string[];
  /** Náhľady 800 px, rovnaké poradie a dĺžka ako photos. */
  photo_thumbnails: string[];
  /** ISO 8601 */
  createdAt: string;
}

export interface SearchPost {
  id: number;
  title: string;
  short_description: string;
  published_at: string | null;
  url: string;
  image_url: string | null;
  thumbnail_url: string | null;
}

export interface SearchResponse {
  query: string;
  min_query_length: number;
  mushrooms: Mushroom[];
  mushrooms_has_more: boolean;
  posts: SearchPost[];
  posts_has_more: boolean;
}

const BASE_URL = 'https://hribiky.sk';

export async function search(q: string, signal?: AbortSignal, limit = 20): Promise<SearchResponse> {
  const params = new URLSearchParams({ q, limit: String(limit) });
  const res = await fetch(`${BASE_URL}/api/search?${params}`, { signal });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}
```

### Odporúčania pre appku

- **Vyhľadávanie počas písania:** posielaj požiadavku až po krátkej pauze (napr. 250 ms od posledného písmena) a predchádzajúcu nedokončenú požiadavku zruš cez `AbortController`. Tak to robí aj web.
- **Kratší dotaz ako `min_query_length`** vôbec neposielaj, server by aj tak vrátil prázdne výsledky.
- **Bez internetu (na horách):** ak má appka hríbiky uložené lokálne (synchronizácia cez `GET /api/mushrooms?since_id=…`), môže ich hľadať aj offline. Rovnaké ignorovanie diakritiky a veľkosti písmen dosiahneš takto:

  ```ts
  const normalize = (s: string) =>
    s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

  export function searchOffline(mushrooms: Mushroom[], q: string): Mushroom[] {
    const words = normalize(q).split(/\s+/).filter(w => w.length >= 2);
    if (words.length === 0) return [];
    return mushrooms.filter(m => {
      const text = normalize(`${m.title} ${m.description ?? ''}`);
      return words.every(w => text.includes(w));
    });
  }
  ```

  Články offline k dispozícii nie sú, tie hľadaj len cez API.
