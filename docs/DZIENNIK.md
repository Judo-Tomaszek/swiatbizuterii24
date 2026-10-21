---
aliases: [Dziennik, Kronika prac, Log prac]
tags: [dziennik]
---

# 📓 DZIENNIK prac — swiatbizuterii24

Chronologiczna kronika pracy nad projektem. **Jeden plik, wpisy dopisywane na końcu**, nigdy w środku.
Format nagłówka jest sztywny, bo z niego generuje się indeks:

```
## §N — RRRR-MM-DD — Tytuł
```

Indeks: [[SPIS-DZIENNIKA]] · Zasady: [[KONWENCJA-DOKUMENTACJI]] · Pulpit: [[HOME]]

---

## §1 — 2026-10-16 — Start projektu: cel migracji i system dokumentacji

Wpis otwierający. Katalog projektu był pusty — §1 zapisuje cel i założenia, od kolejnego wpisu
kronika idzie na bieżąco.

### Cel

Sklep **swiatbizuterii24.pl** działa dziś na **Shoperze**. Przenosimy go na **WordPress +
WooCommerce**: dane pobieramy przez API Shopera i przygotowujemy do importu do WooCommerce.

### Założenia od właściciela

- **Bardzo ważne: zachowanie struktury linków stron sprzedażowych.** Blog — też, jeśli się da.
  Stąd zasada nadrzędna w `CLAUDE.md` i [[MOC-SEO-i-URL]]: każdy stary adres działa albo daje 301
  na swój odpowiednik.

### Decyzje

- **System pracy i dokumentacji skopiowany z `club-manager-api`** (prośba właściciela): vault
  Obsidian w repo, `CLAUDE.md` z rytuałem start/koniec sesji, jeden [[DZIENNIK]] z wpisami `§N`,
  generowany [[SPIS-DZIENNIKA]], pulpit [[HOME]], mapy tematyczne, plany w `docs/plany/`.
- Mapy tematyczne dopasowane do projektu: [[MOC-Migracja]], [[MOC-SEO-i-URL]], [[MOC-WordPress]],
  [[MOC-Wdrozenie]] (zamiast backend/frontend/moduły z tamtego projektu).
- Generator spisu jako `docs/_narzedzia/gen-spis.mjs` (rozszerzenie `.mjs`, bo projekt nie ma
  `package.json` z `"type": "module"`).
- Surowe eksporty (`dane/`) i `.env` poza gitem — w eksporcie będą dane osobowe klientów.

### Dotknięte pliki

`CLAUDE.md`, `.gitignore`, `.obsidian/app.json`, `.obsidian/core-plugins.json`, `docs/HOME.md`,
`docs/KONWENCJA-DOKUMENTACJI.md`, `docs/DZIENNIK.md`, `docs/SPIS-DZIENNIKA.md`, `docs/MOC-*.md`,
`docs/_narzedzia/gen-spis.mjs`, `docs/plany/migracja-shoper-woocommerce.md`.

### W toku

1. Plan [[migracja-shoper-woocommerce]] to **szkic** — wzorce adresów Shopera są założeniem do
   weryfikacji na żywym sklepie.
2. Następny krok: dostęp do API Shopera (etap 0) i inwentaryzacja adresów (etap 1).
3. Repozytorium git jeszcze niezałożone.

Powiązania: [[HOME]], [[migracja-shoper-woocommerce]], [[KONWENCJA-DOKUMENTACJI]].

## §2 — 2026-10-16 — Strony kluczowe SEO, sondaż API Shopera, migawka 1808 adresów i eksport katalogu

Pierwsze połączenie z API i komplet danych katalogu na dysku. Nic nie zostało zapisane w Shoperze —
wszystkie skrypty robią wyłącznie GET.

### Strony kluczowe (priorytety właściciela)

- **1:1 bez poprawek** (1. miejsca w Google): strona główna, `/naprawa-bizuterii-opole`,
  `/skup-zlota-opole`, kategorie `/zlote-kolczyki`, `/srebrne-kolczyki`, `/obraczki`,
  `/pierscionki-zareczynowe`, blog `/pl/blog/Ksztalty-diamentow/16` — tekst, title, meta, adres.
  Lista z tytułami i opisami z tego dnia: [[strony-kluczowe-seo-2026-10-16]].
- Literówki w meta („Odwieź”, „naprawia biżuterii”) **zostają** — poprawka to osobna decyzja po
  ustabilizowaniu pozycji, nie część migracji.
- Reszta bloga: stare adresy i tekst; **nowe wpisy mogą mieć strukturę WordPressa** (zgoda właściciela).
- Najważniejsze do przeniesienia poza tym: **produkty, kategorie, opisy, zdjęcia**.

### Ustalenia o sklepie (sprawdzone, nie założone)

- **Dwa schematy adresów naraz**: własne w korzeniu (`/zlote-kolczyki`, produkty, strony) i domyślne
  z ID (`/pl/p/…/5355`, `/pl/c/…/158`, `/pl/i/…`, `/pl/blog/…`). W korzeniu mieszają się typy, więc
  natywne permalinki WooCommerce odpadają → kierunek **C**: tabela „stara ścieżka → obiekt” we własnej
  wtyczce dla wszystkiego, co istnieje, nowe treści natywnie. Plan: [[migracja-shoper-woocommerce]].
- Sitemap pod `/console/integration/execute/name/GoogleSitemap` (`/sitemap.xml` → 404), 1807 adresów
  + 120 stron paginacji kategorii w środku (`/zloto/2`…) — format paginacji do zachowania.
- **API nie zna title/meta, które widzi Google** — pola SEO przeważnie puste, Shoper skleja je
  z szablonu. Stąd decyzja: **źródłem prawdy dla metadanych jest migawka stron**, treść — z API.
- **Tekst strony głównej nie istnieje w API** (moduły szablonu) — weźmiemy go z HTML-a w migawce.
- Kategorie mają dwa teksty (`description` nad produktami, `description_bottom` pod) — WooCommerce ma
  jedno pole; drugie trzeba obsłużyć w meta i w motywie. Wpływa na wybór motywu.
- Blog nie ma dat ani na stronie, ani w API (`0000-00-00`).
- Token: brak uprawnień do `availabilities`, `specialoffers` i kilku innych — **rozszerzenie
  niepotrzebne**: promocji nie ma (właściciel), etykiety dostępności Shoper wylicza ze stanu.

### Eksport

- [[sondaz-api-shoper-2026-10-16]] — liczności i luki API.
- [[migawka-seo-2026-10-16]] — 1808 adresów, wszystkie 200; title/meta/canonical/H1 + pełny HTML
  (51 MB) jako wzorzec do porównania po migracji. Zastane: 459 stron ze zdublowanym tytułem, blog
  i strony `/pl/i/` bez H1 — notujemy, nie naprawiamy przy migracji.
- [[eksport-katalogu]] — 4030 produktów w 4 grupach (decyzja właściciela: na pewno importujemy
  **aktywne ze stanem > 0 — 607**, reszta do ustalenia): 988 aktywnych bez stanu, **1696 nieaktywnych
  ze stanem** (warte przejrzenia), 739 nieaktywnych bez stanu. 8880 zdjęć (269 MB, 0 błędów),
  142 kategorie z obydwoma opisami. Podział **nie** wg kategorii (produkt bywa w kilku) — kategoria
  jest kolumną w CSV.

