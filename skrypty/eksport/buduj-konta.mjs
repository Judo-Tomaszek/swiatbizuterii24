// Składa konta klientów do importu: tylko konta ZAREJESTROWANE w Shoperze (`registered=1`).
// Uruchomienie z korzenia repo:  node skrypty/eksport/buduj-konta.mjs
// Wejście: dane/surowe/prywatne/{users,user-addresses}.json (pobierz-prywatne.mjs).
// Wynik: dane/eksport/konta/klienci.json → skrypty/import/importuj-konta.php.
// ⚠️ Dane osobowe: tylko lokalnie (dane/ jest poza gitem). Do konsoli wypisujemy wyłącznie liczby.
//
// Decyzje (docs/plany/konfiguracja-sklepu.md, pkt 13):
// - klientów bez konta (zakupy jako gość, import 2021) nie zakładamy jako kont — sami ich nie zakładali;
// - haseł API nie daje → klient ustawia nowe przez „Nie pamiętasz hasła?”;
// - adres z książki adresowej Shopera (max 1 na konto) → adres rozliczeniowy i dostawy;
// - zgoda na newsletter zapisywana tylko jako informacja (`newsletter`) — narzędzie do decyzji właściciela.
import fs from "node:fs";
import path from "node:path";

const PRYWATNE = path.resolve("dane", "surowe", "prywatne");
const OUT = path.resolve("dane", "eksport", "konta");
const L = (z) => JSON.parse(fs.readFileSync(path.join(PRYWATNE, `${z}.json`), "utf8"));
fs.mkdirSync(OUT, { recursive: true });

const adresy = new Map();
for (const a of L("user-addresses")) {
  // domyślny adres wygrywa, gdyby kiedyś było ich więcej
  if (!adresy.has(a.user_id) || a.default === "1") adresy.set(a.user_id, a);
}
const t = (s) => (s ?? "").trim();

const klienci = L("users")
  .filter((u) => u.registered === "1")
  .map((u) => {
    const a = adresy.get(u.user_id);
    return {
      shoper_id: Number(u.user_id),
      email: t(u.email).toLowerCase(),
      imie: t(u.firstname),
      nazwisko: t(u.lastname),
      data_rejestracji: u.date_add,
      ostatnia_wizyta: u.lastvisit,
      aktywne: u.active === "1",
      newsletter: u.newsletter === "1",
      adres: a
        ? {
            imie: t(a.firstname),
            nazwisko: t(a.lastname),
            firma: t(a.company_name),
            ulica: t(a.street_1),
            ulica_2: t(a.street_2),
            miasto: t(a.city),
            kod: t(a.zip_code),
            kraj: t(a.country_code) || "PL",
            telefon: t(a.phone),
          }
        : null,
    };
  });

fs.writeFileSync(path.join(OUT, "klienci.json"), JSON.stringify(klienci, null, 2));
console.log(`konta: ${klienci.length}, z adresem: ${klienci.filter((k) => k.adres).length}, nieaktywne: ${klienci.filter((k) => !k.aktywne).length}`);
