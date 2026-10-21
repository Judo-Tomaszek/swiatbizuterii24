---
aliases: [Konwencja dokumentacji, Zasady docs]
tags: [konwencje, meta]
---

# 🧭 Konwencja dokumentacji (Obsidian)

Zasady, żeby dokumentacja została **spójna i nawigowalna** przy dalszym rozwoju. ⤴ [[HOME]]

Wzorzec przeniesiony z projektu `club-manager-api` (2026-10-16).

## Vault

- **Vault = całe repozytorium.** W Obsidianie: *Open folder as vault* → wskaż korzeń repo
  (`swiatbizuterii24`).
- Szum (`node_modules`, `.git`, `dane/`, `tmp/`, pliki WordPressa) jest wykluczony w
  `.obsidian/app.json` (`userIgnoreFilters`). Pojawi się nowy śmieciowy katalog — dopisz go tam.
- Linki: **wikilinki** `[[Nazwa]]` w formacie „shortest”. Dla plików o powtarzalnej nazwie
  (np. kilka `README`) linkuj ścieżką z aliasem: `[[skrypty/README|skrypty/README]]`.

## Struktura — świadomie płaska

Plików **nie przenosimy** do folderów `00-/01-…`. Porządek dają **MOC-i + tagi + linki**, nie foldery.

- Pulpit: [[HOME]].
- Mapy: [[MOC-Migracja]], [[MOC-SEO-i-URL]], [[MOC-WordPress]], [[MOC-Wdrozenie]].
- Kronika: [[DZIENNIK]] (jeden log) + [[SPIS-DZIENNIKA]] (generowany indeks).

### Wyjątek: plany → `docs/plany/`

**Plany prac powstają w `docs/plany/`**, nie w korzeniu `docs/`. Plan dostaje tag `plan`, wpis
w [[HOME]] i we właściwym MOC-u jak każdy dokument. Przeniesienie pliku wolno zrobić tylko razem
z poprawką **wszystkich odwołań ścieżką** (`grep -rn "docs/nazwa\.md"`) w tym samym commicie —
wikilinki `[[nazwa]]` przeżyją przeniesienie same, dopóki nazwa pliku jest unikalna.

## Frontmatter

Każdy dokument zaczyna się od:

```yaml
---
aliases: [Krótka nazwa]      # opcjonalne, ułatwia wyszukiwanie i linkowanie
tags: [obszar, ...]          # z taksonomii niżej
---
```

## Taksonomia tagów

`#migracja` · `#shoper-api` · `#woocommerce` · `#wordpress` · `#seo` · `#url` · `#blog` ·
`#wdrozenie` · `#plan` · `#konwencje` · `#dziennik` · `#moc` · `#meta`

## Dziennik — jak dopisywać wpisy

1. Nowy wpis **na samym końcu** [[DZIENNIK]], nagłówek **dokładnie** w formacie:
   `## §N — RRRR-MM-DD — Tytuł` (myślniki „—”, nie zwykłe `-`; spacje jak w istniejących wpisach).
   Zły format = wpis nie trafi do indeksu.
2. W treści: co zrobione, **kluczowe decyzje wraz z uzasadnieniem**, dotknięte pliki, stan „w toku”,
   `[[wikilinki]]` do powiązanych dokumentów.
3. **Odśwież indeks** z korzenia repo:
   ```bash
   node docs/_narzedzia/gen-spis.mjs
   ```
   Przebuduje [[SPIS-DZIENNIKA]] z nagłówków. **Nie edytuj SPIS-u ręcznie** — zostanie nadpisany.

> [!tip] Co jest warte wpisu, a co nie
> Dziennik ma odpowiadać na pytanie **„dlaczego to tak wygląda”** za pół roku. Zapisuj decyzje,
> odrzucone warianty, pułapki i stan niedokończony. Nie przepisuj tego, co i tak widać w kodzie
> i `git log` — od tego jest git.

## Dokumenty z datą w nazwie

Inwentaryzacje, audyty i analizy (np. `inwentaryzacja-url-2026-10-20`) to **fotografie stanu
z danego dnia**. Nie aktualizujemy ich wstecz — gdy stan się zmieni, powstaje nowy dokument albo
wpis w dzienniku, a stary zostaje jako historia.

## Dane w dokumentacji

Dokumentacja opisuje **kształt** danych (pola, typy, liczności, przykłady z wymyślonymi wartościami),
nigdy prawdziwych danych klientów i zamówień. Przykładowe produkty i URL-e sklepu — w porządku,
to dane publiczne.

## Nowy dokument — checklista

1. Utwórz `docs/NAZWA.md` z frontmatterem (tagi) — **plan: `docs/plany/NAZWA.md`** z tagiem `plan`.
2. Dodaj go do tabeli w [[HOME]] i do właściwego **MOC-a**.
3. Wpleć `[[wikilinki]]` do i z pokrewnych dokumentów (sekcja „Powiązane” na końcu).

## Powiązane

[[HOME]] · [[DZIENNIK]] · [[SPIS-DZIENNIKA]]
