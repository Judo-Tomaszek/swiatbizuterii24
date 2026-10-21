// Składa pełne rekordy produktów i kategorii z surowych danych API (dane/surowe/) i migawki SEO
// (dane/migawka/), dzieli produkty na 4 grupy do decyzji o imporcie.
// Uruchomienie z korzenia repo:  node skrypty/eksport/buduj-katalog.mjs
//
// Wynik w dane/eksport/:
//   <grupa>/produkty.json   pełne rekordy (opis, SEO, atrybuty, warianty, zdjęcia, adres)
//   <grupa>/produkty.csv    arkusz do przeglądu w Excelu (średnik, UTF-8 z BOM)
//   kategorie.json          drzewo kategorii z opisami i SEO
//   podsumowanie.json
// Grupy: aktywne-na-stanie, aktywne-stan-0, nieaktywne-na-stanie, nieaktywne-stan-0.
// Stan produktu = suma stanów wariantów (gdy ma warianty) albo stan magazynu podstawowego.
// Zdjęcia pobiera osobny skrypt (pobierz-zdjecia.mjs) do <grupa>/zdjecia/<id>/ wg pola `plik`.
import fs from "node:fs";
import path from "node:path";
import zlib from "node:zlib";

const SKLEP = "https://swiatbizuterii24.pl";
const SUROWE = path.resolve("dane", "surowe");
const OUT = path.resolve("dane", "eksport");
const L = (z) => JSON.parse(fs.readFileSync(path.join(SUROWE, `${z}.json`), "utf8"));
const pl = (o) => o?.translations?.pl_PL ?? {};

const products = L("products"), images = L("product-images"), stocks = L("product-stocks");
const options = L("options"), optionValues = L("option-values"), attributes = L("attributes");
const attributeGroups = L("attribute-groups"), categories = L("categories"), tree = L("categories-tree");
const producers = L("producers");

// --- migawka SEO: url -> { title, description, canonical, h1 }
const migawka = new Map();
for (const l of fs.readFileSync(path.resolve("dane", "migawka", "wyniki.jsonl"), "utf8").split("\n")) {
  if (!l) continue;
  const r = JSON.parse(l);
  migawka.set(r.url, r);
}
const seoZMigawki = (url) => {
  const r = migawka.get(url);
  return r ? { title: r.title, description: r.description, canonical: r.canonical, h1: r.h1 } : null;
};

// --- słowniki
const kat = new Map(categories.map((c) => [Number(c.category_id), c]));
const rodzic = new Map();
(function chodz(wezly, ojciec) {
  for (const w of wezly) { rodzic.set(w.id, ojciec); chodz(w.children, w.id); }
})(tree, null);
const sciezkaKat = (id) => {
  const s = [];
  for (let c = id; c != null; c = rodzic.get(c)) s.unshift(pl(kat.get(c)).name ?? `#${c}`);
  return s.join(" > ");
};
const katAktywnaZRodzicami = (id) => {
  for (let c = id; c != null; c = rodzic.get(c)) if (pl(kat.get(c)).active !== "1") return false;
  return true;
};

const atrNazwa = new Map(attributes.map((a) => [a.attribute_id, a.name]));
const atrGrupa = new Map(attributeGroups.map((g) => [g.attribute_group_id, g.name]));
const opcjaNazwa = new Map(options.map((o) => [o.option_id, pl(o).name]));
const wartoscNazwa = new Map(optionValues.map((v) => [v.ovalue_id, pl(v).value]));
const producent = new Map(producers.map((p) => [p.producer_id, p.name]));

const zdjeciaProduktu = new Map();
for (const i of images) {
  if (!zdjeciaProduktu.has(i.product_id)) zdjeciaProduktu.set(i.product_id, []);
  zdjeciaProduktu.get(i.product_id).push(i);
}
const wariantyProduktu = new Map();
for (const s of stocks) {
  if (s.extended !== "1") continue;
  if (!wariantyProduktu.has(s.product_id)) wariantyProduktu.set(s.product_id, []);
  wariantyProduktu.get(s.product_id).push(s);
}

