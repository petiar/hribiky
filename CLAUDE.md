# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

**Hríbiky.sk** — platforma na mapovanie turistických rozcestníkov (tzv. "hríbikov"). Nie je to o hubárstve — "hríbik" je slangový výraz pre turistický rozcestník. Stránka slúži na zdieľanie zaujímavých miest, turistiku a cykloturistiku. Obsahuje AI-generovaný blog o lokalitách.

## Commands

```bash
# Závislostiach
composer install

# Cache
php bin/console cache:clear

# Databáza
php bin/console doctrine:migrations:migrate

# Assets
php bin/console importmap:install
php bin/console assets:install public

# Testy
php bin/phpunit
php bin/phpunit --filter=TestClassName

# Custom príkazy
php bin/console app:generate-blog-posts   # Generuje AI blogposty z hríbikov + publikuje naplánované
php bin/console app:count-mushrooms       # Štatistiky
```

## Architecture

### Entities
- **Mushroom** — hlavná entita, turistický rozcestník s GPS súradnicami, fotkami, popisom. Pole `blogPostGenerated` sleduje či už má vygenerovaný blog.
- **BlogPost** — AI-generovaný článok z hríbika. Slug je unikátny, `published=false` kým nie je naplánovaný čas.
- **Photo** — fotka priradená k Mushroom, MushroomComment alebo BlogPost (nullable FK na každý).
- **MushroomComment** — update/komentár k hríbiku od používateľa.
- **MushroomEditLink** — jednorazový odkaz na editáciu popisu hríbika (OneToOne na Mushroom).
- **User** — admin účet, `ROLE_RELIABLE_THRESHOLD = 20`.

### Blog generovanie (kľúčový flow)
1. `GenerateBlogPostsCommand` — spúšťa sa crono jobom:
   - Najprv publikuje naplánované posty (`publishedAt <= now`)
   - Potom vygeneruje 1 nový post pre hríbik kde `blogPostGenerated = false`
2. `BlogPostGeneratorService` — volá Anthropic API (claude-sonnet-4-6, max 8192 tokenov)
   - Odpoveď NIE JE JSON — používa oddeľovače `===TITLE===`, `===DESCRIPTION===`, `===TAGS===`, `===TEXT===`
   - `parseResponse()` extrahuje sekcie cez regex
   - `fixHtml()` opraví div-y na p/h2 tagy
3. Command tiež skopíruje náhodnú fotku z hríbika do BlogPostu

### Fotky
- Ukladajú sa do `public/uploads/photos/` (gitignorované, len na produkcii)
- `photo.path` = len názov súboru (napr. `abc123.jpg`)
- V šablónach: `asset('uploads/photos/' ~ photo.path)`

### Admin
- EasyAdmin 4 na `/admin`, vyžaduje `ROLE_ADMIN`
- `BlogPostCrudController` má akciu "Zobraziť na webe" → `/blog/{slug}`

### SEO
- Sitemap na `/sitemap.xml` (mushroom detail + blog posty + blog index)
- Blog show šablóna má JSON-LD `BlogPosting` + `BreadcrumbList`
- OG tagy na všetkých verejných stránkach
- Related posts podľa tagov na konci článku

### API
- `ApiKeyListener` validuje `IOS_API_KEY` pre mobilné endpointy
- `BlacklistRequestListener` (priority 255) blokuje IP adresy

## Key config

```yaml
# config/services.yaml
photos_directory: '%kernel.project_dir%/public/uploads/photos'
```

```
# .env (required)
DATABASE_URL=mysql://...
ANTHROPIC_API_KEY=...
IOS_API_KEY=...
```

## Jazykové konvencie
- Komentáre a strings v slovenčine
- Entita "Mushroom" = turistický rozcestník (nie huba)
- "hríbik" = slang pre rozcestník