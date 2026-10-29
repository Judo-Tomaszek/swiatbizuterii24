// Poprawa małych zdjęć głównych przez ChatGPT (Codex CLI, generowanie obrazu), jedno po drugim.
// Lista: dane/eksport/male-zdjecia.csv (najpierw produkty na stanie, potem najmniejsze zdjęcia).
// Wynik: dane/poprawa-zdjec/chatgpt/<shoperId>-<gfxId>.png + log.jsonl. Wznawialne — pomija gotowe.
// Po limicie ChatGPT czeka do podanej godziny i jedzie dalej.
// NIE podmienia zdjęć w WordPressie: każde wymaga akceptacji (ChatGPT potrafi zmienić detale).
// Uruchom: node skrypty/zdjecia/popraw-chatgpt.mjs [--reszta] [limit]
import { execFileSync } from 'node:child_process';
import { appendFileSync, copyFileSync, existsSync, mkdirSync, readFileSync, renameSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';

const KORZEN = resolve(import.meta.dirname, '../..');
const EKSPORT = join(KORZEN, 'dane/eksport');
const WYNIK = join(KORZEN, 'dane/poprawa-zdjec/chatgpt');
const ROBOCZY = join(KORZEN, 'tmp/poprawa-zdjec');
const LOG = join(KORZEN, 'dane/poprawa-zdjec/log.jsonl');
const PROMPT = readFileSync(join(import.meta.dirname, 'prompt-chatgpt.txt'), 'utf8');
const CODEX = join(process.env.ProgramFiles ?? 'C:/Program Files', 'nodejs/node_modules/@openai/codex/bin/codex.js');
const RESZTA = process.argv.includes('--reszta'); // pozostałe zdjęcia aktywnych produktów (reszta-zdjec.csv, buduje lista-reszty.py)
const limit = Number(process.argv.slice(2).find((a) => /^\d+$/.test(a))) || Infinity;
mkdirSync(WYNIK, { recursive: true });

const csv = (plik) => readFileSync(join(EKSPORT, plik), 'utf8').replace(/^﻿/, '').trim().split(/\r?\n/).slice(1).map((w) => w.split(';'));
let lista;
if (RESZTA) {
  // shoper_id;gfx_id;plik;stan;szer;wys — już posortowane (na stanie, od najmniejszych)
  lista = csv('reszta-zdjec.csv').map((k) => ({ nazwa: `${k[0]}-${k[1]}`, plik: join(EKSPORT, k[2]), px: Math.max(k[4], k[5]) }));
} else {
  // produkt → główne zdjęcie (oba katalogi aktywnych)
  const glowne = new Map();
  for (const grupa of ['aktywne-na-stanie', 'aktywne-stan-0']) {
    for (const p of JSON.parse(readFileSync(join(EKSPORT, grupa, 'produkty.json'), 'utf8'))) {
      const z = p.zdjecia.find((z) => z.glowne) ?? p.zdjecia[0];
      if (z) glowne.set(String(p.shoper_id), { gfx: z.gfx_id, plik: join(EKSPORT, grupa, z.plik) });
    }
  }
  lista = csv('male-zdjecia.csv')
    .map((k) => ({ id: k[0], stan: Number(k[3]), px: Number(k[5]) }))
    .sort((a, b) => (b.stan > 0) - (a.stan > 0) || a.px - b.px)
    .filter(({ id }) => glowne.has(id))
    .map(({ id, px }) => ({ nazwa: `${id}-${glowne.get(id).gfx}`, plik: glowne.get(id).plik, px }));
}

// Limit ChatGPT: „try again at 2:29 AM” → czekamy do tej godziny (+1 min) i ponawiamy to samo zdjęcie.
function czekajNaLimit(tekst) {
  const m = tekst.match(/try again at (\d{1,2}):(\d{2})\s*([AP]M)/i);
  const sek = tekst.match(/resets_in_seconds\D{0,3}(\d+)/);
  let ms = sek ? (Number(sek[1]) + 60) * 1000 : 30 * 60_000;
  if (m && !sek) {
    const t = new Date();
    t.setHours((Number(m[1]) % 12) + (/pm/i.test(m[3]) ? 12 : 0), Number(m[2]) + 1, 0, 0);
    if (t <= new Date()) t.setDate(t.getDate() + 1);
    ms = t - new Date();
  }
  console.log(`${new Date().toLocaleTimeString('pl')} limit ChatGPT — czekam do ${new Date(Date.now() + ms).toLocaleTimeString('pl')}`);
  // po minucie i według zegara ściennego — licznik Atomics.wait stoi, gdy komputer jest uśpiony
  const koniec = Date.now() + ms;
  while (Date.now() < koniec) Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, Math.min(60_000, koniec - Date.now()));
}

