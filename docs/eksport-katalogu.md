---
aliases: [Eksport katalogu, Grupy produktów]
tags: [migracja, shoper-api, woocommerce]
---

# 📦 Eksport katalogu — produkty, kategorie, zdjęcia

Żywa dokumentacja eksportu (aktualizujemy przy zmianach skryptów). Kompletny katalog z Shopera,
podzielony na grupy do decyzji o imporcie. ⤴ [[MOC-Migracja]] · [[migracja-shoper-woocommerce]]

## Jak odtworzyć (z korzenia repo)

```bash
node --env-file=.env skrypty/shoper/pobierz-tresci.mjs   # API → dane/surowe/      (~10 min)
node skrypty/strona/migawka-seo.mjs                      # strony → dane/migawka/  (~35 min, wznawialne)
node skrypty/eksport/buduj-katalog.mjs                   # → dane/eksport/         (sekundy)
node skrypty/eksport/pobierz-zdjecia.mjs                 # → dane/eksport/*/zdjecia/ (wznawialne)
```

`buduj-katalog` łączy dane z API z migawką SEO ([[migawka-seo-2026-10-16]]) — migawka musi istnieć.

## Grupy (stan z 2026-10-16)

| Grupa | Produktów | Zdjęć | Z wariantami | W sitemapie | Decyzja |
|---|---|---|---|---|---|
| `aktywne-na-stanie` | 607 | 1263 | 60 | 607 | ✅ **importujemy** (właściciel, 2026-10-16) |
| `aktywne-stan-0` | 988 | 2960 | 1 | 983 | do ustalenia |
| `nieaktywne-na-stanie` | 1696 | 3069 | 25 | 0 | do ustalenia — wyłączone, a mają stan |
| `nieaktywne-stan-0` | 739 | 1588 | 0 | 0 | do ustalenia |

**Stan** = suma stanów aktywnych wariantów (gdy produkt ma warianty), inaczej stan magazynu
podstawowego. Zdjęcia: 8880 oryginałów, 269 MB, zero błędów.

5 aktywnych produktów spoza sitemapy (obrączki A3/A4/A5, fazowane 4 i 5 mm) to produkty z wyłączonej
kategorii „Dostępne od ręki” — w arkuszu „Kategoria aktywna: nie”.

## Co jest w folderze grupy

`dane/eksport/<grupa>/`:

- `produkty.csv` — przegląd w Excelu (średnik, UTF-8 z BOM): ID, nazwa, kod, cena, stan, ścieżka
  kategorii („Złoto > Złote kolczyki”), wszystkie kategorie, adres, w sitemapie, liczba zdjęć
  i wariantów, długość opisu, daty.
- `produkty.json` — pełny rekord: `url` (ścieżka, permalink, seo_url), `seo.ze_strony` (title,
  description, canonical, H1 z migawki) i `seo.z_api`, kategoria główna i wszystkie, opis HTML
  (bez znacznika `<!--[mode:tiny]-->`), atrybuty (grupa / nazwa / wartość), warianty (opcje, cena,
  stan), zdjęcia (kolejność, główne, alt, plik).
- `zdjecia/<shoper_id>/<nr>-<gfx_id>.<ext>` — oryginały w kolejności ze sklepu.

`dane/eksport/kategorie.json` — 142 kategorie w kolejności drzewa: rodzic, głębokość, ścieżka,
aktywność (sama i z rodzicami), adres, SEO z API i ze strony, **`opis_gora` i `opis_dol`**.

Kategorie podzielone na grupy nie są — produkt może należeć do kilku kategorii, więc podział
na foldery wg kategorii zdublowałby zdjęcia. Filtrowanie po kategorii: kolumna w CSV.

## Struktura kategorii

Hierarchia jest w danych (`categories-tree`), **adresy są płaskie**: `/zlote-kolczyki`, nie
`/zloto/zlote-kolczyki`. Tak zostaje w WooCommerce. Kategoria nadrzędna (Złoto, Srebro) ma 0
produktów przypisanych wprost — strona pokazuje produkty podkategorii (WooCommerce robi tak samo).
Stare drzewo „Biżuteria” (~100 podkategorii) jest całe wyłączone, 0 aktywnych produktów.

## Pułapki

- **Cena wariantu z API bywa nieaktualna.** Przy `price_type = 0` („jak produkt”) pole `comp_price`
  wariantu potrafi mieć starą cenę (produkt 2515: wariant 1049 zł, strona 1289 zł). Bierzemy cenę
  produktu; własną cenę ma tylko `price_type = 1` (2 warianty). Po poprawce: **1590 / 1590 cen
  produktów i 268 / 268 wariantów zgodnych ze stroną** (`itemprop="price"` w migawce).
- `product-stocks` zawiera magazyn podstawowy (`extended = 0`) i warianty (`extended = 1`) — nie sumować obu.
- Oryginał zdjęcia: `/userdata/gfx/<gfx_id>.<ext>` (nazwa pliku w adresie nie ma znaczenia).
- Nazwy produktów bywają ze spacją na początku (`" Złoty pierścionek…"`) — przy imporcie przyciąć,
  ale title/meta zostają z migawki.
- Część slugów jest nieczytelna (`/1-2342`) — zostają, bo są zaindeksowane.

## Powiązane

[[sondaz-api-shoper-2026-10-16]] · [[migawka-seo-2026-10-16]] · [[migracja-shoper-woocommerce]] · [[MOC-Migracja]]
