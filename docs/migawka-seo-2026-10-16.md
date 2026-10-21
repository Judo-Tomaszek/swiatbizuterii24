---
aliases: [Migawka SEO, Inwentaryzacja URL]
tags: [seo, url, migracja]
---

# 📸 Migawka SEO sklepu — stan na 2026-10-16

Przebieg po **wszystkich 1808 adresach** (sitemap + strona główna) przed migracją: status, title,
meta description, robots, canonical, H1, `og:`. To jest **wzorzec do porównania po migracji** —
każdy adres z tej listy musi po przełączeniu dać to samo (albo świadomie zmienione).
**Fotografia stanu** — nie aktualizujemy wstecz. ⤴ [[MOC-SEO-i-URL]] · [[migracja-shoper-woocommerce]]

Skrypt: `skrypty/strona/migawka-seo.mjs` (1 żądanie/s, wznawialny). Dane: `dane/migawka/wyniki.jsonl`
(wiersz = adres) + `dane/migawka/html/<nr>.html.gz` (pełny HTML, 51 MB) — z niego wyciągamy teksty
spoza API (strona główna), bez ponownego chodzenia po sklepie.

## Wynik

| Miara | Wynik |
|---|---|
| Adresy | 1808 — **wszystkie `200`**, zero przekierowań, zero błędów |
| Title / description | są na każdej stronie |
| `meta robots` | brak na wszystkich (domyślnie index, follow) |
| Canonical | wskazuje na siebie na 1807 stronach; wyjątek: `/zlote-zawieszki/7` → `/zlote-zawieszki/6` (błąd Shopera przy ostatniej stronie paginacji) |
| Paginacja kategorii | 120 stron (`/zloto/2` …); **title jak strona 1**, canonical na siebie |

## Szablony Shopera (do odtworzenia)

Tam, gdzie pola SEO w panelu są puste, Shoper skleja:

| Typ | `<title>` | Meta description |
|---|---|---|
| Produkt | `<nazwa> \| Jubiler Opole \| Swiatbiżuterii24` | `<nazwa> w kategorii <kategoria>` |
| Kategoria | `<nazwa>  \| Jubiler Opole \| Swiatbiżuterii24` (dwie spacje) | `Lista produktów kategorii <nazwa>` |
| Blog | `Blog - <tytuł> \| Jubiler Opole \| Swiatbiżuterii24` | |

**Decyzja do podjęcia przy imporcie:** zapisujemy title/meta **dosłownie z migawki** dla każdego
obiektu (pewne 1:1, rekomendowane dla stron z [[strony-kluczowe-seo-2026-10-16]]) czy odtwarzamy
szablon we wtyczce SEO (prościej przy nowych produktach). Rekomendacja: dosłownie dla wszystkich
istniejących obiektów, szablon tylko dla nowych.

## Rzeczy zastane (nie naprawiamy przy migracji — tylko notujemy)

- **Zdublowane tytuły: 145 grup, 459 stron** (bez paginacji) — np. 20 produktów „Spinki do mankietów
  stal szlachetna”. Produkty o identycznej nazwie różnią się tylko końcówką sluga (`-1503`, `-1504`).
- **Brak H1:** wszystkie wpisy bloga (28 w `/pl/blog/`), wszystkie strony `/pl/i/` (14) i 23 strony
  w korzeniu (strony/wpisy z własnym adresem); **2 × H1** na 4 stronach. To kwestia szablonu Shopera —
  nowy motyw da H1 sam; do poprawy *po* migracji, jako świadoma zmiana.
- **Blog nie wyświetla dat** — w HTML wpisu „Kształty diamentów” nie ma żadnej daty, w API daty są
  puste (`0000-00-00`). Daty publikacji w WordPressie nadamy umownie (decyzja przy imporcie bloga).

## Powiązane

[[sondaz-api-shoper-2026-10-16]] · [[strony-kluczowe-seo-2026-10-16]] · [[migracja-shoper-woocommerce]] · [[MOC-SEO-i-URL]]