### Pułapka: cena wariantu z API

Przy `price_type = 0` („cena jak produkt”) pole `comp_price` wariantu bywa nieaktualne — produkt 2515:
wariant 1049 zł, strona 1289 zł. Bez poprawki 60 produktów z wariantami weszłoby ze złymi cenami.
Poprawione w `buduj-katalog.mjs`; weryfikacja z `itemprop="price"` w migawce: 1590 / 1590 cen
produktów i 268 / 268 wariantów zgodnych.

### Dotknięte pliki

`.env.example`, `.env` (poza gitem), `CLAUDE.md`, `skrypty/shoper/{klient,sondaz,pobierz-tresci}.mjs`,
`skrypty/strona/migawka-seo.mjs`, `skrypty/eksport/{buduj-katalog,pobierz-zdjecia}.mjs`,
`docs/{strony-kluczowe-seo-2026-10-16,sondaz-api-shoper-2026-10-16,migawka-seo-2026-10-16,eksport-katalogu}.md`,
`docs/plany/migracja-shoper-woocommerce.md`, `docs/HOME.md`, `docs/MOC-*.md`. Dane: `dane/` (poza gitem).

### W toku

1. **Decyzje właściciela:** które grupy poza `aktywne-na-stanie`; klienci (498) i zamówienia (46);
   co ze starym, wyłączonym drzewem „Biżuteria” (~100 kategorii — część adresów może być w Google).
2. Następny krok: mapowanie pól Shoper → WooCommerce (produkty, warianty, atrybuty, kategorie z dwoma
   opisami, zdjęcia), potem blog i strony informacyjne, tekst strony głównej z HTML-a.
3. Motyw — w drugiej kolejności (właściciel); wymaganie z eksportu: drugi opis kategorii.
4. Repozytorium git nadal niezałożone.

