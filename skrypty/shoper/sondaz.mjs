// Sondaż API Shopera: co jest dostępne i ile tego jest.
// Uruchomienie z korzenia repo:  node --env-file=.env skrypty/shoper/sondaz.mjs
// Dla każdego zasobu: status HTTP + liczba rekordów; dla zasobów bez danych osobowych zapisuje
// JEDEN przykładowy rekord do dane/sondaz/<zasob>.json (dane/ jest poza gitem).
import fs from "node:fs";
import path from "node:path";
import { get } from "./klient.mjs";

const OUT = path.resolve("dane", "sondaz");
fs.mkdirSync(OUT, { recursive: true });

// osobowe: true → tylko liczba, bez zapisu próbki
const ZASOBY = [
  ["products"], ["categories"], ["categories-tree"], ["producers"], ["product-images"],
  ["product-stocks"], ["attributes"], ["attribute-groups"], ["options"], ["option-values"],
  ["option-groups"], ["availabilities"], ["deliveries"], ["units"], ["taxes"], ["currencies"],
  ["languages"], ["aboutpages"], ["news"], ["news-categories"], ["news-tags"], ["news-comments"],
  ["redirects"], ["metafields"], ["gauges"], ["product-files"], ["specialoffers"],
  ["shippings"], ["payments"], ["statuses"], ["user-groups"], ["subscribers", true],
  ["users", true], ["orders", true], ["product-comments", true],
];

const wiersze = [];
for (const [zasob, osobowe] of ZASOBY) {
  const { status, body } = await get(zasob, { limit: 1 });
  const ile = status === 200 ? (body.count ?? (Array.isArray(body) ? body.length : "?")) : "-";
  if (status === 200 && !osobowe) {
    const probka = body.list ? body.list[0] : body;
    if (probka !== undefined) fs.writeFileSync(path.join(OUT, `${zasob}.json`), JSON.stringify(probka, null, 2));
  }
  const blad = status === 200 ? "" : String(body?.error_description || body?.error || body).slice(0, 80);
  wiersze.push({ zasob, status, ile, blad });
  console.log(`${zasob.padEnd(18)} HTTP ${status}  ${String(ile).padStart(6)}  ${blad}`);
}

fs.writeFileSync(path.join(OUT, "_podsumowanie.json"), JSON.stringify(wiersze, null, 2));
