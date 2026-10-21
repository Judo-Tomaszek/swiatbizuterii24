// Banery na górę stron kategorii — generowane przez ChatGPT (Codex CLI), dekoracyjne (nie zdjęcia produktów).
// Styl jak zdjęcie z góry strony głównej: ciemne, ciepłe tło (śliwka/espresso), złote światło, bez napisów.
// Wynik: dane/eksport/banery-kategorii/<slug>.png (import: importuj-tresci.php banery). Wznawialne.
// Z --strony: banery stron informacyjnych i bloga → dane/eksport/banery-stron/<slug strony>.png
// („_strony” = wspólny baner pozostałych stron informacyjnych).
// Uruchom: node skrypty/zdjecia/banery-kategorii.mjs [--strony] [slug…]
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, renameSync, rmSync } from 'node:fs';
import { join, resolve } from 'node:path';

const KORZEN = resolve(import.meta.dirname, '../..');
const STRONY = process.argv.includes('--strony');
const WYNIK = join(KORZEN, STRONY ? 'dane/eksport/banery-stron' : 'dane/eksport/banery-kategorii');
const ROBOCZY = join(KORZEN, STRONY ? 'tmp/banery-stron' : 'tmp/banery-kategorii');
const CODEX = join(process.env.ProgramFiles ?? 'C:/Program Files', 'nodejs/node_modules/@openai/codex/bin/codex.js');
mkdirSync(WYNIK, { recursive: true });

// slug kategorii (jak w WooCommerce) → co ma być na zdjęciu
const KATEGORIE = {
  'pierscionki-zareczynowe': 'a few elegant gold engagement rings with a single sparkling diamond solitaire, one ring standing upright',
  'zloto': 'an arrangement of assorted fine gold jewelry: a ring, delicate earrings, a fine chain and a bracelet',
  'zlote-kolczyki': 'several pairs of fine gold earrings (studs, small hoops and drop earrings), some with small gemstones',
  'zlote-pierscionki': 'several fine gold rings, some with colored gemstones (ruby, sapphire, amethyst) and small zircons',
  'zlote-bransoletki': 'two or three delicate gold bracelets, one chain bracelet and one bangle',
  'zloto-zloto-dla-dzieci': 'tiny delicate gold jewelry for children: small stud earrings and a little pendant on a thin chain',
  'zlote-lancuszki': 'several fine gold chains of different weaves, gracefully draped',
  'zloto-diamenty': 'luxurious gold jewelry set with diamonds: a diamond ring and diamond earrings',
  'zlote-zawieszki': 'several gold pendants (a heart, a small gemstone pendant, a letter-free charm) on thin chains',
  'zlote-broszki': 'two vintage-style gold brooches with small stones',
  'zlote-medaliki': 'small gold religious medallions (round and oval) on thin chains, without readable inscriptions',
  'zlote-zegarki': 'an elegant women\'s gold wristwatch with a minimalist dial without any brand or text',
  'zlote-krzyzyki': 'small gold cross pendants on thin gold chains',
  'srebro': 'an arrangement of assorted sterling silver jewelry: a ring, earrings, a chain and a bracelet',
  'srebrne-pierscionki': 'several sterling silver rings, some with zircons and colored stones',
  'srebrne-bransoletki': 'delicate sterling silver bracelets, chain and bangle',
  'srebrne-krzyzyki': 'small sterling silver cross pendants on thin silver chains',
  'srebrne-zawieszki': 'sterling silver pendants (heart, gemstone, charm) on thin chains',
  'srebrne-lancuszki': 'several fine sterling silver chains of different weaves, gracefully draped',
  'srebrne-broszki': 'two elegant sterling silver brooches with small stones',
  'srebrne-medaliki': 'small sterling silver religious medallions on thin chains, without readable inscriptions',
  'srebrne-kolczyki': 'several pairs of sterling silver earrings (studs, hoops, drops), some with zircons',
  'srebrna-bizuteria-dla-dzieci': 'tiny delicate sterling silver jewelry for children: small stud earrings and a little pendant',
  'bizuteria-ze-stali': 'men\'s jewelry: a stainless steel bracelet and a black leather bracelet with a steel clasp',
  'obraczki': 'a pair of classic wedding bands, one yellow gold and one white gold, leaning against each other',
  'indywidualne-projekty-158': 'a goldsmith\'s workbench close-up: a handmade gold ring with a gemstone held in a jeweler\'s tool, soft focus tools in the background',
};

