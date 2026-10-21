// Składa treści do importu: strony informacyjne, blog, stronę główną i obrazki wklejone w tekstach.
// Uruchomienie z korzenia repo:  node skrypty/eksport/buduj-tresci.mjs
// Wejście: dane/surowe/{aboutpages,news,news-tags,products}.json, dane/migawka/ (adresy, title/meta,
// HTML strony głównej), dane/eksport/kategorie.json (obrazki w opisach kategorii).
// Wynik: dane/eksport/tresci/{strony,blog,glowna,obrazy}.json + tresci/obrazy/<ścieżka z Shopera>.
//
// Skąd co:
// - adres: własny (`seo_url`) albo dopasowany w migawce po ID (`/pl/i/<…>/<id>`, `/pl/blog/<…>/<id>`);
// - title/meta: z migawki (to widzi Google), treść: z API;
// - strona główna: z HTML-a migawki — tekst siedzi w modułach szablonu (`box_custom*`), nie w API;
// - obrazki z `/userdata/…` pobierane lokalnie — po wyłączeniu Shopera ich adresy przestaną działać.
import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";

const SKLEP = "https://swiatbizuterii24.pl";
const SUROWE = path.resolve("dane", "surowe");
const OUT = path.resolve("dane", "eksport", "tresci");
const L = (z) => JSON.parse(fs.readFileSync(path.join(SUROWE, `${z}.json`), "utf8"));
fs.mkdirSync(path.join(OUT, "obrazy"), { recursive: true });

const migawka = new Map();
for (const l of fs.readFileSync(path.resolve("dane", "migawka", "wyniki.jsonl"), "utf8").split("\n")) {
  if (!l) continue;
  const r = JSON.parse(l);
  migawka.set(decodeURI(new URL(r.url).pathname), r);
}
const html = (sciezka) => zlib.gunzipSync(fs.readFileSync(path.resolve("dane", "migawka", migawka.get(sciezka).html))).toString();
const seo = (sciezka) => {
  const r = migawka.get(sciezka);
  return r ? { title: r.title, description: r.description, h1: r.h1?.[0] ?? null } : null;
};
const sciezkaPoId = (prefiks, id, seoUrl) => {
  if (seoUrl) return `/${seoUrl}`;
  const re = new RegExp(`^/pl/${prefiks}/[^/]+/${id}$`);
  return [...migawka.keys()].find((s) => re.test(s)) ?? null;
};
const czysc = (h) => (h || "").replace(/<!--\[mode:[a-z]+\]-->/g, "").trim();

// --- strony informacyjne
const strony = L("aboutpages").map((a) => {
  const sciezka = sciezkaPoId("i", a.page_id, a.seo_url);
  const s = {
    shoper_id: Number(a.page_id),
    tytul: a.name,
    sciezka,
    aktywna: a.active === "1",
    tresc: czysc(a.content),
    seo: sciezka ? seo(sciezka) : null,
    uwagi: [],
  };
  if (!sciezka) s.uwagi.push("brak adresu w migawce");
  if (/rr_xss_cert/.test(s.tresc) || !s.tresc.replace(/<[^>]+>/g, "").trim()) {
    s.uwagi.push("treść ładowana zewnętrznym widżetem (rzetelny regulamin) — w API i HTML-u brak tekstu");
  }
  return s;
});

// --- blog
const tagi = new Map(L("news-tags").map((t) => [String(t.tag_id), t.name]));
const blog = L("news").map((n) => {
  const sciezka = sciezkaPoId("blog", n.news_id, n.seo_url);
  return {
    shoper_id: Number(n.news_id),
    tytul: n.name,
    sciezka,
    aktywny: n.active === "1",
    tresc: czysc(n.content),
    zajawka: czysc(n.short_content),
    data: n.date && !n.date.startsWith("0000") ? n.date : null,
    tagi: (n.tags || []).map((t) => tagi.get(String(t.tag_id ?? t)) ?? String(t.name ?? t)),
    zdjecie: n.image_file ? { zrodlo: `/userdata/public/news/images/${n.image_file}`, alt: n.image_name || n.name } : null,
    seo: sciezka ? seo(sciezka) : null,
  };
});

