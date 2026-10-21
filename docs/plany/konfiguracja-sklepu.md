---
aliases: [Konfiguracja sklepu, Do skonfigurowania]
tags: [plan, woocommerce, konfiguracja]
---

# 🧾 Konfiguracja sklepu przed startem

**Status: lista otwarta (2026-10-19).** Co trzeba ustawić w WooCommerce, żeby sklep przyjmował
zamówienia tak jak Shoper. Stan Shopera odczytany z API (tylko odczyt) 2026-10-19.
⤴ [[HOME]] · [[MOC-WordPress]] · [[MOC-Wdrozenie]] · [[migracja-shoper-woocommerce]]

Legenda: **(ja)** zrobię bez danych od właściciela · **(właściciel)** potrzebne dane, decyzja albo
klucze, które wpisuje właściciel.

## 1. Bez tego nie da się złożyć zamówienia

| # | Co | Shoper dziś | WooCommerce dziś | Kto |
|---|---|---|---|---|
| 1 | **Dostawy** | odbiór osobisty 0 zł; DHL Kurier 21,50 zł, od 70 zł darmowa (przedziały wartości zamówienia), do 10 kg | ✅ strefa „Polska” + odbiór w salonie (2026-10-20); limitu 10 kg brak — produkty nie mają wagi | ja |
| 2 | **PayU** | włączone | brak wtyczki | ja instaluję, właściciel wpisuje klucze z panelu PayU |
| 3 | **Przedpłata przelewem** | włączona | wyłączona | właściciel: numer konta i odbiorca |
| 4 | **Za pobraniem** | włączone | wyłączone | właściciel: czy z dopłatą |
| 5 | **Karta podarunkowa** | włączona | brak w standardzie | właściciel: wtyczka czy rezygnacja |
| 6 | **Adres sklepu** (stopka e-maili, faktury) | Ozimska 17 A, 45-057 Opole | ✅ ustawiony, sprzedaż i wysyłka tylko do Polski (2026-10-20) | ja |

Pozostałe metody w Shoperze (Autopay, Przelewy24, PayPal, raty, Klarna, InPost Pay, Stripe…) są
wyłączone — nie przenosimy.

## 2. E-maile

| # | Co | Stan | Kto |
|---|---|---|---|
| 7 | Nadawca e-maili sklepu | ✅ (2026-10-21) nadawca: „Świat Biżuterii 24” + skrzynka sklepu w domenie (zakłada właściciel); powiadomienia o zamówieniach (nowe, anulowane, nieudane) i formularz (`sb24_email_kontakt`) → skrzynka sklepu + adres właściciela | właściciel: założyć skrzynkę |
| 8 | Wysyłka przez SMTP (wtyczka) | brak → potwierdzenia lądują w spamie | właściciel: hasło aplikacji / dane skrzynki |
| 9 | Czas wysyłki przy produktach | Shoper: słownik „24 godziny … 21 dni” | właściciel: czy pokazywać |

## 3. Konta klientów

| # | Co | Stan | Kto |
|---|---|---|---|
| 10 | Rejestracja na „Moje konto” i przy zamówieniu, zakup bez konta zostaje | ✅ włączona (2026-10-19) | ja |
| 11 | Adresy podstron konta po polsku (`/moje-konto/zamowienia` zamiast `/orders`) | ✅ po polsku (2026-10-19) | ja |
| 12 | 301 ze starych adresów Shopera `/pl/login`, `/pl/panel` → `/moje-konto/` | ✅ działało już we wtyczce `sb24-migracja` (także `/pl/reg`) | ja |
| 13 | Przeniesienie kont: 39 zarejestrowanych (bez haseł → „Nie pamiętasz hasła?”), opcjonalnie 46 zamówień do historii | ✅ 39 kont przeniesionych (2026-10-19); zamówienia nie | ja (zamówienia: decyzja właściciela) |
| 14 | Zgody newslettera (96 osób w Shoperze) — dokąd? | brak narzędzia | właściciel: decyzja |
| 15 | Ochrona rejestracji przed spamem (np. Turnstile / honeypot) | ✅ pułapka + min. 3 s (2026-10-19) | ja |
| 16 | Baner „Załóż konto −10%” = kupon za rejestrację | do decyzji od §4 | właściciel |

Kształt danych Shopera (bez danych osobowych): 498 rekordów `users`, z czego **39 to konta
zarejestrowane** (`registered=1`), reszta to klienci bez konta (głównie import z 2021). 46 zamówień
2021–2026, wszystkie przypięte do rekordu klienta. Grupy i rabaty klientów — nieużywane.

## 4. Przed startem

| # | Co | Kto |
|---|---|---|
| 17 | Faktury z sklepu (Fakturownia / wFirma / iFirma — zależnie od używanego programu) | właściciel: który program |
| 18 | Wtyczka SEO (title/meta 1:1, sitemap) + 301 bloga | ✅ Yoast SEO (2026-10-19); 301 bloga już we wtyczce migracji |
| 19 | Wyłączenie trybu „wkrótce otwarcie” — w dniu przełączenia domeny | ja, w dniu startu |
| 20 | Telefon w Salonie (przesunięty drugi numer) | ja |

## 5. Po wgraniu paczki Duplicatora na serwer

Paczka z 2026-10-21 (`dane/paczka/`, poza repo): sklep otwarty (bez „wkrótce otwarcie”), bez konta testowego,
bez Astry/Blocksy, bez `wp-content/cache`. Na serwerze, po instalatorze:

