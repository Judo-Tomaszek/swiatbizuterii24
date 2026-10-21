// Test zachowania adresów: każdy adres z migawki Shopera (dane/migawka/wyniki.jsonl) odpytany na
// nowej stronie — oczekiwany status 200 i ten sam H1 co w Shoperze.
// Uruchomienie z korzenia repo:  node skrypty/strona/sprawdz-adresy.mjs [baza-url]
//   baza-url domyślnie http://localhost:8080 (lokalny WordPress); później staging / produkcja.
// Wynik: dane/sprawdzenie-adresow.json + podsumowanie na konsoli (per typ adresu).
//
// Adresy celowo nieprzenoszone (decyzja właściciela 2026-10-16: puste strony wypadają) liczone
// osobno jako „pominięte”: 20 pustych kategorii spod wyłączonego drzewa „Biżuteria” (+ paginacja)
// i 2 strony producentów bez żadnego aktywnego produktu.
import fs from "node:fs";
import path from "node:path";
// SB24_COOKIE: ciasteczko zalogowanego administratora — gdy sklep jest w trybie „wkrótce otwarcie”
// (WooCommerce pokazuje wtedy niezalogowanym zaślepkę zamiast kategorii i produktów).
const NAGLOWKI = process.env.SB24_COOKIE ? { cookie: process.env.SB24_COOKIE } : {};

const BAZA = (process.argv[2] || "http://localhost:8080").replace(/\/+$/, "");
const ROWNOLEGLE = 4;

const migawka = fs.readFileSync(path.resolve("dane", "migawka", "wyniki.jsonl"), "utf8")
  .split("\n").filter(Boolean).map((l) => JSON.parse(l));
const pominiete = new Set([
  ...JSON.parse(fs.readFileSync(path.resolve("dane", "eksport", "kategorie.json"), "utf8"))
    .filter((k) => !k.aktywna_z_rodzicami && k.w_sitemapie).map((k) => k.url.sciezka),
  ...JSON.parse(fs.readFileSync(path.resolve("dane", "surowe", "producers.json"), "utf8"))
    .map((p) => "/" + p.translations.pl_PL.seo_url),
]);

const sciezka = (url) => decodeURI(new URL(url).pathname);
function typ(s) {
  if (s === "/") return "strona główna";
  const m = s.match(/^\/pl\/(p|c|i|blog)\//);
  if (m) return { p: "produkt /pl/p/", c: "kategoria /pl/c/", i: "strona /pl/i/", blog: "blog /pl/blog/" }[m[1]];
  if (/^\/[^/]+\/\d+$/.test(s)) return "paginacja kategorii";
  return "adres w korzeniu";
}
const baza = (s) => (/^\/[^/]+\/\d+$/.test(s) && !s.startsWith("/pl/") ? s.replace(/\/\d+$/, "") : s);
const dekoduj = (t) => t.replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(+d)).replace(/&amp;/g, "&")
  .replace(/&quot;/g, '"').replace(/&#039;|&apos;/g, "'").replace(/\s+/g, " ").trim();
const h1 = (html) => [...html.matchAll(/<h1\b[^>]*>([\s\S]*?)<\/h1>/gi)].map((m) => dekoduj(m[1].replace(/<[^>]+>/g, " ")));

const wyniki = [];
const kolejka = [...migawka];
async function pracownik() {
  while (kolejka.length) {
    const r = kolejka.shift();
    const s = sciezka(r.url);
    const w = { sciezka: s, typ: typ(s) };
    if (pominiete.has(baza(s))) w.wynik = "pominięty";
    else {
      try {
        // Surowa ścieżka z sitemapy (z %2C itp.) — tak, jak wysyła ją przeglądarka i Google.
        // Nie decodeURI + encodeURI: %2C zostaje przy decodeURI, a encodeURI robi z niego %252C.
        const res = await fetch(BAZA + new URL(r.url).pathname, { redirect: "manual", headers: NAGLOWKI });
        w.status = res.status;
        w.location = res.headers.get("location");
        if (res.status === 200) {
          const nowe = h1(await res.text());
          const stare = (r.h1 || []).map(dekoduj);
          w.h1_shoper = stare[0] ?? null;
          w.h1_nowy = nowe[0] ?? null;
          // Shoper nie ma H1 na blogu i stronach /pl/i/ — wtedy nowy H1 musi być początkiem <title>
          // (separator bywa „ | ” i „ - ”, wpisy bloga mają przedrostek „Blog - ”).
          const tytul = dekoduj(r.title || "").replace(/^Blog - /, "");
          w.wynik = (stare[0] ? nowe.includes(stare[0]) : nowe.some((n) => n && tytul.startsWith(n))) ? "ok" : "inny H1";
        } else w.wynik = `HTTP ${res.status}`;
      } catch (e) {
        w.wynik = "błąd: " + e.message;
      }
    }
    wyniki.push(w);
  }
}
await Promise.all(Array.from({ length: ROWNOLEGLE }, pracownik));

fs.writeFileSync(path.resolve("dane", "sprawdzenie-adresow.json"), JSON.stringify(wyniki, null, 2));
const tabela = {};
for (const w of wyniki) {
  tabela[w.typ] ??= {};
  tabela[w.typ][w.wynik] = (tabela[w.typ][w.wynik] || 0) + 1;
}
console.log(`Baza: ${BAZA}, adresów: ${wyniki.length}`);
console.table(tabela);
const zle = wyniki.filter((w) => !["ok", "pominięty"].includes(w.wynik));
console.log(`Problemy: ${zle.length}`);
zle.slice(0, 15).forEach((w) => console.log(" ", w.wynik, w.sciezka, w.h1_shoper ? `| shoper: ${w.h1_shoper} | nowy: ${w.h1_nowy}` : "", w.location || ""));
