---
aliases: [Sondaż API Shopera, Co da się zaimportować]
tags: [shoper-api, migracja, url, seo]
---

# 🔍 Sondaż API Shopera — co da się przenieść (stan na 2026-10-16)

Pierwsze połączenie z API (token + client_id z `.env`, **tylko GET**). Liczności, dostępne pola,
luki. **Fotografia stanu** — nie aktualizujemy wstecz. ⤴ [[MOC-Migracja]] · [[migracja-shoper-woocommerce]]

Skrypty: `skrypty/shoper/klient.mjs` (klient GET z odstępem 550 ms i obsługą 429),
`sondaz.mjs` (liczności + próbki → `dane/sondaz/`), `pobierz-tresci.mjs` (komplet produktów,
kategorii, stron, bloga, producentów → `dane/surowe/`). Uruchamianie: `node --env-file=.env skrypty/shoper/<plik>`.

## Liczności

| Zasób | Ile | Uwagi |
|---|---|---|
| `products` | 4030 | **aktywne: 1595**; 1590 z nich w sitemapie, nieaktywnych w sitemapie 0 |
| `categories` | 142 | aktywne 47, z opisem 26; drzewo w `categories-tree` (6 korzeni) |
| `product-images` | 8880 | oryginały pobieralne: `/userdata/public/gfx/<gfx_id>/<nazwa>.<ext>` |
| `product-stocks` | 5154 | stany i ceny (także wariantów) |
| `attributes` / `attribute-groups` | 32 / 6 | aktywne produkty używają 31 atrybutów (próba, waga, zapięcie, kamień, kolor złota…) |
| `options` / `option-values` / `option-groups` | 1384 / 11634 / 924 | warianty ma **61** aktywnych produktów |
| `aboutpages` | 16 | strony informacyjne, w tym **Naprawa biżuterii (14)** i **Skup złota (17)** |
| `news` | 31 | blog; **Kształty diamentów = id 16** (26,8 tys. znaków) |
| `news-categories` / `news-tags` | 20 / 20 | |
| `producers` | 2 | Future Design, Traditional Way — oba z własnym adresem |
| `deliveries` / `shippings` / `payments` / `taxes` / `units` / `statuses` | 8 / 5 / 26 / 6 / 4 / 11 | konfiguracja — odtworzymy ręcznie w WooCommerce |
| `users` / `orders` | 498 / 46 | dostępne; **pobrana tylko liczba** (dane osobowe) |
| `news-comments`, `redirects`, `gauges`, `product-files`, `user-groups` | 0 | puste |

**Brak uprawnień tokenu (403):** `availabilities`, `currencies`, `languages`, `specialoffers`,
`subscribers`. `metafields` → 500, `product-comments` → moduł wyłączony.
Dla migracji liczy się realnie `availabilities` (etykiety dostępności) i `specialoffers` (promocje) —
do rozszerzenia uprawnień, jeśli będą potrzebne.

## Adresy URL — pokrycie pełne

- Produkty i kategorie mają w API `seo_url` i **gotowy `permalink`** — mapę adresów budujemy wprost z API.
- Aktywne produkty: 1543 z własnym adresem w korzeniu, **52 bez** → adres `/pl/p/<Nazwa>/<id>`.
- `news` i `aboutpages` mają `seo_url` tylko gdy ustawiony (np. `naprawa-bizuterii-opole`,
  `jak-czyscic-kolczyki-aby-lsnily-jak-nowe`); reszta → `/pl/blog/<Tytul>/<id>` i `/pl/i/<Tytul>/<id>`.
- **Sitemap: 1807 adresów** (`/console/integration/execute/name/GoogleSitemap`, indeks 8 podmap):
  1590 w korzeniu, 52 `/pl/p/`, 3 `/pl/c/`, 14 `/pl/i/`, 28 `/pl/blog/` + **paginacja kategorii**
  (`/zloto/2` … `/zloto/31`, `/pierscionki-zareczynowe/2` … `/26` itd.).
- **Każdy adres z sitemapy ma swój obiekt w API** — brak sierot.
- Slugi bez kolizji: zero zdublowanych `seo_url` produktów, zero kolizji między typami.
- Shoper sam przekierowuje `/pl/p/<cokolwiek>/<id>` → 301 na właściwy adres produktu. Ten sam
  mechanizm warto odtworzyć (siatka bezpieczeństwa dla starych linków zewnętrznych).