Powiązania: [[migracja-shoper-woocommerce]], [[eksport-katalogu]], [[migawka-seo-2026-10-16]],
[[sondaz-api-shoper-2026-10-16]], [[strony-kluczowe-seo-2026-10-16]], [[DZIENNIK#§1 — 2026-10-16 — Start projektu: cel migracji i system dokumentacji|§1]].

## §3 — 2026-10-16 — Lokalny WooCommerce: import katalogu i treści, wtyczka starych adresów

Sklep stoi lokalnie (Docker, http://localhost:8080) z całym aktywnym katalogiem i treściami;
każdy adres z Shopera działa bez przekierowania, teksty są słowo w słowo.

### Decyzje właściciela

- **Z Shopera pobieramy wszystko, do WordPressa idą tylko aktywne produkty** — także bez stanu
  (1595). Dociągnięci też klienci (498), adresy (403), zamówienia (46) i ich pozycje — tylko lokalnie
  w `dane/surowe/prywatne/`; do WordPressa na razie nie idą.
- Stan 0 = „Brak w magazynie” **bez zakupu** — tak działa dziś Shoper („brak towaru”, „powiadom
  o dostępności”; sprawdzone w HTML-u migawki), więc nic nie zmieniamy.
- Produkt 2684 (tylko w wyłączonym „Biżuteria > Pierścionki”) → **Złote pierścionki**.
- **Puste strony wypadają:** 20 włączonych kategorii spod wyłączonego drzewa „Biżuteria” i 2 strony
  producentów (0 aktywnych produktów) — bez importu, bez przekierowań, poza nową sitemapą. Po
  przełączeniu dadzą 404 i znikną z Google.

### Zrobione

- Środowisko: `docker-compose.yml`, `skrypty/wp.sh`; WordPress 7.1.3, WooCommerce 11.2.1, pl_PL.
  [[import-woocommerce]].
- Import katalogu (`skrypty/import/importuj-katalog.php`, idempotentny po ID z Shopera): 27 kategorii
  z hierarchią, 1595 produktów (61 wariantowych, 268 wariantów), 4223 zdjęcia, 13 atrybutów
  globalnych pod filtry. **Ceny i stany 1595 / 1595 + 268 / 268 zgodne z eksportem.**
- Wtyczka `sb24-migracja` — [[adresy-url-wtyczka]]: tabela „stara ścieżka → obiekt”, linki
  i paginacja (`/zloto/2`) w formacie Shopera, 301 dla `/pl/p/<cokolwiek>/<id>`, dolny opis kategorii
  pod produktami, teksty bez przeróbek WordPressa.
- Treści (`skrypty/eksport/buduj-tresci.mjs` + `skrypty/import/importuj-tresci.php`): 16 stron,
  31 wpisów bloga z tagami i zdjęciami, `/pl/blog` jako lista wpisów, strona główna (H1, 7 wyróżnionych
  produktów, 2 moduły tekstu z HTML-a migawki), 100 obrazków z tekstów do biblioteki mediów.
- Testy na całości (do powtórzenia na stagingu i po przełączeniu): `sprawdz-adresy.mjs` —
  **1785 / 1786** adresów z migawki 200 + ten sam H1 (Kontakt: Shoper bez H1, różnica tylko w teście);
  `sprawdz-teksty.mjs` — **62 / 62 teksty 1:1**.

### Pułapki (każda kosztowała czas)

- **`do_parse_request` → `false` w WP 6+ pomija zapytanie** — każdy stary adres dawał 200 ze stroną
  główną. Status 200 niczego nie dowodzi; dlatego test porównuje H1, a nie kod odpowiedzi.
- **Blok Product Collection** składa linki paginacji sam, z pominięciem filtrów — poprawka na HTML-u bloku.
- **WordPress po cichu zmienia tekst:** `"…"` → `„…”` (wptexturize) i `:)` → obrazki emoji —
  wyłączone, wyłapane dopiero testem tekstów.
- **Cena wariantu z API** (z §2) — po imporcie potwierdzona zgodność.
- WooCommerce 11 wymaga WP 7.0 — obraz Dockera 6.8 za stary, `wp core update` w wolumenie.
- Git Bash przerabia `/import/...` na `C:/Program Files/Git/...` → `MSYS_NO_PATHCONV=1` w `wp.sh`.
- Test adresów: `decodeURI` zostawia `%2C`, a `encodeURI` robi z niego `%252C` — fałszywe 301.
- Regulamin w Shoperze to widżet zewnętrzny (`rr_xss_cert`) — tekstu nie ma nigdzie.

### W toku

1. **Właściciel:** regulamin (strona jest, treść pusta); wybór motywu.
2. Wtyczka SEO — title/meta czekają w meta `_sb24_*` (WordPress pokazuje na razie własne tytuły);
   sitemap bez pustych stron.
3. Motyw: wymagania z migracji — brak dat na blogu, H1 strony bez dublowania (naprawa biżuterii ma
   H1 w treści), dolny opis kategorii (wtyczka już go wstawia), filtry po atrybutach.
4. Hosting, staging, przełączenie domeny; repozytorium git nadal niezałożone.

Powiązania: [[import-woocommerce]], [[adresy-url-wtyczka]], [[eksport-katalogu]],
[[migracja-shoper-woocommerce]], [[DZIENNIK#§2 — 2026-10-16 — Strony kluczowe SEO, sondaż API Shopera, migawka 1808 adresów i eksport katalogu|§2]].

## §4 — 2026-10-18 — Wygląd 1:1 z Shoperem, banery, blog po WordPressowemu, poprawa zdjęć produktów

Sklep lokalny wygląda już jak Shoper w kluczowych miejscach (główna, kategorie, strony usług), ma
banery w nagłówkach i bloga w strukturze WordPressa. W tle trwa poprawa małych zdjęć produktów.

### Decyzje właściciela

- **Blog po WordPressowemu** (2026-10-17): adresy pomocnicze Shopera (kategorie bloga, paginacja) są
  pomieszane i bez wartości w Google — nie odtwarzamy ich, najwyżej 301. **Wpisy zostają 1:1**
  (zwłaszcza `/pl/blog/Ksztalty-diamentow/16`). Permalinki nowych: `/blog/%postname%/`,
  kategorie `blog/kategoria`, tagi `blog/temat`.
- **Strona główna: 8 produktów zamiast 7** — w siatce 4 kolumn 7 wyglądało niesymetrycznie;
  dopełnienie: najnowsze złote kolczyki z cyrkonią (Shoper ID 5355).
- **Banery generowane przez ChatGPT** (Codex CLI) w stylu zdjęcia z głównej: ciemna śliwka, biżuteria
  po prawej, pusta lewa strona pod H1, bez napisów i ludzi. 26 kategorii + 7 stron
  (naprawa, skup złota, salon, blog, o firmie, kontakt, wspólny dla pozostałych stron informacyjnych);
  projekty indywidualne dzielą baner z kategorią.
- **Zdjęcia produktów — jedna metoda na raz, decyzja po porównaniu:** ChatGPT (wierne przerysowanie
  w wyższej rozdzielczości) i Real-ESRGAN (lokalny upscaler) na wszystkich 937 małych zdjęciach,
  potem PDF „oryginał | ESRGAN | ChatGPT”. **Nic jeszcze nie podmienia zdjęć w WordPressie.**

### Zrobione

- **Strona główna** jak w Shoperze: zdjęcie `main-foto.jpg`, pasek 5 zalet z ikonami skórki, H1 +
  polecane produkty, blok tekstu, 4 kafle kategorii, 2 banery, mapa, drugi blok tekstu
  (`importuj-tresci.php glowna`, `glowna.css`). Kolejność polecanych jak w HTML-u migawki.
- **Kategorie:** baner w nagłówku, górny opis zwijany („Czytaj więcej”, cały tekst zostaje w HTML-u
  dla SEO), filtry w lewej kolumnie (widżety WooCommerce: kategorie, Złoto, kamienie, kolor złota,
  zapięcie, długość, cena) — `kategorie.css/js`, `ustaw-wyglad.php`.
- **FAQ jako harmonijka** (CSS 1:1 ze skórki Shopera, `faq.css`) — pytania otwierają się na
  pierścionkach zaręczynowych, skupie złota i Kształtach diamentów.
- **Style treści z Shopera** (`tresci.css`): siatka `f-row/f-grid-*`, naprawa biżuterii (ikony w ramkach,
  przycisk na zdjęciu warsztatu), trzy kroki skupu złota, tabele `.sb`, godziny salonu. Źródło:
  CSS użytkownika z `skin.css` Shopera (`tmp/shoper-css/`).
- **Banery** (`banery-kategorii.mjs [--strony]` → `importuj-tresci.php banery`; meta `_sb24_baner`
  kategorii/stron, opcja `sb24_baner_stron`); wspólny `baner.css` + `sb24_baner()` we wtyczce.
- **Blog:** działa paginacja `/pl/blog/page/N`, karty wpisów, prawy pasek (szukaj, salon, najnowsze,
  kolekcje, tematy) — `blog.css`; kolejność wpisów jak w Shoperze.
- **Przekierowania systemowe Shopera:** `/pl/reg|login|panel` → Moje konto, `/pl/basket` → koszyk,
  `/pl/blog/category|tag/…` → lista bloga (301); wyłączone zgadywanie adresów przez WordPress.
- **Zdjęcia:** Real-ESRGAN — 937 / 937 (`dane/poprawa-zdjec/esrgan/`); ChatGPT — 647 / 937
  (`dane/poprawa-zdjec/chatgpt/`), skrypt czeka sam na odnowienie limitu. Skrypty w
  `skrypty/zdjecia/` (`popraw-chatgpt.mjs`, `popraw-esrgan.mjs`, `porownanie-pdf.py`).
- Testy po zmianach: teksty **62 / 62**, adresy **1785 / 1786** (Kontakt — znana różnica z §3).

### Pułapki

- **Tryb „wkrótce otwarcie” WooCommerce** pokazuje gościom zaślepkę — test tekstów dał 14 fałszywych
  różnic. Testy przyjmują teraz ciasteczko zalogowanego (`SB24_COOKIE`, `tmp/cookie.txt`). Ciasteczko
  wygasa — gdy test nagle pokazuje różnice w kategoriach, najpierw odnowić je
  (`wp eval` + `wp_generate_auth_cookie(1, time()+30*DAY_IN_SECONDS, 'logged_in')`).
- **kses wycina `<input>`**, a wpautop owija go w `<p>` — harmonijka FAQ się nie otwierała
  (`input:checked+.box-faq`). Poprawka: `sb24_kses_opis()` + `sb24_faq_bez_akapitow()`.
- **Bezpańskie komentarze Gutenberga** w treściach z Shopera (`<p><!-- wp:heading --></p>`) mieszały
  układ wpisu 16 — usuwane przy imporcie (`sb24t_bez_blokow_wp()`).
- `/pl/reg` dawało 301 na Regulamin — **zgadywanie adresów 404 przez WordPress**; wyłączone.
- Reguła „`/pl/blog/x/<id>` → wpis” łapała `/pl/blog/page/2`.
- Git Bash zepsuł strukturę permalinków na `/C:/Program Files/Git/%postname%/` — ustawiać przez `wp eval`.
- **Codex przy limicie obrazków kończy się kodem 0** — o limicie świadczy tylko brak pliku i tekst
  „usage limit”; `resets_in_seconds` jest tylko w logach. Limit ChatGPT Plus ok. 150 obrazków/okno.
- ChatGPT potrafi „dorobić” fakturę (plecionka na gładkiej skórze) i podbić kolory — prompt ma teraz
  twarde zakazy; mimo to porównanie w PDF jest obowiązkowe przed podmianą.
- Kadence ustawia `min-height` nagłówka stron mocniejszym selektorem — baner potrzebuje `.entry-hero.entry-hero`.
- Obraz WP-CLI to Alpine — brak `GLOB_BRACE`.
- Menedżer pamięci Claude Code zabija długie zadania w tle — długie skrypty lepiej w osobnym terminalu.

### W toku

1. Zdjęcia ChatGPT: 290 do końca → dwa PDF-y porównania (na stanie / bez stanu) → decyzja właściciela,
   którą metodą podmieniamy zdjęcia w sklepie.
2. **Właściciel:** baner „Załóż konto −10%” (rejestracja wyłączona, kuponu brak), wyłączenie trybu
   „wkrótce otwarcie”, regulamin, wtyczka SEO.
3. Atrybut Rozmiar nieuporządkowany (poza filtrami); `pa_zloto` ma duble „0,585 /14K” i „585 / 14K”.
4. Salon firmowy: drugi numer telefonu lekko przesunięty względem Shopera (kosmetyka).
5. Mapa 301 bloga (stare adresy pomocnicze → WordPress) do sitemapy/przekierowań przy wdrożeniu.

Powiązania: [[wyglad-motyw]], [[adresy-url-wtyczka]], [[import-woocommerce]], [[strony-kluczowe-seo-2026-10-16]],
[[DZIENNIK#§3 — 2026-10-16 — Lokalny WooCommerce: import katalogu i treści, wtyczka starych adresów|§3]].

## §5 — 2026-10-19 — Zdjęcia produktów: ChatGPT 937 / 937, PDF-y porównania

Domknięcie poprawy małych zdjęć produktów z [[DZIENNIK#§4 — 2026-10-18 — Wygląd 1:1 z Shoperem, banery, blog po WordPressowemu, poprawa zdjęć produktów|§4]]:
obie metody mają komplet, porównanie jest u właściciela. **Zdjęcia w WordPressie nadal oryginalne.**

### Decyzje

- **Podmiana zdjęć dopiero po decyzji właściciela** (ESRGAN / ChatGPT / mieszanie per produkt) na
  podstawie PDF-ów — ChatGPT bywa lepszy wizualnie, ale potrafi zmienić detal, więc bez przeglądu
  nie podmieniamy niczego automatycznie.
- PDF-y dzielone na dwie grupy (na stanie / bez stanu), bo produkty na stanie są ważniejsze i
  właściciel może je przejrzeć najpierw.

### Zrobione

- **ChatGPT 937 / 937** (`dane/poprawa-zdjec/chatgpt/`), Real-ESRGAN 937 / 937 (`…/esrgan/`).
  Ostatnie 31 zdjęć dorobione 2026-10-19 rano (skrypt pomija gotowe, można go uruchamiać ponownie).
- **PDF-y porównania** (`porownanie-pdf.py na-stanie|bez-stanu`): oryginał | ESRGAN | ChatGPT +
  fragment ×3, jedna strona = jeden produkt —
  `dane/poprawa-zdjec/porownanie-esrgan-vs-chatgpt-na-stanie.pdf` (432 str., 38 MB) i
  `…-bez-stanu.pdf` (507 str., 53 MB). Do wysyłki podzielone na połówki (limit 30 MB).
- `popraw-chatgpt.mjs`: przy serii 3 błędów czeka 30 min zamiast kończyć (po 4 seriach koniec),
  loguje końcówkę odpowiedzi Codexa, rozpoznaje limit w różnych sformułowaniach (PL/EN).
- Wygląd (po wpisie §4): na głównej 8 polecanych produktów (2 rzędy po 4), tekst bloku
  `box_custom46` opływa zdjęcie (`glowna.css`), karty wpisów bloga mają wewnętrzny odstęp (`blog.css`).

### Pułapki

- **Komunikat o limicie obrazków nie jest stały** — Codex opisuje go za każdym razem innymi słowami,
  po polsku lub angielsku („usage-limit error”, „limit użycia”, „limit … wyczerpany”). Skrypt
  łapiący tylko „usage limit” pominął 31 zdjęć zamiast czekać. Regex jest teraz szerszy.
- Okno limitu ChatGPT Plus wyszło ok. 12 h (16:13 → ok. 4:30), na jedno okno ok. 135–150 zdjęć.
- Odpowiedzi Codexa przy limicie proponują „CLI fallback z OPENAI_API_KEY” — nie używamy (koszt API,
  wymaga zgody).

### W toku

1. **Właściciel:** przegląd PDF-ów i decyzja, którą metodą podmieniamy zdjęcia → potem skrypt
   podmiany załączników w WooCommerce (z zachowaniem oryginałów i ID z Shopera).
2. Bez zmian z §4: baner „Załóż konto −10%”, tryb „wkrótce otwarcie”, regulamin, wtyczka SEO,
   atrybuty Rozmiar/`pa_zloto`, telefon w Salonie, mapa 301 bloga.

Powiązania: [[import-woocommerce]], [[wyglad-motyw]],
[[DZIENNIK#§4 — 2026-10-18 — Wygląd 1:1 z Shoperem, banery, blog po WordPressowemu, poprawa zdjęć produktów|§4]].

## §6 — 2026-10-19 — Zdjęcia WebP bez metadanych AI, reszta zdjęć, favicon, strony prawne i cookies

Właściciel wybrał wersje z ChatGPT. Zdjęcia produktów i banery są już w WooCommerce jako WebP.
Ruszyła poprawa pozostałych zdjęć aktywnych produktów. Sklep dostał zgody na cookies, GTM,
favicon, polskie teksty koszyka i poprawioną stronę zwrotów.

### Decyzje

- **WebP bez metadanych.** Pliki z ChatGPT niosą znacznik C2PA („wygenerowane przez AI”, chunk
  `caBX`) i daty w PNG. Ponowne kodowanie przez Pillow (`frombytes` → WebP) daje czysty VP8 bez
  kontenera VP8X, więc nie ma w nim EXIF/XMP/C2PA. Sprawdzone na wszystkich 971 plikach.
- **Podmiana załączników w miejscu** (to samo ID, wyszukiwane po `_sb24_gfx_id`), a nie nowe
  załączniki. Dzięki temu nie zmieniają się galerie, zdjęcie główne ani powiązania.
  Meta `_sb24_poprawione` (md5) i `_sb24_poprawione_zrodlo=chatgpt` pozwalają wznawiać i cofać.
- **Strony prawne zostają z Rzetelnego Regulaminu.** To treść robiona na zamówienie; skrypt
  pokazuje ją tylko na domenie swiatbizuterii24.pl (lokalnie wyświetla „niedostępny”, to normalne).
  Regulamin i polityka prywatności są podpięte w opcjach Woo/WP (akceptacja przy zamówieniu).
- **„Zwroty i reklamacje” poprawione celowo** (wyjątek od zasady 1:1). Strona w Shoperze była
  szablonem z cudzymi danymi. Nowa treść jest w `skrypty/import/tresci-poprawione/<slug>.html`,
  adres się nie zmienia, a test zgodności tekstów tę stronę pomija.
- **Zgody na cookies: własne okno** z tekstami i kategoriami jak w Shoperze oraz Google Consent
  Mode v2 (domyślnie wszystko „denied”). **GTM-PQPTQ7M** (konto właściciela) ładuje się tylko na
  produkcyjnej domenie, żeby lokalne testy nie trafiały do statystyk. GTM-T68LWS to kontener Shopera,
  więc go nie przenosimy. Kod weryfikacji Search Console przeniesiony.
- **Opinie: tylko od zweryfikowanych kupujących**, z moderacją. **Odrzucone:** dopisywanie
  wymyślonych opinii i komentarzy. Fałszywe opinie są zakazane (Omnibus, UOKiK) i grożą karą.
  W Shoperze moduł opinii był wyłączony, więc nie ma czego przenosić.
  Proponowane zamiast tego: prośba o opinię po zakupie i widget opinii Google salonu.

### Zrobione

- `do-webp.py`: PNG z ChatGPT i banery → `dane/eksport/zdjecia-webp/{produkty,banery-kategorii,
  banery-stron}/`. Jakość 85 dla produktów i 82 dla banerów, przezroczystość na białym tle.
  Wynik: 937 + 26 + 8 plików.
- `importuj-tresci.php zdjecia`: 937 zdjęć podmienionych w miejscu. Banery wczytywane z WebP,
  stary załącznik usuwany.
- **Reszta zdjęć:** `lista-reszty.py` buduje `reszta-zdjec.csv` (3286 zdjęć aktywnych produktów,
  w tym 832 na stanie; najpierw te na stanie, potem od najmniejszych). Tryb `--reszta` w
  `popraw-esrgan.mjs` i `popraw-chatgpt.mjs`. Obie poprawy działają w osobnych oknach.
  Stan o 17:40: ESRGAN 2350 / 3286, ChatGPT ok. 65.
- Banery nagłówka na stronach koszyka, zamówienia i konta (fallback na `sb24_baner_stron`).
- Favicon 512 px (złote „Ś” na #111) w `tresci/marka/favicon.png`, ustawiany jako `site_icon`
  przez `ustaw-wyglad.php wspolne`.
- Polska paczka językowa WooCommerce 11.2.1, czyli tłumaczenia bloków koszyka i zamówienia.
- Wtyczka `sb24-migracja`: `zgody.js` i `zgody.css`, w `<head>` domyślne zgody, GTM i weryfikacja
  Search Console; w stopce link „Ustawienia cookies”.
- `buduj-tresci.mjs` pobiera też PDF-y. Wzór formularza odstąpienia jest w mediach.
  `sprawdz-teksty.mjs`: 61 OK, 0 rozbieżności.

### Pułapki

- **Kontener WordPressa nie pobiera z internetu** (cURL 60, SSL), więc `wp language … update` nie
  działa. Paczki pobieramy na hoście i wrzucamy przez `docker cp` (z `chown www-data`).
- `ustaw-wyglad.php` ma osobne tryby: `site_icon` i opcje opinii ustawia tylko `wspolne`, nie
  `kadence`. Uwaga: `wspolne` tworzy menu od nowa (nowe ID).
- Git Bash przerabia ścieżki w argumentach `docker exec` (`/var/www/...`), więc trzeba
  `MSYS_NO_PATHCONV=1`.
- Docker Desktop nie startuje sam z Windowsem. Bez niego `wp.sh` kończy się błędem demona.

### W toku

1. **Poprawa reszty zdjęć:** ESRGAN skończy się za ok. godzinę. ChatGPT zajmie ok. 3 tygodni
   (limit ok. 140 na 12 h), zdjęcia na stanie ok. 5–6 dni. Potem albo etapami: `do-webp.py` i
   `importuj-tresci.php zdjecia` (import sam bierze nowe gfx).
2. Do sprawdzenia przez właściciela: czy w koszyku zostały angielskie teksty, wygląd favicona.
3. Bez zmian: baner „Załóż konto −10%”, tryb „wkrótce otwarcie”, wtyczka SEO, telefon w Salonie,
   repozytorium git.

Powiązania: [[import-woocommerce]], [[wyglad-motyw]],
[[DZIENNIK#§5 — 2026-10-19 — Zdjęcia produktów: ChatGPT 937 / 937, PDF-y porównania|§5]].

## §7 — 2026-10-19 — Stopka NETHOUSE, plan konfiguracji sklepu, analiza kont klientów

Krótka sesja po §6: podpis wykonawcy w stopce, spis tego, co trzeba ustawić przed startem,
i propozycja ustawień kont klientów. Zatrzymane obie poprawy reszty zdjęć.

### Zrobione

- **Stopka:** „© {year} Świat Biżuterii 24 · Jubiler Opole · Wykonanie: NETHOUSE”, link do
  https://www.nethouse.tech/ (nowa karta, `rel="noopener"`, bez `nofollow`) w `ustaw-wyglad.php`
  dla Kadence i Blocksy. Zastosowane (`kadence`) i sprawdzone na stronie głównej.
- **Plan [[konfiguracja-sklepu]]** (`docs/plany/`, wpisy w [[HOME]], [[MOC-WordPress]],
  [[MOC-Wdrozenie]]): dostawy, płatności, e-maile, konta, faktury, SEO, z podziałem kto co robi.
- **Stan Shopera odczytany z API** (`shippings`, `payments`, `deliveries`, tylko odczyt):
  - dostawy: odbiór osobisty i DHL Kurier 21,50 zł do 10 kg (pole „darmowa” = 70);
  - płatności: PayU, przedpłata przelewem, pobranie, karta podarunkowa;
  - czas wysyłki: słownik „24 godziny … 21 dni”.
- **W WooCommerce nadal brak:** stref wysyłki i metod płatności (wszystkie wyłączone), adres
  sklepu jest pusty, nadawcą e-maili jest prywatny adres administratora.
- **Konta (tylko kształt danych):** 498 rekordów `users`, z czego **39 to konta zarejestrowane**
  (`registered=1`); reszta to klienci bez konta, głównie import z 2021. 46 zamówień, 96 zgód na
  newsletter, grupy i rabaty nieużywane. W WooCommerce rejestracja jest wyłączona, zakup bez konta
  włączony, a podstrony konta mają angielskie adresy (`/moje-konto/orders`). Stare adresy
  `/pl/login` (200) i `/pl/panel` (302 → login).

### Decyzje / propozycje

- **Do zrobienia bez danych właściciela** (czeka na „tak”):
  - rejestracja na „Moje konto” i przy zamówieniu, zakup bez konta zostaje, klient sam ustawia
    hasło;
  - polskie podstrony konta;
  - 301 `/pl/login` i `/pl/panel` → `/moje-konto/`;
  - zabezpieczenie rejestracji przed botami bez przepisywania kodów.
- **Konta z Shopera przenosimy tylko zarejestrowane (39), bez haseł**, bo API ich nie daje
  (reset przez „Nie pamiętasz hasła?”). Opcjonalnie z 46 zamówieniami: historia w koncie i
  weryfikacja kupującego przy opiniach. Klientów bez konta nie zakładamy jako kont (RODO,
  nie zakładali ich).

### W toku

1. **Poprawa reszty zdjęć zatrzymana ok. 17:53–17:55** (okna przerwane Ctrl+C):
   - ESRGAN 2600 / 3286;
   - ChatGPT ok. 74 z reszty (1011 plików łącznie).

   Oba skrypty pomijają gotowe, więc wystarczy uruchomić ponownie: `node
   skrypty/zdjecia/popraw-esrgan.mjs --reszta` i `node skrypty/zdjecia/popraw-chatgpt.mjs --reszta`.
2. **Właściciel:**
   - próg darmowej dostawy;
   - PayU (klucze);
   - konto do przelewu;
   - dopłata za pobranie;
   - karta podarunkowa;
   - adres nadawcy e-maili i SMTP;
   - przeniesienie 39 kont i zamówień;
   - newsletter;
   - baner −10%;
   - program do faktur.

   Lista: [[konfiguracja-sklepu]].
3. Bez zmian z §6: wtyczka SEO, tryb „wkrótce otwarcie”, telefon w Salonie, repozytorium git.

Powiązania: [[konfiguracja-sklepu]], [[wyglad-motyw]],
[[DZIENNIK#§6 — 2026-10-19 — Zdjęcia WebP bez metadanych AI, reszta zdjęć, favicon, strony prawne i cookies|§6]].

## §8 — 2026-10-19 — Yoast SEO 1:1, konta klientów z Shopera, rejestracja

Wieczorna sesja: wtyczka SEO z title/meta 1:1, konta klientów (ustawienia + przeniesienie z
Shopera), wznowienie poprawy zdjęć.

### Zrobione

- **Yoast SEO 28.7** (zainstalował właściciel), konfiguracja: `skrypty/import/ustaw-seo.php`
  (wielokrotnego użytku), potem `skrypty/wp.sh yoast index --reindex`.
  - **Strony z 1. miejsc** (główna, `/naprawa-bizuterii-opole`, `/skup-zlota-opole`): title i meta
    **1:1 z żywą stroną**, z literówkami; porównane z HTML-em swiatbizuterii24.pl z tego dnia.
    Skrypt zapisuje je wprost z [[strony-kluczowe-seo-2026-10-16]], niezależnie od importu.
  - **Reszta:** `_sb24_seo_title` / `_sb24_seo_description` z importów przepisane do pól Yoasta.
    Objęło to 1638 stron, wpisów i produktów oraz 27 kategorii i producentów. Na próbkach
    (`/zlote-kolczyki`, `/srebrne-kolczyki`, `/pl/blog/Ksztalty-diamentow/16`) wynik jest 1:1.
  - **Szablony** dla obiektów bez danych z Shopera: wzór „Nazwa | Jubiler Opole |
    Swiatbiżuterii24”, separator „|”.
  - **Wyłączone archiwa,** których Shoper nie miał: autorzy, daty, formaty i strony załączników.
    Koszyk, kasa i konto mają noindex i są poza sitemapą.
  - **Pozostałe ustawienia:** dane firmy z logo i Facebookiem, sitemap `/sitemap_index.xml`.
- **Poprawka adresów Yoasta** w `sb24-migracja` (`sb24_yoast_adres`):
  - **Problem:** Yoast dopisywał ukośnik w canonical, og:url, schema i sitemapie, np.
    `/naprawa-bizuterii-opole/`, a ten adres daje 301 na wersję bez ukośnika.
  - **Teraz:** stare adresy wychodzą bez ukośnika, a paginacja kategorii w formacie Shopera
    (`/zlote-kolczyki/2`, rel prev/next).
  - Opis pułapki: [[adresy-url-wtyczka]].
- **Konta klientów** (`skrypty/import/ustaw-konta.php`):
  - **Rejestracja** na „Moje konto” i przy zamówieniu. Zakup bez konta zostaje. Klient sam ustawia
    hasło, login powstaje z e-maila.
  - **Podstrony po polsku:**
    - `/moje-konto/zamowienia/`, `/szczegoly-zamowienia/`, `/adresy/`, `/edytuj-konto/`,
      `/odzyskaj-haslo/`, `/wyloguj/`;
    - na kasie `/zamowienie/przyjete/` i `/zamowienie/zaplac/`.
  - **Ochrona rejestracji** (`sb24-migracja`): ukryte pole-pułapka i minimum 3 s od wyświetlenia
    formularza (znacznik czasu podpisany `wp_hash`).
  - **Test:** bot i zbyt szybkie wysłanie odrzucone; normalna rejestracja, logowanie i podstrony
    działają.
  - **Stare adresy:** 301 `/pl/login`, `/pl/panel`, `/pl/reg` → `/moje-konto/` działało już
    wcześniej we wtyczce.
- **Przeniesione konta z Shopera:**
  - **Skrypty:** `skrypty/eksport/buduj-konta.mjs` → `dane/eksport/konta/klienci.json` →
    `skrypty/import/importuj-konta.php`.
  - **Wynik:** 39 kont zarejestrowanych, w tym 23 z adresem i telefonem (adres idzie jako
    rozliczeniowy i do dostawy). Ponowne uruchomienie tylko aktualizuje: 0 nowych, 39
    zaktualizowanych.
  - **Meta:** `_sb24_shoper_user_id`, `_sb24_ostatnia_wizyta`, `_sb24_newsletter` (6 zgód; tylko
    informacja).
  - **Konto testowe** dla właściciela: `importuj-konta.php testowe`, login `klient-testowy`, hasło
    podane w czacie (ponowne uruchomienie ustawia nowe). Ma meta `_sb24_testowe` i trzeba je
    **usunąć przed startem**.
- **Poprawa zdjęć:**
  - ESRGAN skończony: w `dane/poprawa-zdjec/esrgan` są 4223 pliki;
  - ChatGPT `--reszta` wznowiony: 1096 plików, czeka na odnowienie limitu.

### Decyzje / pułapki

- **Ochrona rejestracji bez CAPTCHA (Turnstile, reCAPTCHA).** Każda z nich to zewnętrzna usługa i
  dodatkowe cookies do baneru zgód. Pułapka i limit czasu wystarczają na typowe boty. Konto przy
  zamówieniu wymaga złożenia całego zamówienia, więc tam ochrony nie dokładamy.
- **Końcówki WooCommerce są globalne** (działają przy każdej stronie), więc nie mogą się nazywać
  jak istniejąca strona. Stąd `szczegoly-zamowienia`, a nie `zamowienie`, które jest adresem kasy.
  `ustaw-konta.php` sam sprawdza kolizje.
- **Konta z Shopera:**
  - przenosimy tylko zarejestrowane, bez haseł (API ich nie daje): klient ustawia nowe przez
    „Nie pamiętasz hasła?”;
  - nikt nie dostaje e-maila przy imporcie;
  - klientów bez konta nie zakładamy (RODO; sami konta nie zakładali);
  - PESEL-u nie przenosimy (i tak pusty).
- **Yoast zamienia podwójną spację w tytule na pojedynczą.** Dotyczy to dwóch kategorii
  („Srebrne kolczyki  |”, „Obrączki  |”). Zostawione, bo Google i tak pokazuje jedną spację.

### W toku

1. **ChatGPT `--reszta`:** skrypt czeka na limit (do 22:32) i jedzie dalej sam. Po przerwaniu
   wystarczy uruchomić go ponownie (pomija gotowe). Wcześniejszy przebieg zatrzymał Claude Code
   przy braku pamięci w systemie.
2. **Gotowe zdjęcia ESRGAN** jeszcze nie wgrane: `do-webp.py`, potem `importuj-tresci.php
   zdjecia`.
3. **Przed startem:**
   - usunąć konto testowe;
   - `yoast index --reindex` na produkcji;
   - wyłączyć tryb „wkrótce otwarcie”.
4. **Właściciel** ([[konfiguracja-sklepu]]):
   - dostawy (próg darmowej);
   - PayU i pozostałe płatności;
   - e-maile i SMTP;
   - historia 46 zamówień w kontach;
   - newsletter;
   - baner −10%;
   - faktury.

Powiązania: [[konfiguracja-sklepu]], [[adresy-url-wtyczka]], [[strony-kluczowe-seo-2026-10-16]],
[[DZIENNIK#§7 — 2026-10-19 — Stopka NETHOUSE, plan konfiguracji sklepu, analiza kont klientów|§7]].

## §9 — 2026-10-19 — Astra jako trzeci motyw: sprawdzona i odrzucona, powrót do Kadence

Na prośbę właściciela szukaliśmy innego darmowego motywu. Astrę zainstalowaliśmy i ustawiliśmy, ale
właściciel ją odrzucił, więc aktywny jest z powrotem Kadence.

### Zrobione

- **Przegląd darmowych motywów:** Astra, Botiga, GeneratePress, OceanWP, Storefront, JewStore.
  Dane z API wordpress.org: instalacje, ocena, data aktualizacji, polskie tłumaczenie.
  - Odpadły:
    - OceanWP i Zakra: bez pl_PL;
    - JewStore: 60 instalacji;
    - Storefront: zbyt surowy.
  - Demo jubilerskie Botigi jest tylko w wersji Pro.
- **Astra 4.14.1** z pl_PL, tryb `astra` w `ustaw-wyglad.php`:
  - **Układ jak w Kadence:** czarny pasek z telefonem, logo | szukaj + koszyk, menu pod spodem,
    stopka 4 kolumny, filtry po lewej.
  - **Banery:** „Banner Layout 2” Astry, w środku okruszki + tytuł.
  - **Fonty** lokalnie.
- **Wtyczka:** `baner.css` obsługuje baner Astry (`.ast-archive-entry-banner`,
  `.ast-single-entry-banner`). `blog.css` obsługuje pasek `#secondary`; nagłówek ciemnej karty
  salonu przegrywał wcześniej z kolorem nagłówków motywu.
- **Weryfikacja zrzutami** z Chrome bez okna (`--headless=new --screenshot`, scratchpad sesji):
  główna, kategoria, produkt, blog, strona informacyjna, telefon. Poprawione:
  - opis kategorii w banerze;
  - niewidoczny nagłówek karty salonu;
  - tytuł w banerze przy krawędzi na telefonie;
  - za mały tytuł produktu.
- **Powrót:** `theme activate kadence` + `ustaw-wyglad.php kadence`. Widgety stopki, filtrów i
  bloga są na miejscu; zrzuty kategorii i bloga wyglądają jak przed zmianą.

### Decyzje / pułapki

- **Bez „Starter Templates” Astry.** Import szablonu nadpisuje strony, menu i ustawienia treścią
  demo, a szablony jubilerskie są w większości płatne. Odtwarzamy własny układ na danych sklepu.
- **Astra odrzucona przez właściciela** („nie podoba mi się”). Zostaje zainstalowana i ustawiona do
  porównania: `?motyw=astra`. Wybór dalej między Kadence a Blocksy ([[wyglad-motyw]]).
- **Zrzuty bez logowania pokazują tryb „wkrótce otwarcie” WooCommerce** zamiast sklepu.
  `?woo-share=<klucz>` nie działa w Chrome bez okna. Na czas zrzutów wyłączaliśmy tryb
  (`woocommerce_coming_soon` = `no`) i od razu przywracaliśmy `yes`.
- Chrome bez okna ma minimalną szerokość ok. 500 px — „telefon” 390 px wychodził przycięty.
  Zrzuty mobilne robić w 500 px.

### W toku

1. ChatGPT `--reszta`: 1096 plików, nadal czeka na odnowienie limitu (kolejna próba 23:02).
2. Bez zmian z §8: zdjęcia ESRGAN do wgrania, konto testowe do usunięcia przed startem, dane od
   właściciela ([[konfiguracja-sklepu]]).

Powiązania: [[wyglad-motyw]],
[[DZIENNIK#§8 — 2026-10-19 — Yoast SEO 1:1, konta klientów z Shopera, rejestracja|§8]].

## §10 — 2026-10-20 — Dostawy i adres sklepu 1:1 z Shopera

### Zrobione

- **Dostawy odczytane z API Shopera** (`shippings`, tylko odczyt). Aktywne są dwie:
  - odbiór osobisty 0 zł w salonie;
  - DHL Kurier: 21,50 zł, przedziały wartości zamówienia 0–70 zł → 21,50 zł i od 70 zł → 0 zł
    (`depend_on_w=2`). To wyjaśnia pole „darmowa = 70” z [[DZIENNIK#§7 — 2026-10-19 — Stopka NETHOUSE, plan konfiguracji sklepu, analiza kont klientów|§7]].
  - Nieaktywne (Allegro, Shoper Przesyłki) — nie przenosimy.
- **`skrypty/import/ustaw-dostawy.php`** (wznawialny: metody strefy zakładane od nowa):
  - strefa „Polska”: stawka ryczałtowa 21,50 zł + darmowa wysyłka od 70 zł, obie jako „DHL Kurier”;
  - odbiór osobisty jako „Odbiór” kasy blokowej (`woocommerce_pickup_location_settings` +
    `pickup_location_pickup_locations`), lokalizacja: salon, ul. Ozimska 17A;
  - adres sklepu: ul. Ozimska 17A, 45-057 Opole, woj. opolskie;
  - sprzedaż i wysyłka tylko do Polski (w Shoperze strefa obejmowała tylko Polskę).
- **Wtyczka `sb24-migracja`**, sekcja „dostawy”: filtr `woocommerce_package_rates` chowa płatną
  stawkę, gdy darmowa jest dostępna.
- **Sprawdzone** (`calculate_shipping_for_package`): 30 zł i 69,99 zł → DHL 21,50; 70 zł i 250 zł →
  DHL 0; odbiór zawsze 0.
- **Zdjęcia ChatGPT** wznowione o 7:34 po odnowieniu limitu.
- [[konfiguracja-sklepu]]: punkty 1 i 6 odhaczone.

### Decyzje / pułapki

- **Darmowa dostawa jako osobna metoda + filtr**, a nie jedna stawka z progiem: WooCommerce nie ma
  progu cenowego w stawce ryczałtowej. Bez filtra klient widziałby obok siebie „DHL Kurier 21,50”
  i „DHL Kurier 0”.
- **Odbiór przez blokowy „Odbiór”**, nie starą metodę `local_pickup` w strefie: kasa jest blokowa,
  a w niej odbiór ma własną zakładkę z adresem salonu i nie wymaga adresu dostawy.
- **Limit 10 kg z Shopera pominięty** — żaden produkt nie ma wagi, więc nie byłoby czego liczyć.
- **Dopłata 20 zł za pobranie** jest w Shoperze przypięta do płatności, nie do dostawy — wróci przy
  płatnościach (pkt 4, decyzja właściciela).

### W toku

1. ChatGPT `--reszta`: o 9:44 lista „reszta” to 3286 zdjęć, gotowe 254, zostało 3032; od 7:34
   94 zdjęcia (1 błąd), dalej wg limitu.
2. Bez zmian z §8: zdjęcia ESRGAN do wgrania, konto testowe do usunięcia przed startem, płatności
   i SMTP od właściciela ([[konfiguracja-sklepu]]).

Powiązania: [[konfiguracja-sklepu]],
[[DZIENNIK#§8 — 2026-10-19 — Yoast SEO 1:1, konta klientów z Shopera, rejestracja|§8]].

## §11 — 2026-10-21 — Zdjęcia WebP w sklepie, wygląd, zakupy wyłączone, kontakt, poczta, bezpieczeństwo, cache, Duplicator, GitHub

### Zrobione

- **Zdjęcia w sklepie:** `do-webp.py` bierze wersję z ChatGPT, a gdy jej nie ma — z ESRGAN (zmniejszoną
  do 1024 px); `produkty/_zrodla.json` mówi, skąd jest każdy plik. Import podmienił 3285 zdjęć — wszystkie
  4222 to WebP (1331 ChatGPT, 2891 ESRGAN). Wagi: miniatura listy ~6–7 KB, zdjęcie produktu 600 px ~16–19 KB,
  pełne 1024 px 30–45 KB. Podmiana na ChatGPT później: `do-webp.py` + `importuj-tresci.php zdjecia`
  (po md5, ten sam załącznik). Pillow doinstalowany (zniknął po aktualizacji Pythona).
- **Kategorie i menu:** 3 produkty w rzędzie, 15 na stronę; kafelek bez przycisku — cały prowadzi do
  produktu, przy najechaniu powiększa się zdjęcie. Menu: większe, ciemne napisy, złota kreska, jasne
  podmenu (długie w 2 kolumnach). Wyszukiwarka w nagłówku jako pole (szuka produktów) zamiast nakładki.
  Baner na wynikach wyszukiwania i `/sklep`.
- **Paginacja:** linki na `/kategoria/3` doklejały numer (`/3/page/6/`) — `sb24_paginacja_shopera`
  odcina numer strony Shopera; link „1” → adres kategorii, `/zloto/1` → 301. Sprawdzone automatycznie
  na 25 kategoriach, blogu, wyszukiwarce i filtrach. Opisy kategorii (górny i dolny) tylko na stronie 1 —
  jak w Shoperze.
- **Zakupy online wyłączone** (decyzja właściciela, na razie): zamiast koszyka zaproszenie do salonu
  z telefonami i „Zapytaj o ten produkt”; bez koszyka w nagłówku; koszyk i kasa → salon. Włączenie:
  `wp option update sb24_zakupy_online tak`.
- **Nowe adresy** (`SB24_NOWE_ADRESY`): `/salon-firmowy`, `/indywidualne-projekty`, `/kontakt` — stare
  `/pl/i/…`, `/pl/c/…` (z paginacją) dają 301. Strony Salon i Kontakt przerobione (karty, mapa, godziny;
  treść w `tresci-poprawione/`). Numer stacjonarny wszędzie przy telefonach; poprawione godziny w pasku bloga.
- **Jeden H1:** filtr treści usuwa H1 równy tytułowi, inne zamienia na H2 (pomija stronę główną).
  „Naprawa biżuterii” dostała tytuł „Naprawa biżuterii Opole” = H1 z Shopera. 1677 stron: wszędzie 1 H1.
- **Formularz kontaktowy** `[sb24_formularz]` (Kontakt, Salon): pułapka + min. 3 s + 5/h z IP, zgoda RODO,
  e-mail HTML w szablonie maili WooCommerce (logo, kolory marki — też dla maili do klientów).
- **Poczta:** nadawca `info@` sklepu, formularz i powiadomienia o zamówieniach → skrzynka sklepu +
  właściciel; SMTP cal.pl z `.env` (`SB24_SMTP_*` przez docker-compose → `phpmailer_init`); lokalnie
  `SB24_MAILPIT` kieruje wszystko do Mailpita (podgląd http://localhost:8026).
- **Logowanie:** własny limit prób (`bezpieczenstwo.php`) + historia z odblokowaniem w Narzędzia →
  Próby logowania (30 dni); XML-RPC off, `/?author=N` i REST `users` → 404. Panel ukryty wtyczką
  WPS Hide Login (adres tylko w bazie i `.env`).
- **Zadania w tle:** 1927 zaległych akcji (m.in. tabela atrybutów do filtrów) — WordPress w kontenerze
  nie mógł połączyć się sam ze sobą pod `localhost:8080`. Apache słucha też na 8080 (docker-compose).
- **WP Fastest Cache** (`ustaw-cache.php`) + **paczka Duplicatora** (518 MB, `dane/paczka/`): bez konta
  testowego, bez Astry/Blocksy, sklep otwarty. Lista kroków po wgraniu: [[konfiguracja-sklepu]] §5.
- **GitHub:** repozytorium założone i wypchnięte (`Judo-Tomaszek/swiatbizuterii24`), `.gitattributes` z LF.

### Decyzje / pułapki

- **Permalinki `/blog/%postname%` bez ukośnika.** WP Fastest Cache nie zapisuje stron, których adres
  nie kończy się ukośnikiem, gdy struktura WP kończy się ukośnikiem — czyli żadnej strony z adresem
  Shopera. Po zmianie: 1807 adresów z mapy Shopera bez zmian (porównanie przed/po), zmieniły się tylko
  `/moje-konto`, `/sklep`, `/koszyk` (wersje z `/` → 301). Przy okazji znika źródło pułapki Yoasta z §8.
- **Norton Antivirus przechwytuje TLS** (SMTP i HTTPS): lokalnie wtyczka nie weryfikuje certyfikatu
  SMTP (tylko `local`), a Git w repo używa `http.sslBackend schannel`.
- **WP-CLI ma osobny kontener** — bez `WORDPRESS_CONFIG_EXTRA` widział środowisko „production”; dodane.
- Komunikat Action Scheduler z `%1$d` i linkiem `%2$s` to błąd WooCommerce 11.2.1 (brak `sprintf`), nie
  tłumaczenia.
- Test formularza curlem na Windowsie wysyła polskie znaki w CP1250 — fałszywy błąd; przeglądarka wysyła UTF-8.
- Limit logowania tylko po IP, nie po loginie — inaczej obcy mógłby zablokować właściciela.
- Zdjęcia z ChatGPT dalej robione lokalnie — po przeniesieniu sklepu podmiany trzeba robić też na serwerze.

### W toku

1. ChatGPT `--reszta`: 1617 plików; skrypt kilka razy wyłączany przez Claude Code przy braku pamięci
   (główne „zjadacze”: Firefox, emulator Androida z innego projektu, usługa DTS — wyłączona).
2. Na serwerze: kroki z [[konfiguracja-sklepu]] §5 (SMTP w wp-config, WP Fastest Cache zapisać ponownie,
   adres panelu, usunąć instalator). Od właściciela: płatności, regulamin, faktury.

Powiązania: [[konfiguracja-sklepu]], [[adresy-url-wtyczka]],
[[DZIENNIK#§10 — 2026-10-20 — Dostawy i adres sklepu 1:1 z Shopera|§10]].
