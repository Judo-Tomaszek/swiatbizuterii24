// Test „tekst 1:1”: czy cały tekst z Shopera (strony, blog, strona główna, opisy kategorii) jest
// na nowej stronie słowo w słowo. Porównuje sam tekst (bez znaczników, białe znaki zwinięte).
// Uruchomienie z korzenia repo:  node skrypty/strona/sprawdz-teksty.mjs [baza-url]
import fs from "node:fs";
import path from "node:path";
// SB24_COOKIE: ciasteczko zalogowanego administratora — gdy sklep jest w trybie „wkrótce otwarcie”
// (WooCommerce pokazuje wtedy niezalogowanym zaślepkę zamiast kategorii i produktów).
const NAGLOWKI = process.env.SB24_COOKIE ? { cookie: process.env.SB24_COOKIE } : {};

const BAZA = (process.argv[2] || "http://localhost:8080").replace(/\/+$/, "");
const T = (p) => JSON.parse(fs.readFileSync(path.resolve("dane", "eksport", ...p.split("/")), "utf8"));
const ENC = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: " " };
const tekst = (h) => (h || "")
  .replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>|<!--[\s\S]*?-->/g, " ")
  .replace(/<[^>]+>/g, " ")
  .replace(/&#x([0-9a-f]+);/gi, (_, x) => String.fromCodePoint(parseInt(x, 16)))
  .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(+d))
  .replace(/&([a-z]+);/gi, (m, n) => ENC[n.toLowerCase()] ?? m)
  .replace(/[\s\u00a0]+/g, " ").trim();

const przypadki = [];
const g = T("tresci/glowna.json");
g.bloki.forEach((b) => przypadki.push({ nazwa: `strona główna ${b.id}`, sciezka: "/", tresc: b.html }));
// strony z treścią poprawioną celowo (skrypty/import/tresci-poprawione/) — tam 1:1 nie obowiązuje
const POPRAWIONE = new Set(fs.readdirSync(new URL("../import/tresci-poprawione/", import.meta.url)).map((f) => f.replace(/\.html$/, "")));
const slug = (sciezka) => sciezka.replace(/\/\d+$/, "").split("/").pop().toLowerCase();
for (const s of T("tresci/strony.json")) if (!POPRAWIONE.has(slug(s.sciezka))) przypadki.push({ nazwa: `strona: ${s.tytul}`, sciezka: s.sciezka, tresc: s.tresc });
for (const b of T("tresci/blog.json")) przypadki.push({ nazwa: `blog: ${b.tytul}`, sciezka: b.sciezka, tresc: b.tresc });
for (const k of T("kategorie.json").filter((k) => k.aktywna_z_rodzicami)) {
  przypadki.push({ nazwa: `kategoria (góra): ${k.nazwa}`, sciezka: k.url.sciezka, tresc: k.opis_gora });
  przypadki.push({ nazwa: `kategoria (dół): ${k.nazwa}`, sciezka: k.url.sciezka, tresc: k.opis_dol });
}

const wyniki = { ok: 0, pusty: 0, brak: [] };
for (const p of przypadki) {
  const oczek = tekst(p.tresc);
  if (!oczek) { wyniki.pusty++; continue; }
  const html = await (await fetch(BAZA + encodeURI(p.sciezka).replace(/%25/g, "%"), { headers: NAGLOWKI })).text();
  const jest = tekst(html);
  if (jest.includes(oczek)) wyniki.ok++;
  else {
    // pierwsze zdanie, którego brakuje — żeby było widać, CO się nie zgadza
    const zdania = oczek.split(/(?<=[.!?])\s+/);
    const brak = zdania.find((z) => z.length > 20 && !jest.includes(z));
    wyniki.brak.push({ nazwa: p.nazwa, sciezka: p.sciezka, brakuje: brak ? brak.slice(0, 120) : "(drobne różnice)" });
  }
}
console.log(`Teksty zgodne 1:1: ${wyniki.ok}, puste w Shoperze: ${wyniki.pusty}, niezgodne: ${wyniki.brak.length}`);
wyniki.brak.forEach((b) => console.log(" ", b.nazwa, "|", b.sciezka, "\n     brakuje:", b.brakuje));