- Nieaktywny produkt (np. id 34) nadal odpowiada 200 pod swoim adresem — do decyzji, co z 2435
  nieaktywnymi (patrz niżej).

## 🔴 Pułapka: title i meta z API ≠ to, co widzi Google

Pola `seo_title` / `seo_description` są w API **przeważnie puste** (kategorie: tytuł ma 2 / 142,
produkty: 0 / 1595), a strona i tak ma tytuł — **Shoper skleja go z szablonu**:

| Adres | `seo_title` w API | `<title>` na stronie |
|---|---|---|
| `/obraczki` | *(pusty)* | Obrączki  \| Jubiler Opole \| Swiatbiżuterii24 |
| `/srebrne-kolczyki` | *(pusty)* | Srebrne kolczyki  \| Jubiler Opole \| Swiatbiżuterii24 |
| `/pl/blog/Ksztalty-diamentow/16` | *(pusty)* | Blog - Kształty diamentów \| Jubiler Opole \| Swiatbiżuterii24 |

Meta description podobnie (`/obraczki` → „Lista produktów kategorii Obrączki”, generowana).

**Wniosek: źródłem prawdy dla title, meta, canonical i H1 jest wyrenderowana strona, nie API.**
Potrzebny przebieg po wszystkich 1807 adresach z sitemapy (z limitem `Crawl-delay: 1` z robots.txt,
ok. 30 min), zapisujący title / description / canonical / H1 / robots per adres.
Treść (opisy) bierzemy z API; metadane — z tego przebiegu.

## 🔴 Pułapka: tekst strony głównej nie istnieje w API

Tekst SEO strony głównej („Dla każdej świadomej mody kobiety…”, „Jubiler Opole udowadnia…”)
siedzi w **modułach szablonu**, nie w `aboutpages` ani innym zasobie. Bierzemy go z HTML-a
strony z zachowaniem nagłówków H1–H3 i kolejności akapitów.

## Treść — co jest

- **Kategorie:** dwa pola treści — `description` (nad produktami) i `description_bottom` (pod
  produktami). Kluczowe kategorie mają oba (np. `/pierscionki-zareczynowe`: 1,7 + 6,5 tys. znaków).
  WooCommerce ma natywnie jeden opis kategorii — drugie pole trzeba obsłużyć (meta terminu + motyw).
- **Produkty:** opis HTML ma 1593 / 1595 aktywnych; zawiera znacznik edytora `<!--[mode:tiny]-->`
  do wycięcia.
- **Blog:** wszystkie wpisy mają `date = 0000-00-00` poza ostatnim (2025-11-19) — **daty publikacji
  nie przeniesiemy z API**; do sprawdzenia, czy strona je wyświetla (wtedy z HTML-a).
- **Zdjęcia:** oryginały z `/userdata/public/gfx/…`, nazwa i kolejność w `product-images`.

## Do decyzji

1. **Nieaktywne produkty (2435)** — nie importować / importować jako szkice (historia, ewentualny
   powrót do oferty) / importować tylko te, które mają ruch lub linki (Search Console).
2. **Klienci (498) i zamówienia (46)** — przenosić? Hasła i tak nie przejdą (reset przy pierwszym logowaniu).
3. **Paginacja kategorii** (`/zloto/2`) — odtworzyć ten sam format w WordPressie (`/zloto/page/2` jest
   natywny) czy 301 → strona 1. Rekomendacja: zachować format, koszt niewielki w tej samej wtyczce.
4. ~~Rozszerzenie uprawnień tokenu~~ — **niepotrzebne** (decyzja 2026-10-16): w Shoperze nie ma
   żadnych promocji (potwierdził właściciel; pole `promo_price` puste we wszystkich aktywnych
   produktach), a etykiety dostępności są wyliczane ze stanu (`availability_id` = null wszędzie;
   `calculated_availability_id` 2 ≈ stan > 0 — 607 vs 608, 1 = brak stanu — 987, 4 — 1 produkt).
   W WooCommerce odtworzymy napisy ręcznie, wg stanu.

## Priorytety właściciela (2026-10-16)

Oprócz tekstów, title i meta stron kluczowych ([[strony-kluczowe-seo-2026-10-16]]) najważniejsze
do przeniesienia: **produkty, kategorie, opisy i zdjęcia**.

## Powiązane

[[migracja-shoper-woocommerce]] · [[strony-kluczowe-seo-2026-10-16]] · [[MOC-Migracja]] · [[MOC-SEO-i-URL]]
