---
aliases: [Wtyczka adresów, sb24-migracja, Stare adresy]
tags: [url, seo, wordpress, woocommerce]
---

# 🔗 Wtyczka `sb24-migracja` — stare adresy Shopera w WordPressie

Żywa dokumentacja. Realizuje wymaganie nadrzędne projektu: **każdy stary adres działa tak samo**,
bez przekierowań (kierunek C z [[migracja-shoper-woocommerce#Strategia adresów URL]]).
⤴ [[MOC-SEO-i-URL]] · [[MOC-WordPress]]

Kod: `wordpress/wtyczki/sb24-migracja/sb24-migracja.php` (montowany do `wp-content/plugins/`).

## Jak działa

1. **Tabela `wp_sb24_adresy`** — `sciezka` (PK) → `typ` (`product` / `product_cat` / `page` / `post`),
   `obiekt_id`, `shoper_id`. Budowana z meta `_sb24_sciezka` i `_sb24_shoper_id` zostawionych przez
   import ([[import-woocommerce]]):
   ```bash
   skrypty/wp.sh sb24 przebuduj      # po każdym imporcie
   ```
2. **Wejście:** `do_parse_request` szuka ścieżki w tabeli, filtr `request` podmienia zapytanie
   WordPressa na ten obiekt. Adres w przeglądarce się nie zmienia.
3. **Linki:** `post_type_link`, `page_link`, `post_link`, `term_link` → stara ścieżka. Menu, listy
   produktów, canonical i sitemapa pokazują stare adresy.
4. **Paginacja kategorii w formacie Shopera** (`/zloto/2`, strona 1 bez numeru): wejście obsługiwane,
   linki przepisywane w `get_pagenum_link`, `paginate_links` i w HTML-u bloku Product Collection.
5. **Przekierowania 301 (siatka bezpieczeństwa):**
   - `/pl/p|c|i|blog/<cokolwiek>/<id>` → aktualny adres obiektu o tym ID w Shoperze (jak w Shoperze);
   - stary adres z ukośnikiem na końcu → bez ukośnika;
   - `/<kategoria>/page/N` → `/<kategoria>/N`.
6. **Treść 1:1:** wyłączone `wptexturize` (WordPress zamieniał `"…"` na `„…”`) i zamiana
   emotikonów na obrazki (`use_smilies` — teksty Shopera mają dużo `:)`).
7. **Drugi opis kategorii** (`_sb24_opis_dol`, tekst SEO pod produktami) wyświetlany niezależnie od
   motywu: hak `woocommerce_after_shop_loop` (motywy klasyczne) albo po bloku Product Collection
   (motywy blokowe), raz na stronę.

Obiekty bez starego adresu (nowe produkty, nowe wpisy bloga) mają zwykłe adresy WordPressa.

## Pułapki

- ⚠️ **Nie zwracać `false` z `do_parse_request`.** Od WP 6.0 `WP::main()` pomija wtedy całe
  zapytanie — każdy stary adres dawał **200 ze stroną główną**. Pierwsza wersja wtyczki tak miała;
  sam status 200 niczego nie dowodzi, test sprawdza H1.
- Blok Product Collection (WooCommerce) składa linki paginacji sam, z pominięciem filtrów — stąd
  poprawka na wyrenderowanym HTML-u. Po wyborze motywu sprawdzić paginację jeszcze raz.
- `redirect_canonical` jest wyłączane dla trafień z tabeli — inaczej WordPress „poprawia” stare adresy.
- ⚠️ **Yoast SEO dopisuje ukośnik** do adresu każdego obiektu (model indexable, gdy struktura
  linków kończy się na `/`) — mimo filtrów linków. Canonical wskazywał `/naprawa-bizuterii-opole/`,
  który daje 301. Wtyczka zdejmuje ukośnik ze starych adresów w canonical, og:url, schema, sitemapie
  i rel next/prev (`sb24_yoast_adres`). Po zmianie domeny: `skrypty/wp.sh yoast index --reindex`.

## Nowe adresy zamiast domyślnych adresów Shopera

Decyzja właściciela (2026-10-21): te obiekty dostają zwykłe adresy zamiast `/pl/i/…/<id>`, `/pl/c/…/<id>`
(stała `SB24_NOWE_ADRESY` we wtyczce, stosowana przy `wp sb24 przebuduj`):

| Stary adres (Shoper) | Nowy adres | Stary działa jako |
|---|---|---|
| `/pl/i/Salon-firmowy/15` | `/salon-firmowy` | 301 |
| `/pl/c/Indywidualne-projekty/158` (+ `/2`, `/3`…) | `/indywidualne-projekty` (+ `/2`…) | 301 |
| `/pl/i/Kontakt-i-dane-firmy/9` | `/kontakt` | 301 |

Kolejne takie zmiany: dopisać parę do `SB24_NOWE_ADRESY` i `wp sb24 przebuduj`. Nie dla stron z 1. miejsc w Google.

## Test

```bash
node skrypty/strona/sprawdz-adresy.mjs [baza-url]   # domyślnie http://localhost:8080
```

Każdy adres z [[migawka-seo-2026-10-16]] → oczekiwane 200 i ten sam H1 co w Shoperze (dla stron bez
H1 — nazwa z `<title>`). Wynik szczegółowy: `dane/sprawdzenie-adresow.json`. Ten sam test pójdzie
na stagingu i po przełączeniu domeny.

### Wynik 2026-10-16 (lokalnie)

| Typ adresu | OK | Uwagi |
|---|---|---|
| Produkty i kategorie w korzeniu | 1564 | + 19 pominiętych (puste kategorie spod „Biżuteria”, decyzja właściciela) |
| Produkty `/pl/p/…/<id>` | 52 | |
| Paginacja kategorii | 120 | |
| Kategorie `/pl/c/…/<id>` | 2 | + 1 pominięta |
| **Razem sklep** | **1738 / 1738** | |

Po imporcie treści (ten sam dzień): **1785 / 1786** — doszły strona główna, 28 wpisów `/pl/blog/`,
3 wpisy i 2 strony z własnym adresem, 13 / 14 stron `/pl/i/` (Kontakt: Shoper bez H1, własny tytuł —
różnica tylko w teście). 2 puste strony producentów pominięte. Teksty: `sprawdz-teksty.mjs` — 62 / 62.

## Powiązane

[[import-woocommerce]] · [[migawka-seo-2026-10-16]] · [[strony-kluczowe-seo-2026-10-16]] · [[MOC-SEO-i-URL]]
