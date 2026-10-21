// Pobiera z API Shopera komplet danych katalogu i treści do dane/surowe/<zasob>.json.
// Uruchomienie z korzenia repo:  node --env-file=.env skrypty/shoper/pobierz-tresci.mjs [zasob ...]
// Bez argumentów pobiera wszystko z listy ZASOBY. Surowe odpowiedzi bez zmian — transformacja
// pracuje na nich, bez ponownego pytania API. Pełny przebieg: ok. 10 min (limit zapytań).
import fs from "node:fs";
import path from "node:path";
import { get, getAll } from "./klient.mjs";

const ZASOBY = [
  "products", "product-images", "product-stocks",
  "options", "option-values", "option-groups",
  "attributes", "attribute-groups",
  "categories", "producers", "taxes", "units",
  "aboutpages", "news", "news-categories", "news-tags",
];
// Zasoby, które zwracają strukturę zamiast stronicowanej listy
const POJEDYNCZE = ["categories-tree"];

const OUT = path.resolve("dane", "surowe");
fs.mkdirSync(OUT, { recursive: true });
const wybrane = process.argv.slice(2);

for (const z of wybrane.length ? wybrane.filter((z) => ZASOBY.includes(z)) : ZASOBY) {
  const lista = await getAll(z);
  fs.writeFileSync(path.join(OUT, `${z}.json`), JSON.stringify(lista, null, 2));
  console.log(`${z.padEnd(18)} ${lista.length}`);
}
for (const z of wybrane.length ? wybrane.filter((z) => POJEDYNCZE.includes(z)) : POJEDYNCZE) {
  const { status, body } = await get(z);
  if (status !== 200) throw new Error(`${z}: HTTP ${status}`);
  fs.writeFileSync(path.join(OUT, `${z}.json`), JSON.stringify(body, null, 2));
  console.log(`${z.padEnd(18)} ok`);
}
console.log("Koniec.");
