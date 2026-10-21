---
aliases: [Motyw, Wygląd, Kadence vs Blocksy, Astra]
tags: [wordpress, woocommerce]
---

# 🎨 Wygląd — wybór motywu (Kadence vs Blocksy)

Żywy dokument. Porównanie dwóch darmowych motywów na prawdziwych danych sklepu, przed decyzją
właściciela. ⤴ [[MOC-WordPress]]

## Jak oglądać

Lokalnie: http://localhost:8080/?motyw=kadence albo `?motyw=blocksy` / `?motyw=astra` — przełącza motyw dla tej
przeglądarki (ciasteczko), `?motyw=` wraca do aktywnego (Kadence). Działa na każdej podstronie.
Przełącznik jest we wtyczce `sb24-migracja`, tylko przy `WP_ENVIRONMENT_TYPE=local`.

Ustawienia odtwarzalne skryptem `skrypty/import/ustaw-wyglad.php` (`wspolne` | `kadence` | `blocksy`
— dla Blocksy motyw musi być aktywny w czasie skryptu, nagłówek i stopkę budują jego klasy).

## Co jest ustawione (oba motywy tak samo)

- Marka z Shopera: logo (350×66), złoty `#c9bd80` jako akcent, **ciemniejsze złoto `#8b7b3f` na
  przyciski** (biały tekst na `#c9bd80` ma kontrast ~1,9:1), czerń `#111`, Open Sans; nagłówki
  Cormorant Garamond (propozycja — elegancki krój pod biżuterię).
- Górny czarny pasek: telefon, adres salonu, Facebook. Pod nim logo | szukaj + koszyk, niżej menu.
- **Menu 1:1 z Shopera** (kolejność z migawki): Złoto ▾, Złote kolczyki, Srebro ▾, Obrączki,
  Pierścionki zaręczynowe, Indywidualne projekty, Blog, Salon firmowy, Naprawa biżuterii, Skup złota.
- **Stopka 1:1 z Shopera**: Informacje / Zakupy / Usługi i salon / Salon w Opolu (kontakt) — linki
  ścieżkami (`/pl/i/…`), przeżyją zmianę domeny.
- 4 produkty w rzędzie, dolny opis kategorii pod produktami (wtyczka).

Niezależne od motywu (wtyczka `sb24-migracja`), więc przetrwa zmianę motywu:
- **„Cena ustalana indywidualnie” zamiast „0,00 zł”** + „Zapytaj o cenę” (link do kontaktu) i brak
  zakupu — dotyczy **819 produktów** (wszystkie obrączki i pierścionki zaręczynowe mają w Shoperze
  cenę 0, w opisie „Cena ustalana jest indywidualnie w zależności od rozmiaru”; 2 z nich są na
  stanie i bez tego dałoby się je kupić za 0 zł).
- Blog bez dat (Shoper ich nie miał).
- **Zdjęcia nie są rozciągane** ponad naturalny rozmiar w galerii produktu, kopie WordPressa w jakości
  92 zamiast 82 (miniatury przegenerowane: 4324 / 4324).

## Zdjęcia — dlaczego bywają nieostre (2026-10-16)

Import ich nie pogorszył — pliki są **bajt w bajt** jak oryginały na serwerze Shopera
(`/userdata/gfx/<id>` = `/userdata/public/gfx/<id>/…`). Źródło jest słabe:

| Zdjęcia aktywnych produktów | ≤ 450 px | 451–799 | 800–999 | ≥ 1000 |
|---|---|---|---|---|
| na stanie (główne / wszystkie) | **408** / 807 | 23 / 26 | 116 / 276 | 60 / 154 |
| bez stanu (główne / wszystkie) | 127 / 255 | 379 / 752 | 454 / 1517 | 28 / 436 |

Do tego mocna kompresja (900×900 ≈ 30 KB). Motyw rozciągał je do 600 px (retina: 1200 px), Shoper
pokazywał mniejsze — stąd różnica. Jedyna pełna poprawa: **lepsze zdjęcia od właściciela**. Lista
937 aktywnych produktów z głównym zdjęciem < 800 px: `dane/eksport/male-zdjecia.csv` (Excel).
Wymiana zdjęcia po migracji = zwykła podmiana w panelu WooCommerce, adres produktu bez zmian.

### Test poprawy zdjęć: Real-ESRGAN vs ChatGPT (2026-10-16)

8 zdjęć głównych (415 px) produktów na stanie, różne rodzaje: kolczyki z cyrkoniami, kolia,
pierścionek z rubinem, zawieszki, bransoletka, srebrne z koralem, zawieszka z ametystem.