// --- strona główna
const glownaHtml = html("/");
const bloki = [...glownaHtml.matchAll(/<div class="box resetcss box_custom" id="(box_custom\d+)">([\s\S]*?)<\/div>\s*(?=<div class="box|<\/div>\s*<\/div>\s*<\/div>)/g)]
  .map((m) => ({ id: m[1], html: m[2].trim() }));
const wyroznione = L("products")
  .filter((p) => p.translations.pl_PL.active === "1" && p.translations.pl_PL.main_page === "1")
  .map((p) => Number(p.product_id));
// Kolejność jak na stronie (main_page_order bywa równy dla wszystkich) — z migawki strony głównej.
const naStronie = [...new Set([...html("/").matchAll(/data-product-id="(\d+)"/g)].map((m) => Number(m[1])))];
wyroznione.sort((a, b) => (naStronie.indexOf(a) + 1 || 1e9) - (naStronie.indexOf(b) + 1 || 1e9));
const glowna = { sciezka: "/", seo: seo("/"), h1: seo("/").h1, wyroznione_produkty: wyroznione, bloki };

// --- obrazki z treści (src i href do plików graficznych i PDF-ów z serwera Shopera)
const kategorie = JSON.parse(fs.readFileSync(path.resolve("dane", "eksport", "kategorie.json"), "utf8"));
const teksty = [
  ...strony.map((s) => s.tresc), ...blog.flatMap((b) => [b.tresc, b.zajawka]),
  ...bloki.map((b) => b.html), ...kategorie.flatMap((k) => [k.opis_gora, k.opis_dol]),
];
const obrazy = new Map();
const dodaj = (zrodlo) => {
  const url = zrodlo.startsWith("http") ? zrodlo : SKLEP + (zrodlo.startsWith("/") ? "" : "/") + zrodlo;
  const u = new URL(url);
  const plik = `obrazy/${u.host === "swiatbizuterii24.pl" ? "" : u.host + "/"}${decodeURIComponent(u.pathname).replace(/^\/+/, "").replace(/\/{2,}/g, "/")}`;
  obrazy.set(zrodlo, { zrodlo, url, plik });
};
for (const t of teksty) {
  for (const m of (t || "").matchAll(/(?:src|href)="([^"]+\.(?:jpe?g|png|gif|webp|pdf))"/gi)) {
    if (/^(\/|https?:\/\/(www\.)?swiatbizuterii24\.pl|https?:\/\/[^/]*dcsaascdn)/.test(m[1])) dodaj(m[1]);
  }
}
for (const b of blog) if (b.zdjecie) dodaj(b.zdjecie.zrodlo);

let pobrane = 0;
const bledy = [];
for (const o of obrazy.values()) {
  const cel = path.join(OUT, o.plik);
  if (fs.existsSync(cel) && fs.statSync(cel).size > 0) continue;
  try {
    const res = await fetch(o.url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    fs.mkdirSync(path.dirname(cel), { recursive: true });
    fs.writeFileSync(cel, Buffer.from(await res.arrayBuffer()));
    pobrane++;
  } catch (e) {
    bledy.push({ ...o, blad: e.message });
  }
}

fs.writeFileSync(path.join(OUT, "strony.json"), JSON.stringify(strony, null, 2));
fs.writeFileSync(path.join(OUT, "blog.json"), JSON.stringify(blog, null, 2));
fs.writeFileSync(path.join(OUT, "glowna.json"), JSON.stringify(glowna, null, 2));
fs.writeFileSync(path.join(OUT, "obrazy.json"), JSON.stringify([...obrazy.values()].filter((o) => !bledy.some((b) => b.zrodlo === o.zrodlo)), null, 2));

console.log(`strony: ${strony.length} (bez adresu: ${strony.filter((s) => !s.sciezka).length}, z uwagami: ${strony.filter((s) => s.uwagi.length).map((s) => s.tytul).join(", ") || "—"})`);
console.log(`blog: ${blog.length} (bez adresu: ${blog.filter((b) => !b.sciezka).length}, ze zdjęciem: ${blog.filter((b) => b.zdjecie).length}, z tagami: ${blog.filter((b) => b.tagi.length).length})`);
console.log(`strona główna: H1 „${glowna.h1}”, bloków tekstu: ${bloki.length} (${bloki.map((b) => b.id).join(", ")}), wyróżnionych produktów: ${wyroznione.length}`);
console.log(`obrazki: ${obrazy.size}, pobrane teraz: ${pobrane}, błędy: ${bledy.length}`);
bledy.forEach((b) => console.log("  ", b.blad, b.url));
