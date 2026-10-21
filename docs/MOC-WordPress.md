---
aliases: [MOC WordPress, WooCommerce]
tags: [moc, wordpress, woocommerce]
---

# 🧩 MOC — WordPress i WooCommerce

Mapa obszaru: instalacja, motyw, wtyczki, konfiguracja sklepu, permalinki. ⤴ [[HOME]]

| Dokument | O czym |
|---|---|
| [[import-woocommerce]] | **Lokalny WordPress w Dockerze** (localhost:8080, WP 7.1.3, WC 11.2.1) i import katalogu: 1595 aktywnych produktów, 27 kategorii, 4223 zdjęcia, ceny i stany zgodne 1:1. Odwzorowanie pól, pułapki środowiska. |
| [[wyglad-motyw]] | **Kadence vs Blocksy** na prawdziwych danych (`?motyw=`), barwy marki, menu i stopka 1:1 z Shopera, „Cena ustalana indywidualnie” zamiast 0 zł (819 produktów). Decyzja właściciela otwarta. |
| [[konfiguracja-sklepu]] | **Lista ustawień przed startem**: dostawy, płatności, e-maile, konta klientów, faktury — z podziałem kto co robi. |
| *(do napisania)* | Lista wtyczek z uzasadnieniem wyboru. |
| [[adresy-url-wtyczka]] | Własna wtyczka `sb24-migracja` — stare adresy Shopera zamiast permalinków WooCommerce; pułapka `do_parse_request` w WP 6+. |