// katalog roboczy bywa chwilowo zablokowany (antywirus, indeksowanie) — wtedy ponawiamy, a w ostateczności zostawiamy go
function usunKatalog(kat) {
  try { rmSync(kat, { recursive: true, force: true, maxRetries: 10, retryDelay: 500 }); } catch (e) { console.log(`nie usunięto ${kat}: ${e.code}`); }
}

let zrobione = 0, kolejneBledy = 0, serie = 0;
for (let i = 0; i < lista.length && zrobione < limit; i++) {
  const { nazwa, plik, px } = lista[i];
  const cel = join(WYNIK, `${nazwa}.png`);
  if (existsSync(cel)) continue;

  const kat = join(ROBOCZY, nazwa);
  usunKatalog(kat);
  mkdirSync(kat, { recursive: true });
  const wej = join(kat, 'oryginal' + plik.slice(plik.lastIndexOf('.')));
  copyFileSync(plik, wej);
  const prompt = PROMPT.replace('ROZMIAR', `${px}x${px}`).replace('NAZWA', nazwa);
  const start = Date.now();
  const wyj = join(kat, `${nazwa}.png`);
  let blad = null, wyjscie = '';
  try {
    wyjscie = execFileSync(process.execPath, [CODEX, 'exec', '-m', 'gpt-6-sol', '--skip-git-repo-check', '-s', 'workspace-write',
      '-C', kat, prompt, '-i', wej], { stdio: ['ignore', 'pipe', 'pipe'], timeout: 6 * 60_000, windowsHide: true, encoding: 'utf8' });
  } catch (e) {
    blad = String(e.stderr ?? e.message).slice(-500);
  }
  // obraz bywa zapisany, zanim Codex zgłosi błąd (np. limit w trakcie odpowiedzi) — wtedy go bierzemy
  // Codex przy limicie generowania obrazów (429) bywa, że kończy się kodem 0 — wtedy limit jest tylko w odpowiedzi.
  if (existsSync(wyj)) { renameSync(wyj, cel); blad = null; } else blad ??= /usage.limit|limit[a-zęó ]*(użycia|wyczerpany|przekroczenia)/i.test(wyjscie) ? wyjscie.slice(-500) : 'brak pliku wynikowego: ' + wyjscie.trim().slice(-300).replace(/\s+/g, ' ');
  const s = Math.round((Date.now() - start) / 1000);
  appendFileSync(LOG, JSON.stringify({ nazwa, px, ok: !blad, s, blad, czas: new Date().toISOString() }) + '\n');
  console.log(`${new Date().toLocaleTimeString('pl')} ${nazwa} (${px}px) ${blad ? 'BŁĄD ' + blad.trim().split('\n').pop() : 'ok'} ${s}s`);
  if (!blad) { kolejneBledy = 0; serie = 0; zrobione++; usunKatalog(kat); continue; }
  if (/usage.limit|limit[a-zęó ]*(użycia|wyczerpany|przekroczenia)/i.test(blad)) { czekajNaLimit(blad); kolejneBledy = 0; i--; continue; }
  // seria błędów bez komunikatu o limicie zwykle też jest limitem obrazków — czekamy 30 min, po 4 seriach kończymy
  if (++kolejneBledy >= 3) {
    if (++serie > 4) { console.log('4 serie błędów — przerywam'); process.exit(1); }
    console.log('3 błędy z rzędu — czekam 30 min'); czekajNaLimit(''); kolejneBledy = 0;
  }
}
console.log(`koniec: ${zrobione} nowych`);