1. **Usunąć pliki instalatora** (Duplicator proponuje to po zalogowaniu) — zawierają dostęp do bazy.
2. **Poczta**: w `wp-config.php` stałe `SB24_SMTP_HOST`, `SB24_SMTP_PORT`, `SB24_SMTP_USER`, `SB24_SMTP_PASSWORD`
   (wartości z lokalnego `.env`); `SB24_MAILPIT` NIE ustawiać. Test: formularz na `/kontakt`.
3. **WP Fastest Cache**: Ustawienia → Zapisz (przebudowuje reguły w `.htaccess` dla nowej domeny — lokalne
   mają `localhost:8080`), potem „Usuń cache”.
4. **Adres panelu**: WPS Hide Login (Ustawienia → Ogólne → Login url) — nowy, nieoczywisty; login admina ≠ „admin”.
5. **Permalinki**: struktura `/blog/%postname%` (bez ukośnika — inaczej WP Fastest Cache nie zapisuje stron
   z adresami Shopera); Ustawienia → Bezpośrednie odnośniki → Zapisz. Potem `wp sb24 przebuduj`.
6. **Yoast**: `wp yoast index --reindex`; Search Console — nowa mapa witryny.
7. **Zdjęcia ChatGPT** robione dalej lokalnie: na serwer wgrywać `dane/eksport/zdjecia-webp/produkty/`
   i uruchamiać tam `importuj-tresci.php zdjecia` (patrz pamięć „podmiana zdjęć”).
8. Sprawdzić: Narzędzia → Kondycja witryny (pętla zwrotna, cron), Narzędzia → Zaplanowane akcje.

## Zrobione

- 2026-10-21 — **WP Fastest Cache** (`skrypty/import/ustaw-cache.php`): cache dla niezalogowanych, preload,
  minifikacja HTML/CSS, łączenie CSS, gzip, cache przeglądarki; bez łączenia JS. Permalinki zmienione na
  `/blog/%postname%` (bez ukośnika) — sprawdzone: 1807 adresów z mapy Shopera bez zmian.
- 2026-10-21 — **paczka Duplicatora** (518 MB) w `dane/paczka/`; usunięte konto testowe, Astra, Blocksy (+ Companion).

- 2026-10-21 — **logowanie**: limit prób (5 w 15 min z IP → 20 min, 3. blokada w dobie → 24 h) dla panelu,
  „Moje konto” i XML-RPC; jeden komunikat „Nieprawidłowy login lub hasło”; historia z odblokowaniem w
  **Narzędzia → Próby logowania** (30 dni); XML-RPC wyłączony; `/?author=N` i REST `users` → 404
  (`wordpress/wtyczki/sb24-migracja/bezpieczenstwo.php`). Panel pod ukrytym adresem — wtyczka
  **WPS Hide Login** (adres w opcji `whl_page`, nie w repo); `/wp-login.php` i `/wp-admin` → 404.
  Klienci bez zmian: `/moje-konto/`. Na serwerze: ustawić własny adres panelu i login admina ≠ „admin”.

- 2026-10-21 — **formularz kontaktowy** `[sb24_formularz]` na `/kontakt` i `/salon-firmowy` (wtyczka `sb24-migracja`):
  wiadomość e-mailem do skrzynki sklepu i właściciela z Reply-To klienta, pułapka + min. 3 s + limit 5/h z IP, zgoda RODO,
  nic w bazie. Ze strony produktu link „Zapytaj o ten produkt” wypełnia pytanie. Lokalnie poczta nie
  wychodzi (brak SMTP) — test przez przechwycenie `wp_mail`. **Do działania na serwerze: pkt 8 (SMTP).**

- 2026-10-21 — **zakupy online wyłączone** (decyzja właściciela, na razie): zamiast „Dodaj do koszyka”
  zaproszenie do salonu z telefonem, bez koszyka w nagłówku, koszyk i kasa → `/salon-firmowy`.
  Włączenie: `skrypty/wp.sh option update sb24_zakupy_online tak` (wtyczka `sb24-migracja`).

- 2026-10-20 — **dostawy i adres sklepu** (`skrypty/import/ustaw-dostawy.php`): DHL Kurier 21,50 zł,
  od 70 zł 0 zł (płatną stawkę chowa wtyczka `sb24-migracja`), odbiór osobisty w salonie (kasa blokowa).
  Dopłata 20 zł za pobranie z Shopera — do ustawienia przy płatnościach (pkt 4).

- 2026-10-19 — **Yoast SEO**: title/meta 1:1 z Shopera, canonical bez ukośnika, sitemap, noindex
  koszyka/kasy/konta (`skrypty/import/ustaw-seo.php`) — [[DZIENNIK#§8 — 2026-10-19 — Yoast SEO 1:1, konta klientów z Shopera, rejestracja|§8]].
- 2026-10-19 — **konta**: rejestracja, polskie podstrony, ochrona przed botami
  (`ustaw-konta.php`), 39 kont z Shopera bez haseł (`buduj-konta.mjs` → `importuj-konta.php`).

- 2026-10-19 — stopka: „Wykonanie: NETHOUSE” z linkiem do nethouse.tech (`ustaw-wyglad.php`).
- 2026-10-19 — zgody cookies, GTM, Search Console, regulamin i polityka podpięte, opinie tylko od
  kupujących — patrz [[DZIENNIK#§6 — 2026-10-19 — Zdjęcia WebP bez metadanych AI, reszta zdjęć, favicon, strony prawne i cookies|§6]].
