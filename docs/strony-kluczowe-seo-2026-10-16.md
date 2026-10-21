---
aliases: [Strony kluczowe SEO, Strony priorytetowe]
tags: [seo, url, migracja, blog]
---

# ⭐ Strony kluczowe SEO — stan na 2026-10-16

Lista stron, które **muszą** przeżyć migrację bez zmian: adres, tekst, `<title>`, meta description.
Priorytety od właściciela. Tytuły i opisy pobrane z żywej strony 2026-10-16 (wszystkie: `200`,
canonical wskazuje na siebie, bez przekierowań). **Fotografia stanu** — nie aktualizujemy wstecz.
⤴ [[MOC-SEO-i-URL]] · [[migracja-shoper-woocommerce]]

## Priorytet 1 — pierwsze miejsca w Google (zachować wszystko 1:1)

| Adres | Typ | `<title>` | Meta description |
|---|---|---|---|
| `/` | strona główna | Jubiler Opole \| Sklep jubilerski Swiatbiżuterii24 | Odwieź nasz sklep jubilerski w Opolu przy ulicy Ozimskiej. Szeroki wybór wyrobów ze złota i srebra. Pierścionki zaręczynowe, obrączki ślubne |
| `/naprawa-bizuterii-opole` | strona usługowa | Naprawa Biżuterii Opole \| ŚwiatBiżuterii24.pl | Twoja ukochana biżuteria uległa uszkodzeniu? Sprawdź naszą ofertę. Profesjonalna naprawia biżuterii Opole |
| `/skup-zlota-opole` | strona usługowa | Skup złota Opole \| Jubiler Opole \| Swiatbizuterii24 | Skup złota Opole - Najlepsze ceny, dyskrecja, pełne zadowolenie. Korzystając z usług naszego skupu złota będziesz w pełni zadowolony |

> [!warning] Tekst przenosimy dosłownie — z literówkami
> „Odwieź”, „naprawia biżuterii” to błędy w meta opisach, ale te strony stoją na 1. miejscu.
> Przy migracji **nie poprawiamy** treści ani tytułów — ewentualne poprawki to osobna, świadoma
> decyzja właściciela **po** ustabilizowaniu pozycji.

## Priorytet 2 — kategorie (adres + tekst + title + meta)

| Adres | `<title>` | Meta description |
|---|---|---|
| `/zlote-kolczyki` | Złote kolczyki \| Jubiler Opole \| Swiatbizuterii24 | Złote kolczyki to świetny pomysł na dodanie uroku. Sprawdź ofertę złotych kolczyków w naszym sklepie w Opolu |
| `/srebrne-kolczyki` | Srebrne kolczyki  \| Jubiler Opole \| Swiatbiżuterii24 | Zapraszamy do zapoznania się z naszym asortymentem srebrnych kolczyków. Zakupu można dokonać w sklepie stacjonarnym w Opolu, bądź internecie |
| `/obraczki` | Obrączki  \| Jubiler Opole \| Swiatbiżuterii24 | Lista produktów kategorii Obrączki |
| `/pierscionki-zareczynowe` | Pierścionki zaręczynowe \| Jubiler Opole \| Swiatbizuterii24 | Piękne i tanie pierścionki zaręczynowe. W Opolu znajdziesz nie tylko pierścionki zaręczynowe z diamentem, ale także z rubinem, szmaragdem i opalem |

Pozostałe kategorie — tak samo „w miarę możliwości”: adres, tekst, title, meta.

## Priorytet 1 — blog

| Adres | `<title>` | Meta description |
|---|---|---|
| `/pl/blog/Ksztalty-diamentow/16` | Blog - Kształty diamentów \| Jubiler Opole \| Swiatbiżuterii24 | Wszystko o kształtach diamentów i jakie kształty diamentów są najbardziej popularne w 2022 roku. Sprawdź przykładowe ceny diamentów |

Reszta bloga: przenosimy z tekstem i **starymi adresami** `/pl/blog/<Tytul>/<id>`.
**Nowe wpisy** (dodawane po migracji) mogą mieć już natywną strukturę WordPressa — decyzja właściciela.

## Do sprawdzenia przy eksporcie

- **Skąd pochodzi tekst strony głównej.** W Shoperze bywa w ramce/module szablonu, a nie w zasobie
  API — jeśli API go nie zwróci, bierzemy go z HTML-a strony (z zachowaniem nagłówków H1/H2).
- Czy `/naprawa-bizuterii-opole` i `/skup-zlota-opole` to strony informacyjne (`/pl/i/…/<id>`)
  z własnym adresem, czy kategorie — od tego zależy zasób API.
- Nagłówki H1/H2 i linki wewnętrzne w tekstach — też część „zachowania tekstu”.
- Schemat danych strukturalnych i `og:` — porównać przed/po.

## Powiązane

[[MOC-SEO-i-URL]] · [[migracja-shoper-woocommerce]] · [[DZIENNIK]]
