---
aliases: [Plan migracji, Migracja Shoper WooCommerce]
tags: [plan, migracja, shoper-api, woocommerce, url]
---

# 🗺️ Plan migracji Shoper → WooCommerce

**Status: szkic (2026-10-16).** Nic nie zaimplementowane, decyzje w sekcji „Do decyzji” otwarte.
⤴ [[HOME]] · [[MOC-Migracja]] · [[MOC-SEO-i-URL]]

> [!warning] Fakty o Shoperze do potwierdzenia
> Schematy adresów sprawdzone na żywej stronie 2026-10-16 (sekcja „Strategia”). Zasoby API to
> nadal **założenia do sprawdzenia** w dokumentacji i na pierwszym połączeniu. Po weryfikacji poprawiamy ten dokument i dopisujemy
> wpis do [[DZIENNIK]].

## Cel

Przenieść sklep swiatbizuterii24.pl na WordPress + WooCommerce **bez utraty ruchu z wyszukiwarek**:
produkty, kategorie, producenci, treści i (jeśli się da) blog — z zachowaniem adresów.

## Strategia adresów URL

**Ustalone na żywej stronie (2026-10-16): sklep używa dwóch schematów naraz.**

| Schemat | Przykłady |
|---|---|
| Własny adres w korzeniu (SEO URL ustawiony w Shoperze) | `/zlote-kolczyki`, `/srebrne-kolczyki`, `/skup-zlota-opole`, `/naprawa-bizuterii-opole`, `/zlote-delikatne-kolczyki-kwadraty` (produkt), `/jak-czyscic-kolczyki-aby-lsnily-jak-nowe` |
| Domyślny adres Shopera z ID | produkt `/pl/p/Zlote-kolczyki-wiszace-ozdobione-cyrkonia/5355`, kategoria `/pl/c/Indywidualne-projekty/158`, strona informacyjna `/pl/i/Regulamin/3`, blog `/pl/blog/Ksztalty-diamentow/16` |

Wniosek: w korzeniu mieszają się kategorie, produkty i strony — **typu obiektu nie da się
odczytać z adresu**. Natywne permalinki WooCommerce (`/produkt/…`, `/kategoria-produktu/…`) nie
odwzorują tego ani jednego, ani drugiego schematu. Do sprawdzenia: czy produkt z własnym adresem
odpowiada też pod `/pl/p/…/<id>` (wtedy obie wersje trzeba obsłużyć, kanoniczna = ta z sitemapy).

Kluczowe strony z tytułami i opisami: [[strony-kluczowe-seo-2026-10-16]].

| Wariant | Jak | Plusy | Minusy |
|---|---|---|---|
| **A. Identyczne adresy** | Mała własna wtyczka z **tabelą „stara ścieżka → obiekt WP”** (produkt / kategoria / strona / wpis), rozwiązywaną na początku żądania; filtry `post_type_link` / `term_link` generują linki w starym formacie, więc menu, sitemap i canonical pokazują stare adresy | Zero przekierowań, zero ryzyka SEO dla obu schematów | Własny kod do utrzymania, zgodność z wtyczką SEO i sitemapą |
| **B. Nowe adresy + 301** | Natywne permalinki WooCommerce, pełna mapa 301 | Standardowy WordPress | Spadek pozycji dla stron z 1. miejsc — **niedopuszczalne dla priorytetu 1** |
| **C. Hybryda** | Stare obiekty jak w A; **nowe** wpisy bloga i nowe produkty — natywnie | Stare adresy nietknięte, nowe treści standardowo | Dwa mechanizmy (ale rozdzielone: stare = tabela, nowe = WP) |

**Rekomendacja: C** — wszystko, co istnieje dziś (sklep, strony, cały blog), zachowuje adres przez
tabelę ścieżek; nowe wpisy bloga dostają strukturę WordPressa (zgoda właściciela 2026-10-16).
301 zostaje jako siatka bezpieczeństwa dla wariantów adresu (np. stara wersja sluga, adres bez ID).

## Etapy

### Etap 0 — Dostęp i środowisko

- Token i client_id API Shopera (**tylko odczyt**) w `.env` (wzór: `.env.example`).
- Wybór języka skryptów eksportu (do decyzji), katalog `dane/` poza gitem.
- Lokalny WordPress + WooCommerce do prób importu.

**Stan 2026-10-16:** połączenie działa, sondaż zrobiony — [[sondaz-api-shoper-2026-10-16]].

### Etap 1 — Inwentaryzacja adresów URL