// slug strony WordPressa → co ma być na zdjęciu
const STRONY_OPISY = {
  'naprawa-bizuterii-opole': 'a goldsmith workbench in a jewelry repair workshop: a gold chain with an open link and a ring on a ring mandrel, fine pliers and a small soldering torch nearby, warm workshop light',
  'skup-zlota-opole': 'a small pile of assorted old gold jewelry (broken chains, single earrings, rings) next to a few small gold bars and a precise jeweler scale',
  'salon-firmowy': 'the interior of an elegant, intimate jewelry boutique: glass display cases with softly lit gold jewelry, warm lamps, dark wood, out-of-focus depth',
  'blog': 'a cozy editorial still life: an open notebook with blank pages, a fountain pen, a loupe and a few pieces of gold jewelry with a loose diamond',
  'o-firmie': 'a traditional goldsmith workbench with classic tools, a gold ring in progress and a jeweler loupe, conveying decades of craftsmanship',
  'kontakt-i-dane-firmy': 'an elegant jewelry gift box slightly open with a gold ring inside, next to a vintage brass desk bell and a small bouquet of dried flowers',
  '_strony': 'a minimal elegant still life: a closed dark jewelry gift box tied with a thin gold ribbon and a single delicate gold necklace beside it',
};
const ZESTAW = STRONY ? STRONY_OPISY : KATEGORIE;

const prompt = (opis, nazwa) => `Use image generation to create a wide, luxurious e-commerce page banner photo for a jewelry store, landscape format 1536x1024 px.
Subject: ${opis}.
Style: high-end studio product photography, the jewelry placed on the RIGHT third of the frame on dark velvet or dark stone, soft warm golden rim light and gentle bokeh sparkles.
Background: deep dark plum / espresso brown (similar to #2a1f24), smooth gradient, the LEFT two thirds calm and almost empty (a heading will be overlaid there).
STRICT: no text, no letters, no logos, no watermarks, no people, no hands. Photorealistic, sharp, elegant, not cluttered.
Save the image in the current working directory as ${nazwa}.png and reply with just the file name.`;

// Limit ChatGPT: czekamy, aż się odnowi (resets_in_seconds z odpowiedzi, inaczej 30 min), i ponawiamy.
function czekajNaLimit(tekst) {
  const sek = tekst.match(/resets_in_seconds\D{0,3}(\d+)/);
  const ms = sek ? (Number(sek[1]) + 60) * 1000 : 30 * 60_000;
  console.log(`${new Date().toLocaleTimeString('pl')} limit ChatGPT — czekam do ${new Date(Date.now() + ms).toLocaleTimeString('pl')}`);
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

const wybrane = process.argv.slice(2).filter((a) => a !== '--strony');
for (const [slug, opis] of Object.entries(ZESTAW)) for (let proba = 0; proba < 20; proba++) {
  if (wybrane.length && !wybrane.includes(slug)) break;
  const cel = join(WYNIK, `${slug}.png`);
  if (existsSync(cel) && (!wybrane.length || proba > 0)) break;
  const kat = join(ROBOCZY, slug);
  rmSync(kat, { recursive: true, force: true });
  mkdirSync(kat, { recursive: true });
  const start = Date.now();
  let blad = null, wyjscie = '';
  try {
    wyjscie = execFileSync(process.execPath, [CODEX, 'exec', '-m', 'gpt-6-sol', '--skip-git-repo-check', '-s', 'workspace-write',
      '-C', kat, prompt(opis, slug)], { stdio: ['ignore', 'pipe', 'pipe'], timeout: 6 * 60_000, windowsHide: true, encoding: 'utf8' });
  } catch (e) {
    blad = String(e.stderr ?? e.message).slice(-300);
  }
  const wyj = join(kat, `${slug}.png`);
  // Codex przy limicie kończy się kodem 0 i tylko pisze, że nie zapisał pliku — dlatego sprawdzamy plik.
  if (existsSync(wyj)) { renameSync(wyj, cel); blad = null; rmSync(kat, { recursive: true, force: true }); }
  else blad ??= /usage limit/i.test(wyjscie) ? 'usage limit ' + wyjscie.slice(-400) : 'brak pliku wynikowego';
  console.log(`${new Date().toLocaleTimeString('pl')} ${slug} ${blad ? 'BŁĄD ' + blad.trim().split('\n').pop() : 'ok'} ${Math.round((Date.now() - start) / 1000)}s`);
  if (!blad) break;
  if (/usage limit/i.test(blad)) czekajNaLimit(blad);
  else if (proba >= 2) break; // inny błąd 3 razy — pomijamy tę kategorię
}
console.log('koniec');