const czyscOpis = (h) => (h || "").replace(/<!--\[mode:[a-z]+\]-->/g, "").trim();
const sciezka = (url) => (url || "").replace(SKLEP, "");

// --- produkty
const GRUPY = ["aktywne-na-stanie", "aktywne-stan-0", "nieaktywne-na-stanie", "nieaktywne-stan-0"];
const wGrupie = Object.fromEntries(GRUPY.map((g) => [g, []]));

for (const p of products) {
  const t = pl(p);
  const aktywny = t.active === "1";
  const warianty = (wariantyProduktu.get(p.product_id) || []).filter((s) => s.active === "1");
  const stan = warianty.length
    ? warianty.reduce((s, w) => s + Number(w.stock), 0)
    : Number(p.stock.stock);
  const grupa = `${aktywny ? "aktywne" : "nieaktywne"}-${stan > 0 ? "na-stanie" : "stan-0"}`;

  const atrybuty = [];
  for (const [gid, wart] of Object.entries(p.attributes || {})) {
    if (!wart || typeof wart !== "object" || Array.isArray(wart)) continue;
    for (const [aid, v] of Object.entries(wart)) {
      if (v === "" || v == null) continue;
      atrybuty.push({ grupa: atrGrupa.get(gid) ?? gid, nazwa: atrNazwa.get(aid) ?? aid, wartosc: v });
    }
  }

  const zdj = (zdjeciaProduktu.get(p.product_id) || [])
    .sort((a, b) => Number(a.order) - Number(b.order))
    .map((i, n) => ({
      gfx_id: i.gfx_id,
      kolejnosc: n + 1,
      glowne: i.main === 1 || i.main === "1",
      ukryte: i.hidden === "1",
      alt: pl(i).name || i.name || t.name,
      opis: pl(i).description || "",
      zrodlo: `${SKLEP}/userdata/gfx/${i.gfx_id}.${i.extension}`,
      plik: `zdjecia/${p.product_id}/${String(n + 1).padStart(2, "0")}-${i.gfx_id}.${i.extension}`,
    }));

  const katGlowna = Number(p.category_id);
  wGrupie[grupa].push({
    shoper_id: Number(p.product_id),
    grupa,
    aktywny,
    stan,
    nazwa: t.name,
    kod: p.code,
    ean: p.ean,
    cena: Number(p.stock.comp_price ?? p.stock.price),
    najnizsza_cena_30d: Number(p.stock.historical_lowest_price),
    waga: Number(p.stock.weight),
    vat_id: p.tax_id,
    producent: p.producer_id ? producent.get(p.producer_id) ?? p.producer_id : null,
    url: { sciezka: sciezka(t.permalink), permalink: t.permalink, seo_url: t.seo_url || null },
    seo: {
      z_api: { title: t.seo_title, description: t.seo_description, keywords: t.seo_keywords },
      ze_strony: seoZMigawki(t.permalink),
    },
    kategoria_glowna: { id: katGlowna, nazwa: pl(kat.get(katGlowna)).name, sciezka: sciezkaKat(katGlowna), aktywna: katAktywnaZRodzicami(katGlowna) },
    kategorie: (p.categories || []).map((c) => ({ id: Number(c), nazwa: pl(kat.get(Number(c))).name, sciezka: sciezkaKat(Number(c)) })),
    opis: czyscOpis(t.description),
    krotki_opis: czyscOpis(t.short_description),
    atrybuty,
    warianty: warianty.map((w) => ({
      stock_id: w.stock_id,
      kod: w.code,
      ean: w.ean,
      // price_type 0 = „jak produkt” — comp_price wariantu bywa wtedy nieaktualne (produkt 2515: wariant
      // 1049, strona 1289); 1 = własna cena wariantu. Innych typów w danych nie ma (2026-10-16).
      cena: w.price_type === "1" ? Number(w.price) : Number(p.stock.comp_price ?? p.stock.price),
      stan: Number(w.stock),
      opcje: Object.entries(w.options || {}).map(([o, v]) => ({ opcja: opcjaNazwa.get(o) ?? o, wartosc: wartoscNazwa.get(v) ?? v })),
    })),
    zdjecia: zdj,
    bestseller: p.bestseller === "1",
    nowosc: p.newproduct === "1",
    powiazane: p.related || [],
    tagi: p.tags || [],
    dodany: p.add_date,
    zmieniony: p.edit_date,
  });
}

