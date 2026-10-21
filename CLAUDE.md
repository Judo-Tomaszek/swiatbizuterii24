# swiatbizuterii24 — instrukcja projektu (auto-ładowana przez Claude Code)

Migracja sklepu **swiatbizuterii24.pl** z **Shopera** na **WordPress + WooCommerce**.
Dane pobieramy przez API Shopera, przekształcamy i przygotowujemy do importu do WooCommerce.
Dokumentacja jest **vaultem Obsidian**; punkt wejścia: `docs/HOME.md`.
Zasady prowadzenia dokumentacji: `docs/KONWENCJA-DOKUMENTACJI.md`.

## 🔴 Najważniejsza zasada projektu: adresy URL

**Zachowujemy strukturę linków stron sprzedażowych** (produkty, kategorie, producenci) i — jeśli się
da — **bloga**. Każdy adres, który dziś działa i jest zaindeksowany, po przeniesieniu musi działać
tak samo albo dawać **301 na swój odpowiednik** (nigdy 404, nigdy 301 na stronę główną).

- **Strony z 1. miejsc w Google** (główna, `/naprawa-bizuterii-opole`, `/skup-zlota-opole`, kluczowe
  kategorie, `/pl/blog/Ksztalty-diamentow/16`): adres, tekst, title i meta **1:1, bez poprawek** —
  lista: `docs/strony-kluczowe-seo-2026-10-16.md`.
- Wszystkie istniejące wpisy bloga zachowują stare adresy; **nowe** wpisy mogą mieć strukturę WordPressa.
- Każdy rekord eksportu niesie **oryginalny URL i ID z Shopera** — bez tego nie zbudujemy mapy.
- Mapa „stary URL → nowy URL” to artefakt projektu, nie efekt uboczny. Plan: `docs/plany/migracja-shoper-woocommerce.md`.
- Zanim zmienisz slug, permalink, bazę kategorii albo strukturę bloga — sprawdź wpływ na mapę URL.

## 🟢 Na starcie sesji (wczytanie „na czym skończyliśmy")

1. Gdy użytkownik prosi o kontynuację / „na czym skończyliśmy" / zaczyna większą pracę:
   przeczytaj **`docs/HOME.md`**, **`docs/SPIS-DZIENNIKA.md`** i **kilka OSTATNICH wpisów `§`**
   z końca `docs/DZIENNIK.md`. ⚠️ NIE czytaj całego DZIENNIKa — tylko końcówkę i to, co potrzebne.
2. Do konkretnego tematu wchodź przez właściwą mapę:
   `docs/MOC-Migracja|SEO-i-URL|WordPress|Wdrozenie.md`.

## 🔴 Na koniec sesji (zapis dokumentacji) — gdy użytkownik o to poprosi

1. **Dziennik**: dopisz wpis na SAMYM KOŃCU `docs/DZIENNIK.md` w formacie
   `## §N — RRRR-MM-DD — Tytuł` (kolejny numer §; myślniki „—" i układ 1:1 jak poprzednie wpisy).
   W treści: co zrobione, kluczowe decyzje **z uzasadnieniem**, dotknięte pliki, stan „w toku",
   `[[wikilinki]]` do powiązań.
2. **Odśwież indeks**: `node docs/_narzedzia/gen-spis.mjs` → przebuduje `docs/SPIS-DZIENNIKA.md`
   z nagłówków. (Nie edytuj SPIS-u ręcznie — zostanie nadpisany.)
3. **GitHub**: commit + push wykonuj **dopiero po potwierdzeniu** przez użytkownika (operacja
   wychodząca). Wiadomość np. `docs: dziennik §N — <temat>` albo wspólnie z commitem kodu.

### 🔔 Przypominaj o zapisie sam z siebie

Użytkownik nie musi pamiętać o formułce „zapisz sesję". **Po zakończeniu większej porcji pracy
zapytaj krótko, jednym zdaniem, czy dopisać wpis do dziennika** — np. *„Zapisać to jako §N w
dzienniku?"*.

Kiedy pytać:
- skończony etap migracji, skrypt eksportu/importu, naprawiony niebanalny błąd, zmiana na serwerze;
- podjęta **decyzja projektowa** lub odrzucony wariant — to najcenniejsza rzecz w dzienniku,
  bo `git log` tego nie zapisze;
- natrafiliśmy na pułapkę / nieoczywiste zachowanie (API Shopera, WooCommerce, permalinki), które
  zje czas przy następnym spotkaniu z nim;
- praca zostaje **niedokończona** — stan „w toku" jest wart wpisu tak samo jak sukces.

Kiedy NIE pytać:
- literówki, drobne poprawki, samo czytanie kodu, odpowiadanie na pytania bez zmian w repo;
- gdy użytkownik już odmówił w tej sesji — nie wracaj do tematu, chyba że doszła nowa duża porcja
  pracy;
- nie dopytuj po raz drugi o to samo i nie przerywaj pytaniem w trakcie pracy — dopiero po niej.

**Nigdy nie dopisuj wpisu bez zgody użytkownika.** Pytanie ma być przypomnieniem, nie zapowiedzią.
Odmowa jest w pełni w porządku — nie każda sesja zasługuje na wpis.

## 📌 Zasady szczegółowe

- Pliki dokumentacji **nie przenosimy** między folderami — porządek dają MOC-i + tagi + linki.
- **Wyjątek: plany → `docs/plany/`.** Nowy plan powstaje tam, z tagiem `plan`.
- Nowy dokument → frontmatter z tagiem + wpis do tabeli w `docs/HOME.md` i do właściwego MOC-a.
- Dokumenty z datą w nazwie (audyty, analizy, inwentaryzacje) to fotografie stanu z danego dnia —
  nie aktualizujemy ich wstecz.

## 🔐 Dane i sekrety

- Klucze API Shopera, hasła WordPressa, klucze WooCommerce REST → **wyłącznie `.env`** (poza gitem).
- Surowe eksporty z API (`dane/`) zawierają **dane osobowe klientów i zamówienia** — nie trafiają do
  repozytorium ani do dokumentacji. W dokumentach opisujemy kształt danych, nie same dane.
- API Shopera traktujemy **tylko do odczytu** — żadnych zapisów do sklepu produkcyjnego bez wyraźnej
  prośby użytkownika.
