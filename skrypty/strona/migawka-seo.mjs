// Migawka SEO sklepu: dla każdego adresu z sitemapy (+ strona główna) zapisuje status, przekierowanie,
// <title>, meta description/robots, canonical, H1, og:title/og:description.
// PO CO: API Shopera nie zwraca tytułów i opisów, które widzi Google (skleja je z szablonu) —
// źródłem prawdy jest wyrenderowana strona. Patrz docs/sondaz-api-shoper-2026-10-16.md.
//
// Uruchomienie z korzenia repo:  node skrypty/strona/migawka-seo.mjs
// Wynik:  dane/migawka/wyniki.jsonl  (jeden wiersz = jeden adres; wznawialne — gotowe adresy pomija)
//         dane/migawka/html/<nr>.html.gz  (pełny HTML do późniejszego wyciągania treści)
// Tempo: 1 żądanie/s (robots.txt: Crawl-delay: 1).
import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";

const SKLEP = "https://swiatbizuterii24.pl";
const SITEMAP = `${SKLEP}/console/integration/execute/name/GoogleSitemap`;
const OUT = path.resolve("dane", "migawka");
const HTML_DIR = path.join(OUT, "html");
const WYNIKI = path.join(OUT, "wyniki.jsonl");
const ODSTEP_MS = 1000;
const UA = "swiatbizuterii24-migracja/1.0 (migawka SEO przed migracja)";

fs.mkdirSync(HTML_DIR, { recursive: true });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const locs = (xml) => [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].trim());

async function adresyZSitemapy() {
  const indeks = locs(await (await fetch(SITEMAP, { headers: { "User-Agent": UA } })).text());
  const wszystkie = [];
  for (const podmapa of indeks) {
    await sleep(ODSTEP_MS);
    wszystkie.push(...locs(await (await fetch(podmapa, { headers: { "User-Agent": UA } })).text()));
  }
  return wszystkie;
}

const ENCJE = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: " " };
const dekoduj = (s) =>
  s == null ? null : s
    .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCodePoint(parseInt(h, 16)))
    .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
    .replace(/&([a-z]+);/gi, (m, n) => ENCJE[n.toLowerCase()] ?? m);

const atrybuty = (tag) => {
  const a = {};
  for (const m of tag.matchAll(/([a-zA-Z:-]+)\s*=\s*("([^"]*)"|'([^']*)')/g)) a[m[1].toLowerCase()] = m[3] ?? m[4];
  return a;
};

function analizuj(html) {
  const metas = [...html.matchAll(/<meta\b[^>]*>/gi)].map((m) => atrybuty(m[0]));
  const meta = (klucz) => {
    const m = metas.find((a) => (a.name || a.property || "").toLowerCase() === klucz);
    return m ? dekoduj(m.content ?? "") : null;
  };
  const linki = [...html.matchAll(/<link\b[^>]*>/gi)].map((m) => atrybuty(m[0]));
  const canonical = linki.find((a) => (a.rel || "").toLowerCase() === "canonical")?.href ?? null;
  const tekst = (s) => dekoduj(s.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim());
  const title = html.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
  return {
    title: title ? dekoduj(title[1].trim()) : null,
    description: meta("description"),
    robots: meta("robots"),
    canonical,
    h1: [...html.matchAll(/<h1\b[^>]*>([\s\S]*?)<\/h1>/gi)].map((m) => tekst(m[1])),
    og_title: meta("og:title"),
    og_description: meta("og:description"),
  };
}

const gotowe = new Set(
  fs.existsSync(WYNIKI)
    ? fs.readFileSync(WYNIKI, "utf8").split("\n").filter(Boolean).map((l) => JSON.parse(l).url)
    : []
);

const adresy = [...new Set([`${SKLEP}/`, ...(await adresyZSitemapy())])];
let nr = gotowe.size;
console.log(`Adresów: ${adresy.length}, już zrobionych: ${gotowe.size}`);

for (const url of adresy) {
  if (gotowe.has(url)) continue;
  await sleep(ODSTEP_MS);
  const wiersz = { nr: ++nr, url, data: new Date().toISOString() };
  try {
    const res = await fetch(url, { redirect: "manual", headers: { "User-Agent": UA } });
    wiersz.status = res.status;
    wiersz.location = res.headers.get("location");
    if (res.status === 200) {
      const html = await res.text();
      Object.assign(wiersz, analizuj(html));
      wiersz.html = `html/${wiersz.nr}.html.gz`;
      fs.writeFileSync(path.join(OUT, wiersz.html), zlib.gzipSync(html));
    }
  } catch (e) {
    wiersz.blad = String(e.message || e);
  }
  fs.appendFileSync(WYNIKI, JSON.stringify(wiersz) + "\n");
  if (wiersz.nr % 100 === 0) console.log(`${wiersz.nr}/${adresy.length}`);
}
console.log("Koniec.");
