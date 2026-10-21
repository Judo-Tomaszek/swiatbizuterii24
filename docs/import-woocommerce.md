---
aliases: [Import WooCommerce, Lokalny WordPress]
tags: [woocommerce, wordpress, migracja, wdrozenie]
---

# 🛒 Import do WooCommerce (lokalny WordPress)

Żywa dokumentacja: środowisko lokalne i import katalogu z [[eksport-katalogu]].
⤴ [[MOC-WordPress]] · [[MOC-Migracja]] · [[migracja-shoper-woocommerce]]

## Środowisko

`docker-compose.yml` w korzeniu repo: MariaDB 11.4 + WordPress (PHP 8.3) + WP-CLI (profil `narzedzia`).

| Co | Wartość |
|---|---|
| Sklep | http://localhost:8080 — panel `/wp-admin` |
| Login / hasło | `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` w `.env` |
| Wersje (2026-10-16) | WordPress 7.1.3, WooCommerce 11.2.1, język pl_PL |
| WP-CLI | `skrypty/wp.sh <polecenie>` (np. `skrypty/wp.sh plugin list`) |

- Baza **nie jest wystawiona** na zewnątrz — port 3306 zajmuje lokalny XAMPP.
- Kontener WP-CLI widzi `dane/eksport` jako `/import/dane` (tylko odczyt) i `skrypty/import` jako `/import/skrypty`.
- Własna wtyczka `wordpress/wtyczki/sb24-migracja/` jest montowana do `wp-content/plugins/` (na razie pusta — tu trafi obsługa starych adresów).
- ⚠️ Obraz `wordpress` daje tylko pliki startowe; wersję WP w wolumenie podnosi `skrypty/wp.sh core update`.
  Obraz 6.8 był za stary dla WooCommerce 11 (wymaga WP 7.0).
- ⚠️ `skrypty/wp.sh` ustawia `MSYS_NO_PATHCONV=1` — bez tego Git Bash zamienia `/import/...`
  na `C:/Program Files/Git/import/...`.
- Każde wywołanie WP-CLI to nowy kontener — `/tmp` nie przeżywa między wywołaniami.
- Dane przeżywają restart komputera (wolumeny `db`, `wp`; `restart: unless-stopped`).

Ustawienia sklepu: PLN, Polska (opolskie), separator tysięcy spacja, przecinek dziesiętny,
waluta po kwocie, ceny z VAT, zarządzanie stanem włączone, brakujące produkty **widoczne**, waga w g.

## Import

```bash
skrypty/wp.sh eval-file /import/skrypty/importuj-katalog.php kategorie
skrypty/wp.sh eval-file /import/skrypty/importuj-katalog.php produkty [offset] [limit] [bez-zdjec]
```

Idempotentny — kategorie i produkty po `_sb24_shoper_id`, warianty po `_sb24_stock_id`, zdjęcia
po `_sb24_gfx_id`. Przerwany import wznawia się od `offset`. Pełny przebieg produktów: ~13 min.

**Zakres (decyzja właściciela 2026-10-16):** do WordPressa idą tylko **aktywne** produkty —
`aktywne-na-stanie` i `aktywne-stan-0` (1595). Nieaktywne zostają wyłącznie w eksporcie.

### Odwzorowanie

| Shoper | WooCommerce |
|---|---|
| Kategoria aktywna (z rodzicami) | `product_cat` z rodzicem; slug = `seo_url`; opis = `opis_gora`; meta `_sb24_opis_dol`, `order` |
| Kategoria wyłączona | nie importowana; produkt dostaje **najbliższą aktywną kategorię nadrzędną** |
| Produkt bez wariantów / z wariantami | `simple` / `variable` |
| Opcja wariantu (`rozmiar`, `rozmiary`, `opcja`) | globalny atrybut wariantu `pa_rozmiar` / `pa_opcja`; pojedyncza cecha „Rozmiar” znika z wariantowych |
| Cechy o powtarzalnych wartościach (Złoto, Kolor złota, Kamienie, Rodzaj zapięcia, Rozmiar, Długość, Srebro, Stal…) | atrybuty globalne `pa_*` — gotowe pod filtry |
| Cechy per produkt (Kod produktu, Waga, Szerokość, Wysokość) | atrybuty lokalne produktu |
| Stan 0 | „Brak w magazynie”, **bez zakupu** (`backorders = no`) — jak „brak towaru” w Shoperze |
| Kod | SKU (unikalne w danych) |
| Zdjęcia | biblioteka mediów; główne → obrazek produktu, reszta → galeria; alt z Shopera; ukryte pominięte |
| Title / meta / H1 z migawki, stary adres | meta `_sb24_seo_title`, `_sb24_seo_description`, `_sb24_h1`, `_sb24_sciezka` (pod wtyczkę SEO i adresów) |

## Wynik (2026-10-16)