// --- kategorie (płaska lista z rodzicem, w kolejności drzewa)
const kategorieOut = [];
(function chodz(wezly, glebokosc) {
  for (const w of wezly) {
    const c = kat.get(w.id), t = pl(c);
    kategorieOut.push({
      shoper_id: w.id,
      rodzic: rodzic.get(w.id),
      glebokosc,
      nazwa: t.name,
      sciezka_kategorii: sciezkaKat(w.id),
      aktywna: t.active === "1",
      aktywna_z_rodzicami: katAktywnaZRodzicami(w.id),
      kolejnosc: Number(c?.order),
      url: { sciezka: sciezka(t.permalink), permalink: t.permalink, seo_url: t.seo_url || null },
      seo: {
        z_api: { title: t.seo_title, description: t.seo_description, keywords: t.seo_keywords, naglowek: t.custom_heading },
        ze_strony: seoZMigawki(t.permalink),
      },
      opis_gora: czyscOpis(t.description),
      opis_dol: czyscOpis(t.description_bottom),
      w_sitemapie: migawka.has(t.permalink),
    });
    chodz(w.children, glebokosc + 1);
  }
})(tree, 0);

// --- zapis
const csvPole = (v) => {
  const s = v == null ? "" : String(v);
  return /[;"\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
};
const KOLUMNY = [
  ["ID Shoper", (p) => p.shoper_id], ["Nazwa", (p) => p.nazwa], ["Kod", (p) => p.kod],
  ["Cena", (p) => String(p.cena).replace(".", ",")], ["Stan", (p) => p.stan],
  ["Kategoria", (p) => p.kategoria_glowna.sciezka], ["Kategoria aktywna", (p) => (p.kategoria_glowna.aktywna ? "tak" : "nie")],
  ["Wszystkie kategorie", (p) => p.kategorie.map((k) => k.sciezka).join(" | ")],
  ["Adres", (p) => p.url.sciezka], ["W sitemapie", (p) => (p.seo.ze_strony ? "tak" : "nie")],
  ["Zdjęć", (p) => p.zdjecia.length], ["Wariantów", (p) => p.warianty.length],
  ["Opis (znaki)", (p) => p.opis.replace(/<[^>]+>/g, "").trim().length],
  ["Dodany", (p) => p.dodany], ["Zmieniony", (p) => p.zmieniony],
];

fs.mkdirSync(OUT, { recursive: true });
const podsumowanie = { data: new Date().toISOString(), produkty: {}, kategorie: kategorieOut.length };
for (const g of GRUPY) {
  const dir = path.join(OUT, g);
  fs.mkdirSync(dir, { recursive: true });
  const lista = wGrupie[g].sort((a, b) => a.kategoria_glowna.sciezka.localeCompare(b.kategoria_glowna.sciezka, "pl") || a.nazwa.localeCompare(b.nazwa, "pl"));
  fs.writeFileSync(path.join(dir, "produkty.json"), JSON.stringify(lista, null, 2));
  const csv = [KOLUMNY.map((k) => k[0]).join(";"), ...lista.map((p) => KOLUMNY.map((k) => csvPole(k[1](p))).join(";"))];
  fs.writeFileSync(path.join(dir, "produkty.csv"), "﻿" + csv.join("\r\n"));
  podsumowanie.produkty[g] = {
    produktow: lista.length,
    zdjec: lista.reduce((s, p) => s + p.zdjecia.length, 0),
    z_wariantami: lista.filter((p) => p.warianty.length).length,
    w_sitemapie: lista.filter((p) => p.seo.ze_strony).length,
  };
}
fs.writeFileSync(path.join(OUT, "kategorie.json"), JSON.stringify(kategorieOut, null, 2));
fs.writeFileSync(path.join(OUT, "podsumowanie.json"), JSON.stringify(podsumowanie, null, 2));
console.table(podsumowanie.produkty);
console.log(`kategorie: ${kategorieOut.length}`);
