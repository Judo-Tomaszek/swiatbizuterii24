// Poprawa małych zdjęć głównych przez Real-ESRGAN x4 (lokalnie, karta graficzna) — wierne powiększenie.
// Ta sama lista co popraw-chatgpt.mjs (dane/eksport/male-zdjecia.csv), te same nazwy plików.
// Wynik: dane/poprawa-zdjec/esrgan/<shoperId>-<gfxId>.jpg. Wznawialne — pomija gotowe.
// Narzędzie: tmp/narzedzia/realesrgan (realesrgan-ncnn-vulkan v0.2.5.0, github.com/xinntao/Real-ESRGAN).
// Uruchom: node skrypty/zdjecia/popraw-esrgan.mjs [--reszta]
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, renameSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';

const KORZEN = resolve(import.meta.dirname, '../..');
const EKSPORT = join(KORZEN, 'dane/eksport');
const WYNIK = join(KORZEN, 'dane/poprawa-zdjec/esrgan');
const WEJ = join(KORZEN, 'tmp/poprawa-esrgan/wej');
const WYJ = join(KORZEN, 'tmp/poprawa-esrgan/wyj');
const ESRGAN = join(KORZEN, 'tmp/narzedzia/realesrgan/realesrgan-ncnn-vulkan.exe');
mkdirSync(WYNIK, { recursive: true });

const RESZTA = process.argv.includes('--reszta'); // pozostałe zdjęcia aktywnych produktów (reszta-zdjec.csv, buduje lista-reszty.py)
const csv = (plik) => readFileSync(join(EKSPORT, plik), 'utf8').replace(/^﻿/, '').trim().split(/\r?\n/).slice(1).map((w) => w.split(';'));
let wszystkie;
if (RESZTA) {
  wszystkie = csv('reszta-zdjec.csv').map((k) => ({ nazwa: `${k[0]}-${k[1]}`, plik: join(EKSPORT, k[2]) }));
} else {
  const glowne = new Map();
  for (const grupa of ['aktywne-na-stanie', 'aktywne-stan-0']) {
    for (const p of JSON.parse(readFileSync(join(EKSPORT, grupa, 'produkty.json'), 'utf8'))) {
      const z = p.zdjecia.find((z) => z.glowne) ?? p.zdjecia[0];
      if (z) glowne.set(String(p.shoper_id), { gfx: z.gfx_id, plik: join(EKSPORT, grupa, z.plik) });
    }
  }
  wszystkie = csv('male-zdjecia.csv').map((k) => k[0]).filter((id) => glowne.has(id))
    .map((id) => ({ nazwa: `${id}-${glowne.get(id).gfx}`, plik: glowne.get(id).plik }));
}

// paczki po 50 — postęp widać, a przerwanie traci najwyżej jedną paczkę
const doZrobienia = wszystkie.filter(({ nazwa }) => !existsSync(join(WYNIK, `${nazwa}.jpg`)));
console.log(`do zrobienia: ${doZrobienia.length} z ${wszystkie.length}`);
for (let i = 0; i < doZrobienia.length; i += 50) {
  for (const k of [WEJ, WYJ]) { rmSync(k, { recursive: true, force: true }); mkdirSync(k, { recursive: true }); }
  for (const { nazwa, plik } of doZrobienia.slice(i, i + 50)) copyFileSync(plik, join(WEJ, nazwa + plik.slice(plik.lastIndexOf('.'))));
  execFileSync(ESRGAN, ['-i', WEJ, '-o', WYJ, '-n', 'realesrgan-x4plus', '-s', '4', '-f', 'jpg'],
    { cwd: join(ESRGAN, '..'), stdio: 'ignore', windowsHide: true });
  for (const f of readdirSync(WYJ)) renameSync(join(WYJ, f), join(WYNIK, f.replace(/\.[^.]+$/, '.jpg')));
  console.log(`${new Date().toLocaleTimeString('pl')} ${Math.min(i + 50, doZrobienia.length)} / ${doZrobienia.length}`);
}
console.log(`koniec: ${readdirSync(WYNIK).length} plików w ${WYNIK}`);
