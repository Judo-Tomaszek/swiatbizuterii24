// Generator indeksu dziennika: docs/DZIENNIK.md -> docs/SPIS-DZIENNIKA.md
// Uruchamianie z KORZENIA repo:  node docs/_narzedzia/gen-spis.mjs
// Nie edytuj SPIS-DZIENNIKA.md ręcznie — ten skrypt go nadpisuje.
// Wzorzec z club-manager-api; .mjs, bo projekt nie ma package.json z "type": "module".
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "..", "..");
const SRC = path.join(ROOT, "docs", "DZIENNIK.md");
const OUT = path.join(ROOT, "docs", "SPIS-DZIENNIKA.md");

const src = fs.readFileSync(SRC, "utf8");
const entries = [];
for (const line of src.split(/\r?\n/)) {
  const m = line.match(/^##\s+(§(\d+[a-z]?)\s+—\s+(\d{4}-\d{2}-\d{2})\s+—\s+(.+))$/);
  if (m) entries.push({ full: m[1], num: m[2], date: m[3], title: m[4] });
}

if (entries.length === 0) {
  console.error("Brak wpisów `## §N — RRRR-MM-DD — Tytuł` w docs/DZIENNIK.md — nic nie generuję.");
  process.exit(1);
}

// grupowanie po dacie
const byDate = new Map();
for (const e of entries) {
  if (!byDate.has(e.date)) byDate.set(e.date, []);
  byDate.get(e.date).push(e);
}
const dates = [...byDate.keys()].sort();

const PL_MIES = ["stycznia", "lutego", "marca", "kwietnia", "maja", "czerwca",
                 "lipca", "sierpnia", "września", "października", "listopada", "grudnia"];
function dataPL(iso) {
  const [y, mo, d] = iso.split("-").map(Number);
  return `${d} ${PL_MIES[mo - 1]} ${y}`;
}

let out = `---
aliases: [Spis dziennika, Indeks dziennika]
tags: [dziennik, indeks, moc]
---

# 🗂️ Spis dziennika (§1–§${entries[entries.length - 1].num})

Klikalny indeks wszystkich wpisów [[DZIENNIK]] — kroniki prac nad **swiatbizuterii24**.
Każdy link prowadzi do konkretnej sekcji dziennika. Wpisów: **${entries.length}**, dni pracy: **${dates.length}**.

> [!tip] Nawigacja
> • Pulpit dokumentacji: [[HOME]] • Zasady dopisywania: [[KONWENCJA-DOKUMENTACJI]]
> • Mapy tematyczne: [[MOC-Migracja]] · [[MOC-SEO-i-URL]] · [[MOC-WordPress]] · [[MOC-Wdrozenie]]

`;

for (const d of dates) {
  out += `\n## ${dataPL(d)}\n\n`;
  for (const e of byDate.get(d)) {
    const short = e.title.length > 90 ? e.title.slice(0, 88) + "…" : e.title;
    out += `- [[DZIENNIK#${e.full}|${e.num} — ${short}]]\n`;
  }
}

out += `\n---\n*Ten spis jest generowany z nagłówków \`## §…\` w [[DZIENNIK]]. Po dodaniu nowego wpisu odśwież go skryptem \`docs/_narzedzia/gen-spis.mjs\` (patrz [[KONWENCJA-DOKUMENTACJI]]).*\n`;

fs.writeFileSync(OUT, out, "utf8");
console.log(`OK — ${entries.length} wpisów, ${dates.length} dni → docs/SPIS-DZIENNIKA.md`);