- ✅ **Przebieg po wszystkich adresach z sitemapy** (2026-10-16) — [[migawka-seo-2026-10-16]]:
  1808 adresów, wszystkie 200, wzorzec title/meta/canonical/H1 + pełny HTML.

- Źródła: sitemap sklepu (`/console/integration/execute/name/GoogleSitemap` — `/sitemap.xml` daje 404), lista z API (produkty, kategorie, producenci, strony, blog),
  Google Search Console (strony z ruchem i linkami zewnętrznymi), ewentualnie crawl.
- Wynik: `inwentaryzacja-url-RRRR-MM-DD` — liczności per typ, wzorce adresów, wyjątki.
- Weryfikacja wzorców z sekcji „Strategia” na żywym sklepie.

### Etap 2 — Eksport z API Shopera

✅ **Katalog wyeksportowany** (2026-10-16) — [[eksport-katalogu]]. Zostaje: blog i strony
informacyjne do formatu importu, tekst strony głównej z HTML-a.

- Produkty z wariantami, opcjami i atrybutami; kategorie z drzewem **i opisami**; producenci;
  zdjęcia; meta SEO (title, description, własny adres) każdego obiektu; strony informacyjne; blog.
- **Tekst strony głównej** — może nie być w API (moduł szablonu); wtedy z HTML-a strony.
- Kontrola: dla stron z [[strony-kluczowe-seo-2026-10-16]] title/meta z API = title/meta z żywej strony.
- Klienci i zamówienia — **do decyzji**, czy przenosimy (RODO, hasła i tak się nie przeniosą).
- Każdy rekord: ID Shopera + pełny URL. Surowe odpowiedzi API zapisywane bez zmian (ponowna transformacja bez ponownego pobierania).

### Etap 3 — Transformacja

- Mapowanie pól Shoper → WooCommerce (osobny dokument).
- Opisy: przepisanie linków wewnętrznych i ścieżek obrazków.
- Generowanie mapy URL (stary → nowy) jako osobnego artefaktu.

### Etap 4 — Import do WooCommerce

✅ **Katalog zaimportowany lokalnie** (2026-10-16) — [[import-woocommerce]]: tylko aktywne produkty
(decyzja właściciela), stan 0 widoczny bez zakupu. ✅ Treści też: 16 stron, 31 wpisów bloga, strona
główna, 100 obrazków z tekstów.

- WooCommerce REST API albo WP-CLI; import powtarzalny (upsert po `_shoper_id`).
- Zdjęcia do biblioteki mediów z zachowaniem kolejności i tekstów alternatywnych.

### Etap 5 — Adresy i przekierowania

✅ **Wtyczka adresów działa lokalnie** (2026-10-16) — [[adresy-url-wtyczka]]: 1785 / 1786 adresów
z migawki (sklep, strony, blog, strona główna) dają 200 z tym samym H1; teksty 62 / 62 słowo w słowo.
Zostaje: wtyczka SEO (title/meta z `_sb24_*`), sitemap, motyw, regulamin od właściciela.

- Wdrożenie wariantu z „Strategii”; 301 dla wszystkiego, czego nie da się odwzorować.
- Test: **każdy** adres z inwentaryzacji → 200 albo 301 → 200 (skrypt, nie ręcznie).

### Etap 6 — Testy

- Porównanie liczności i losowej próbki rekordów Shoper ↔ WooCommerce.
- Ścieżka zakupu, płatności, wysyłki, maile.

### Etap 7 — Przełączenie

- Zamrożenie zmian w Shoperze, ostatni eksport różnicowy, przełączenie DNS.
- Search Console: nowa sitemap, monitoring 404 przez pierwsze tygodnie.
- Plan wycofania (powrót DNS na Shopera).

## Do decyzji

1. ~~Wariant adresów~~ — kierunek C przyjęty 2026-10-16 (stare adresy zostają, nowe wpisy bloga natywnie); do potwierdzenia po inwentaryzacji.
2. Czy przenosimy klientów (498) i historię zamówień (46).
2a. Które grupy produktów poza `aktywne-na-stanie` (ta idzie na pewno) — [[eksport-katalogu#Grupy (stan z 2026-10-16)]]; format paginacji kategorii (`/zloto/2`) — patrz [[sondaz-api-shoper-2026-10-16#Do decyzji]].
3. Hosting i motyw WordPressa.
4. Język i forma skryptów migracyjnych.
5. Bramki płatności i wysyłki na nowym sklepie.

## Powiązane

[[MOC-Migracja]] · [[MOC-SEO-i-URL]] · [[MOC-WordPress]] · [[MOC-Wdrozenie]] · [[DZIENNIK]]
