# Lista pozostałych zdjęć produktów do poprawy (wszystkie zdjęcia aktywnych produktów — główne i galerie —
# poza tymi z male-zdjecia.csv, które już są w dane/poprawa-zdjec/chatgpt/). Nieaktywnych produktów nie ma w sklepie, więc pomijamy.
# Kolejność: najpierw produkty na stanie, w obu grupach od najmniejszego zdjęcia.
# Wynik: dane/eksport/reszta-zdjec.csv (shoper_id;gfx_id;plik;stan;szer;wys), plik względem dane/eksport/.
# Uruchom: python -I skrypty/zdjecia/lista-reszty.py
import csv
import json
from pathlib import Path

from PIL import Image

KORZEN = Path(__file__).resolve().parents[2]
EKSPORT = KORZEN / "dane/eksport"
gotowe = {p.stem.split("-")[1] for p in (KORZEN / "dane/poprawa-zdjec/chatgpt").glob("*.png")}

wiersze, widziane = [], set()
for grupa, stan in (("aktywne-na-stanie", 1), ("aktywne-stan-0", 0)):
    for p in json.loads((EKSPORT / grupa / "produkty.json").read_text(encoding="utf8")):
        for z in p["zdjecia"]:
            gfx = str(z["gfx_id"])
            plik = EKSPORT / grupa / z["plik"]
            if gfx in widziane or gfx in gotowe or not plik.exists():
                continue
            widziane.add(gfx)
            with Image.open(plik) as im:
                w, h = im.size
            wiersze.append([p["shoper_id"], gfx, f"{grupa}/{z['plik']}", stan, w, h])

wiersze.sort(key=lambda r: (-r[3], max(r[4], r[5])))
with open(EKSPORT / "reszta-zdjec.csv", "w", newline="", encoding="utf8") as f:
    csv.writer(f, delimiter=";").writerows([["shoper_id", "gfx_id", "plik", "stan", "szer", "wys"], *wiersze])
print(f"{len(wiersze)} zdjęć, na stanie {sum(r[3] for r in wiersze)}, niekwadratowych {sum(r[4] != r[5] for r in wiersze)}")
