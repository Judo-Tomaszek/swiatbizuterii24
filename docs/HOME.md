---
aliases: [Home, Pulpit dokumentacji, swiatbizuterii24]
tags: [moc, home]
---

# 📖 swiatbizuterii24 — dokumentacja

Migracja sklepu **swiatbizuterii24.pl** z Shopera na WordPress + WooCommerce. To jest **pulpit
dokumentacji** — punkt startowy vaultu Obsidian. Dokumenty są połączone linkami `[[…]]` — użyj
grafu i backlinków, żeby zobaczyć powiązania.

> [!info] Nowy w projekcie? Zacznij tu
> 1. [[DZIENNIK#§1 — 2026-10-16 — Start projektu: cel migracji i system dokumentacji|§1 — start projektu]] — cel, założenia, co już ustalone.
> 2. [[migracja-shoper-woocommerce]] — plan migracji etapami.
> 3. [[KONWENCJA-DOKUMENTACJI]] — jak prowadzić tę dokumentację.

> [!warning] Zasada nadrzędna
> Struktura adresów stron sprzedażowych (i jeśli się da — bloga) **zostaje zachowana**.
> Każdy stary URL działa albo daje 301 na swój odpowiednik. Patrz [[MOC-SEO-i-URL]].

## 🗺️ Mapy tematyczne (MOC)

- [[MOC-Migracja]] — API Shopera, eksport, transformacja, import do WooCommerce.
- [[MOC-SEO-i-URL]] — struktura adresów, mapa przekierowań, blog, SEO.
- [[MOC-WordPress]] — WordPress, WooCommerce, motyw, wtyczki, konfiguracja.
- [[MOC-Wdrozenie]] — środowiska, hosting, przełączenie domeny.

## 🗂️ Dziennik prac

- [[SPIS-DZIENNIKA]] — klikalny indeks wpisów, pogrupowany po dniach.
- [[DZIENNIK]] — pełna kronika (jeden log, chronologicznie). Nowe wpisy **na końcu** — patrz [[KONWENCJA-DOKUMENTACJI]].

## 📚 Wszystkie dokumenty

### Plany

| Dokument | Obszar | O czym |
|---|---|---|
| [[migracja-shoper-woocommerce]] | plan | Plan migracji Shoper → WooCommerce etapami 0–7. Sklep ma **dwa schematy adresów naraz** (własne w korzeniu + `/pl/p/…/<id>`), więc kierunek C: stare adresy przez tabelę ścieżek, nowe wpisy bloga natywnie. **Szkic**, część decyzji otwarta. |
| [[konfiguracja-sklepu]] | plan | Lista ustawień przed startem: dostawy (odbiór, DHL), płatności (PayU, przelew, pobranie), e-maile/SMTP, konta klientów (rejestracja, 39 kont z Shopera, newsletter), faktury, SEO. Kto co robi. **Lista otwarta.** |

### Migracja

| Dokument | Obszar | O czym |
|---|---|---|
| [[sondaz-api-shoper-2026-10-16]] | shoper-api | Pierwsze połączenie z API: 1595 aktywnych produktów z 4030, 142 kategorie, 31 wpisów bloga, 16 stron; każdy adres z sitemapy (1807) ma obiekt w API. Pułapki: title/meta Shoper skleja z szablonu (źródło prawdy = strona), tekst strony głównej poza API. **Fotografia stanu.** |

| [[eksport-katalogu]] | migracja | Eksport katalogu w 4 grupach: **607 aktywnych na stanie (idą do importu)**, 988 aktywnych bez stanu, 1696 nieaktywnych ze stanem, 739 nieaktywnych bez stanu; 8880 zdjęć, kategorie z dwoma opisami. Ceny zweryfikowane ze stroną. Żywy dokument. |

| [[import-woocommerce]] | woocommerce | Lokalny WordPress + WooCommerce w Dockerze (localhost:8080) i import: **1595 aktywnych produktów** (607 na stanie, 988 „brak w magazynie” bez zakupu), 27 kategorii z hierarchią, 4223 zdjęcia; ceny i stany zgodne 1:1. Plus treści: 16 stron, 31 wpisów bloga, strona główna, 100 obrazków z tekstów — **teksty 62 / 62 słowo w słowo**. Regulamin do dostarczenia (widżet zewnętrzny). Żywy dokument. |

### SEO i adresy

| Dokument | Obszar | O czym |
|---|---|---|
| [[adresy-url-wtyczka]] | url | Wtyczka `sb24-migracja`: stare adresy Shopera działają w WordPressie bez przekierowań, linki i paginacja `/zloto/2` w starym formacie, 301 dla `/pl/p/…/<id>`. Drugi opis kategorii pod produktami, teksty bez zmian WordPressa (cudzysłowy, emotikony). Test wszystkich adresów z migawki: **1785 / 1786**. Żywy dokument. |
| [[migawka-seo-2026-10-16]] | seo | Przebieg po 1808 adresach (sitemap + główna): wszystkie `200`, title/meta/canonical/H1 zapisane jako wzorzec do porównania po migracji, pełny HTML w `dane/migawka/`. Szablony title/meta Shopera, 459 stron ze zdublowanym tytułem, blog bez H1 i bez dat. **Fotografia stanu.** |
| [[strony-kluczowe-seo-2026-10-16]] | seo | Strony, które muszą przeżyć migrację 1:1 (główna, naprawa biżuterii, skup złota, 4 kategorie, „Kształty diamentów”): adresy, title i meta z dnia 2026-10-16, zasada „bez poprawiania literówek”. **Fotografia stanu.** |

### Wygląd

| Dokument | Obszar | O czym |
|---|---|---|
| [[wyglad-motyw]] | wordpress | Porównanie Kadence i Blocksy na danych sklepu (`?motyw=kadence` / `?motyw=blocksy`): barwy i logo z Shopera, menu i stopka 1:1, 819 produktów z ceną 0 → „Cena ustalana indywidualnie”. Blocksy bez polskiego tłumaczenia. Żywy dokument. |

### Konwencje

| Dokument | Obszar | O czym |
|---|---|---|
| [[KONWENCJA-DOKUMENTACJI]] | konwencje | Zasady prowadzenia tej dokumentacji. |

## 🔖 Nawigacja po tagach

`#migracja` · `#shoper-api` · `#woocommerce` · `#wordpress` · `#seo` · `#url` · `#blog` ·
`#wdrozenie` · `#plan` · `#konwencje` · `#dziennik` · `#moc`

---
*Pulpit utrzymywany ręcznie. Nowy dokument → dopisz go do tabeli i do właściwego MOC-a (patrz [[KONWENCJA-DOKUMENTACJI]]).*
