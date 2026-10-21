// Pobiera dane osobowe ze Shopera (klienci, adresy, zamówienia) do dane/surowe/prywatne/.
// Uruchomienie z korzenia repo:  node --env-file=.env skrypty/shoper/pobierz-prywatne.mjs
// ⚠️ Dane osobowe: tylko lokalnie (dane/ jest poza gitem), nie kopiujemy do dokumentacji.
// Zasób niedostępny dla tokenu (403/404) jest odnotowany i pomijany — nie przerywa przebiegu.
import fs from "node:fs";
import path from "node:path";
import { getAll } from "./klient.mjs";

const OUT = path.resolve("dane", "surowe", "prywatne");
fs.mkdirSync(OUT, { recursive: true });

for (const z of ["users", "user-addresses", "orders", "order-products", "parcels", "subscribers"]) {
  try {
    const lista = await getAll(z);
    fs.writeFileSync(path.join(OUT, `${z}.json`), JSON.stringify(lista, null, 2));
    console.log(`${z.padEnd(16)} ${lista.length}`);
  } catch (e) {
    console.log(`${z.padEnd(16)} pominięty: ${String(e.message).slice(0, 120)}`);
  }
}
console.log("Koniec.");
