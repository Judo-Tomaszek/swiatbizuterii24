// Klient REST API Shopera — WYŁĄCZNIE ODCZYT (GET). Zapisów do sklepu źródłowego nie robimy.
// Dane dostępowe z .env: uruchamiaj przez `node --env-file=.env …`.
// Limit API: kubełek zapytań — trzymamy odstęp między żądaniami i honorujemy 429 + Retry-After.

const BASE = (process.env.SHOPER_SHOP_URL || "").replace(/\/+$/, "");
const TOKEN = process.env.SHOPER_ACCESS_TOKEN;
const ODSTEP_MS = 550;

if (!BASE || !TOKEN) {
  console.error("Brak SHOPER_SHOP_URL lub SHOPER_ACCESS_TOKEN — uruchom z `node --env-file=.env`.");
  process.exit(1);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let ostatnie = 0;

export async function get(zasob, params = {}) {
  const url = new URL(`${BASE}/webapi/rest/${zasob}`);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, typeof v === "string" ? v : JSON.stringify(v));

  for (let proba = 1; proba <= 5; proba++) {
    const czekaj = ostatnie + ODSTEP_MS - Date.now();
    if (czekaj > 0) await sleep(czekaj);
    ostatnie = Date.now();

    const res = await fetch(url, { headers: { Authorization: `Bearer ${TOKEN}`, Accept: "application/json" } });
    if (res.status === 429) {
      await sleep((Number(res.headers.get("retry-after")) || 2) * 1000);
      continue;
    }
    const tekst = await res.text();
    let body;
    try { body = JSON.parse(tekst); } catch { body = tekst; }
    return { status: res.status, body };
  }
  return { status: 429, body: "limit zapytań — 5 prób bez skutku" };
}

// Pobiera wszystkie strony listy (limit 50 = maksimum Shopera).
export async function getAll(zasob, params = {}) {
  const wynik = [];
  let strona = 1, stron = 1;
  do {
    const { status, body } = await get(zasob, { ...params, limit: 50, page: strona });
    if (status !== 200) throw new Error(`${zasob} strona ${strona}: HTTP ${status} ${JSON.stringify(body).slice(0, 200)}`);
    wynik.push(...(body.list || []));
    stron = Number(body.pages) || 1;
    strona++;
  } while (strona <= stron);
  return wynik;
}