| | Real-ESRGAN x4 (`realesrgan-x4plus`, lokalnie) | ChatGPT przez Codex CLI (`-m gpt-6-sol`, generowanie obrazu) |
|---|---|---|
| Wynik | 1660 px, łagodnie wygładzone i wyostrzone, „plastikowe” | 1024 px, ostre jak zdjęcie katalogowe |
| Wierność | kształt zachowany; przy drobnych kamieniach artefakty (cyrkonie jak „popękane”), czasem rozjaśnione tło | kompozycja zachowana, ale **szczegóły rysowane od nowa**: zmieniony zawias kolczyka, wymyślona faktura koralu, **wyraźnie bardziej nasycony kolor kamienia** (ametyst) |
| Czas / koszt | ~1,4 s / zdjęcie, bez kosztów | ~1 min / zdjęcie, limit generowania obrazów w planie ChatGPT |

Wniosek: ChatGPT wygląda najlepiej, ale zmienia to, co klient kupuje (kolor kamienia, detale) —
tylko z ręczną akceptacją każdego zdjęcia przez właściciela. Real-ESRGAN jest wierny, ale daje
umiarkowaną poprawę. Decyzja właściciela otwarta.

Pułapki Codexa: `-i` zjada kolejne argumenty (prompt **przed** `-i`); bez `< /dev/null` czeka na
stdin w nieskończoność; model z `~/.codex/config.toml` (`gpt-6.1-sol`) nie działa z kontem ChatGPT —
podawać `-m gpt-6-sol`. Zestawienia i skrypty testu: scratchpad sesji (nie w repo).

## Porównanie (2026-10-16)

| | Kadence 1.5.2 | Blocksy 2.1.60 |
|---|---|---|
| Polski | **pełne tłumaczenie pl_PL** | **brak** (tylko `blocksy.pot`) — teksty sklepu („SOLD OUT”, „More”) łatane filtrem we wtyczce |
| Lista produktów | karty bez przycisku, cena złota | karty z przyciskiem „Dodaj do koszyka”, kategoria pod nazwą, plakietka „BRAK TOWARU” |
| Strona produktu | galeria + miniatury, okruszki z „Sklep” | duże zdjęcie, okruszki bez „Sklep”, czytelny układ |
| Menu | mieści się w jednej linii po zmniejszeniu czcionki | ostatnie pozycje chowa pod „Więcej” |
| Ustawienia z kodu | proste (theme mods + opcja palety) | złożone (builder nagłówka/stopki), CSS trzeba przebudować po zmianie |
| Fonty | lokalnie z serwera (`load_fonts_local`) — RODO | lokalne fonty tylko w Pro |

## Astra — sprawdzona i odrzucona (2026-10-19)

Astra 4.14.1 (darmowa, pełne pl_PL, ponad 1 mln instalacji) — trzeci kandydat, na prośbę właściciela.
Ustawienia: `ustaw-wyglad.php astra` (opcja `astra-settings`), układ jak w Kadence:
- czarny pasek z telefonem, logo | szukaj + koszyk, menu w pasku pod spodem; stopka 4 kolumny;
- banery kategorii, stron i bloga jako „Banner Layout 2” Astry (tylko okruszki + tytuł; opis
  kategorii wypisuje wtyczka nad produktami) — zdjęcie z `baner.css`;
- filtry po lewej, 4 produkty w rzędzie, karty bez przycisku; blog w kartach z paskiem po prawej;
- fonty lokalnie (`load-google-fonts-locally`).

**Bez „Starter Templates” Astry** — import szablonu nadpisuje strony, menu i ustawienia treścią demo
(a szablony jubilerskie są w większości płatne). **Właściciel: „nie podoba mi się”** — aktywny z powrotem Kadence (`ustaw-wyglad.php kadence`
przywraca widgety). Astra zostaje zainstalowana i ustawiona do podglądu: `?motyw=astra`.

Pułapki: Astra daje opis kategorii do banera (wyłączone przez `ast-dynamic-archive-product-structure`);
nagłówek w ciemnej karcie paska bloga przegrywał z kolorem nagłówków Astry (`blog.css` obejmuje
teraz `#secondary`). Zrzuty bez logowania pokazują tryb „wkrótce otwarcie” zamiast sklepu.

## Do decyzji

1. Motyw: Kadence czy Blocksy (Astra odrzucona 2026-10-19; płatne: WoodMart / Flatsome).
2. **Długi tekst SEO nad produktami** (np. „Złote kolczyki” 2,4 tys. znaków, „Pierścionki
   zaręczynowe” 1,7 tys.) spycha produkty pod ekran. Propozycja: pokazać pierwsze 2–3 linie
   + „Czytaj więcej” — tekst zostaje w HTML-u w całości (SEO bez zmian), produkty widać od razu.
3. Nagłówki Cormorant Garamond — zostawić czy wrócić do Open Sans jak w Shoperze.
4. Fonty nagłówków są dziś z Google Fonts (`@import` w CSS) — przed startem przenieść na serwer.

## Powiązane

[[import-woocommerce]] · [[adresy-url-wtyczka]] · [[MOC-WordPress]]
