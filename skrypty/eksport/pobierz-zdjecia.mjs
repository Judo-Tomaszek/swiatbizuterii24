// Pobiera oryginały zdjęć produktów do dane/eksport/<grupa>/zdjecia/<id>/<nr>-<gfx_id>.<ext>
// na podstawie pól `zdjecia[].zrodlo` / `plik` z produkty.json (najpierw buduj-katalog.mjs).
// Uruchomienie z korzenia repo:  node skrypty/eksport/pobierz-zdjecia.mjs [grupa ...]
// Wznawialne: istniejące niepuste pliki pomija. 4 pobrania naraz (pliki idą z CDN Shopera).
// Błędy trafiają do dane/eksport/<grupa>/bledy-zdjec.json.
import fs from "node:fs";
import path from "node:path";

const OUT = path.resolve("dane", "eksport");
const GRUPY = process.argv.slice(2).length
  ? process.argv.slice(2)
  : ["aktywne-na-stanie", "aktywne-stan-0", "nieaktywne-na-stanie", "nieaktywne-stan-0"];
const ROWNOLEGLE = 4;
const UA = "swiatbizuterii24-migracja/1.0";

for (const g of GRUPY) {
  const produkty = JSON.parse(fs.readFileSync(path.join(OUT, g, "produkty.json"), "utf8"));
  const zadania = produkty.flatMap((p) => p.zdjecia.map((z) => ({ ...z, cel: path.join(OUT, g, z.plik) })))
    .filter((z) => !(fs.existsSync(z.cel) && fs.statSync(z.cel).size > 0));
  const bledy = [];
  let zrobione = 0;
  console.log(`${g}: do pobrania ${zadania.length}`);

  async function pracownik() {
    while (zadania.length) {
      const z = zadania.shift();
      try {
        const res = await fetch(z.zrodlo, { headers: { "User-Agent": UA } });
        if (!res.ok || !(res.headers.get("content-type") || "").startsWith("image/")) {
          throw new Error(`HTTP ${res.status} ${res.headers.get("content-type")}`);
        }
        fs.mkdirSync(path.dirname(z.cel), { recursive: true });
        fs.writeFileSync(z.cel, Buffer.from(await res.arrayBuffer()));
      } catch (e) {
        bledy.push({ zrodlo: z.zrodlo, plik: z.plik, blad: String(e.message || e) });
      }
      if (++zrobione % 500 === 0) console.log(`  ${g}: ${zrobione}`);
    }
  }
  await Promise.all(Array.from({ length: ROWNOLEGLE }, pracownik));
  fs.writeFileSync(path.join(OUT, g, "bledy-zdjec.json"), JSON.stringify(bledy, null, 2));
  console.log(`${g}: gotowe, błędów ${bledy.length}`);
}
console.log("Koniec.");
