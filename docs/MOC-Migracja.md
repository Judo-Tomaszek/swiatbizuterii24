---
aliases: [MOC Migracja, Migracja]
tags: [moc, migracja]
---

# 🔄 MOC — Migracja

Mapa obszaru: API Shopera, eksport danych, transformacja, import do WooCommerce. ⤴ [[HOME]]

## Plan

| Dokument | O czym |
|---|---|
| [[migracja-shoper-woocommerce]] | Plan całej migracji etapami — punkt wejścia. |

## API Shopera

| Dokument | O czym |
|---|---|
| [[sondaz-api-shoper-2026-10-16]] | **Co da się przenieść**: liczności zasobów, braki uprawnień tokenu, pełne pokrycie adresów z sitemapy, pułapki (title/meta z szablonu, tekst strony głównej poza API, dwa pola opisu kategorii). Skrypty w `skrypty/shoper/`. |

## Eksport

| Dokument | O czym |
|---|---|
| [[eksport-katalogu]] | **Komplet katalogu** w 4 grupach (aktywne/nieaktywne × stan 0/>0): CSV do przeglądu, pełne JSON-y, 8880 zdjęć, kategorie z opisami. Jak odtworzyć, pułapka cen wariantów. |

## Import do WooCommerce

| Dokument | O czym |
|---|---|
| [[import-woocommerce]] | Mapowanie pól Shoper → WooCommerce, skrypt importu (idempotentny), wynik i weryfikacja. |

> [!warning] Każdy rekord niesie ID i URL z Shopera
> Bez oryginalnego ID i adresu nie zbudujemy mapy przekierowań ani ponownego importu.
> Patrz [[MOC-SEO-i-URL]].