| Miara | Wynik |
|---|---|
| Kategorie | 27 (pominięte wyłączone: 115) |
| Produkty | **1595** — 1534 proste, 61 wariantowych (268 wariantów) |
| Stan | 607 na stanie, 988 „brak w magazynie” |
| Zdjęcia | 4223 załączniki, 0 produktów bez zdjęcia |
| Atrybuty globalne | 13 |
| Weryfikacja z eksportem | **1595 / 1595 produktów i 268 / 268 wariantów — cena i stan zgodne** |
| Ostrzeżenia importu | 0 |

## Treści: strony, blog, strona główna

```bash
node skrypty/eksport/buduj-tresci.mjs                                     # → dane/eksport/tresci/ (+ pobiera obrazki)
skrypty/wp.sh eval-file /import/skrypty/importuj-tresci.php wszystko     # obrazy | strony | blog | glowna
skrypty/wp.sh sb24 przebuduj
```

| Shoper | WordPress |
|---|---|
| 16 stron informacyjnych (`aboutpages`), w tym naprawa biżuterii i skup złota | strony (`page`), treść z API, adres przez [[adresy-url-wtyczka]] |
| 31 wpisów bloga (`news`) | wpisy (`post`), zajawka → wyciąg, tagi (16 wpisów), zdjęcie główne z `/userdata/public/news/images/` |
| Lista bloga `/pl/blog` | strona „Blog” = strona wpisów (`page_for_posts`) |
| Strona główna: H1 „Jubiler Opole”, 7 produktów „na stronie głównej”, moduły `box_custom46` i `box_custom51` | strona „Jubiler Opole” jako strona startowa: `[products ids=… orderby="post__in"]` + oba moduły w blokach HTML 1:1; te 7 produktów ma flagę „wyróżniony” |
| 100 obrazków wklejonych w treści (`/userdata/public/assets/…`) | biblioteka mediów (meta `_sb24_zrodlo`), adresy w treściach podmienione — po wyłączeniu Shopera stare by nie działały |

- **Blog nie ma dat** (API `0000-00-00`, strona ich nie pokazywała): wpis dostaje datę importu
  przesuniętą o ID (kolejność jak w Shoperze) i meta `_sb24_brak_daty`. Motyw nie powinien pokazywać
  dat tych wpisów — do decyzji przy motywie.
- **Regulamin** w Shoperze to widżet zewnętrznej usługi (`<div id="rr_xss_cert">`, „rzetelny
  regulamin”) — tekstu nie ma ani w API, ani w HTML-u. Strona istnieje pod starym adresem, **treść
  do dostarczenia przez właściciela** (i tak potrzebny nowy regulamin: inne płatności i dostawa).
- **Producenci** (`/future-design`, `/traditional-way`) — 0 aktywnych produktów, puste strony;
  nieprzenoszeni (jak puste kategorie, decyzja właściciela).

### Weryfikacja treści (2026-10-16)

- `node skrypty/strona/sprawdz-adresy.mjs` — **1785 / 1786** adresów z migawki: 200 i ten sam H1
  (+ 22 celowo pominięte). Jedyny „inny H1”: Kontakt — Shoper nie miał tam H1, a tytuł był własny
  („Kontakt | Jubiler Opole”); nowy H1 = nazwa strony. Bez znaczenia.
- `node skrypty/strona/sprawdz-teksty.mjs` — **62 / 62 teksty 1:1** (strony, blog, strona główna,
  opisy kategorii górne i dolne; 41 pól pustych już w Shoperze). Test wyłapał dwie zmiany robione
  przez WordPress — emotikony `:)` → obrazki i brak dolnego opisu kategorii — obie naprawione
  we wtyczce.

## Otwarte

1. ✅ **Produkt 2684 „Złoty pierścionek ozdobiony cyrkoniami”** — był tylko w wyłączonym
   „Biżuteria > Pierścionki”, przeniesiony do **Złote pierścionki** (decyzja właściciela 2026-10-16;
   na stałe w `SB24_KATEGORIE_RECZNE` w skrypcie importu).
2. ✅ **20 włączonych kategorii pod wyłączonym „Biżuteria”** (`/kolczyki`, `/pierscionki`,
   `/lancuszki`, `/bransoletki`, `/na-komunie`, `/chrzest`…) — puste strony („brak produktów”),
   niewidoczne w menu, dostępne tylko z sitemapy. **Decyzja właściciela 2026-10-16: nie importujemy,
   bez przekierowań, wypadają z sitemapy** — porządkujemy. Po przełączeniu te adresy dadzą 404
   i Google je usunie. W nowej sitemapie ich nie będzie (nie istnieją w WordPressie); w obecnej
   sitemapie Shopera znikną dopiero po wyłączeniu ich w panelu Shopera (opcjonalnie, ręcznie).
3. Powiązane produkty (3 produkty w Shoperze) zapisane w `_sb24_powiazane` — niepodpięte.
4. Wtyczka SEO niewybrana — title/meta czekają w meta `_sb24_*`.
5. Brak obsługi starych adresów — następny krok (wtyczka `sb24-migracja`).

## Powiązane

[[eksport-katalogu]] · [[migawka-seo-2026-10-16]] · [[migracja-shoper-woocommerce]] · [[MOC-WordPress]]
