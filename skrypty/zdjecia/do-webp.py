# Poprawione zdjęcia → WebP bez żadnych metadanych (m.in. znacznik AI C2PA „caBX” od ChatGPT, tEXt z datami, EXIF, XMP, ICC).
# Źródła zostają nietknięte; wynik: dane/eksport/zdjecia-webp/{produkty,banery-kategorii,banery-stron}/ (widoczne w kontenerze jako /import/dane/zdjecia-webp).
# Produkty: wersja z ChatGPT, a gdy jej jeszcze nie ma — z ESRGAN (zmniejszona do 1024 px jak ChatGPT). Gdy później dojdzie
# wersja z ChatGPT (plik nowszy niż WebP), WebP jest robiony od nowa — importuj-tresci.php zdjecia podmieni go po md5.
# Skąd jest każdy WebP: produkty/_zrodla.json (nazwa → chatgpt|esrgan), czyta go import.
# Przezroczystość spłaszczana na białe tło (jak oryginalne zdjęcia sklepu). Wznawialne — pomija gotowe, chyba że podasz --od-nowa.
# Uruchom: python -I skrypty/zdjecia/do-webp.py [--od-nowa]
import json
import sys
from pathlib import Path

from PIL import Image

KORZEN = Path(__file__).resolve().parents[2]
WYNIK = KORZEN / "dane/eksport/zdjecia-webp"
POPRAWA = KORZEN / "dane/poprawa-zdjec"
MAKS_PRODUKT = 1024  # bok zdjęcia produktu (ChatGPT daje 1024, ESRGAN x4 nawet 4000)
od_nowa = "--od-nowa" in sys.argv


def zapisz(zrodlo, wyj, jakosc, maks=None):
    with Image.open(zrodlo) as im:
        im.load()
        if im.mode in ("RGBA", "LA", "P"):
            im = im.convert("RGBA")
            if im.getchannel("A").getextrema()[0] < 255:
                tlo = Image.new("RGBA", im.size, (255, 255, 255, 255))
                im = Image.alpha_composite(tlo, im)
        # nowy obraz z samych pikseli — nic z info/chunków źródła nie przechodzi dalej
        czysty = Image.frombytes("RGB", im.size, im.convert("RGB").tobytes())
    if maks and max(czysty.size) > maks:
        czysty.thumbnail((maks, maks), Image.LANCZOS)
    czysty.save(wyj, "WEBP", quality=jakosc, method=6)


def aktualny(zrodlo, wyj):
    return wyj.exists() and not od_nowa and wyj.stat().st_mtime >= zrodlo.stat().st_mtime


# Produkty: ChatGPT ma pierwszeństwo przed ESRGAN
cel = WYNIK / "produkty"
cel.mkdir(parents=True, exist_ok=True)
zrodla = {p.stem: ("esrgan", p) for p in (POPRAWA / "esrgan").glob("*.jpg")}
zrodla |= {p.stem: ("chatgpt", p) for p in (POPRAWA / "chatgpt").glob("*.png")}
spis = {}
licz = {"chatgpt": 0, "esrgan": 0}
for nazwa, (skad, plik) in sorted(zrodla.items()):
    wyj = cel / f"{nazwa}.webp"
    spis[nazwa] = skad
    if aktualny(plik, wyj):
        continue
    zapisz(plik, wyj, 85, MAKS_PRODUKT)
    licz[skad] += 1
(cel / "_zrodla.json").write_text(json.dumps(spis, indent=0), encoding="utf8")
razem = {s: sum(1 for v in spis.values() if v == s) for s in licz}
print(f"produkty: nowe/odświeżone {licz}, razem {razem}")

for nazwa, zrodlo, jakosc in [
    ("banery-kategorii", KORZEN / "dane/eksport/banery-kategorii", 82),
    ("banery-stron", KORZEN / "dane/eksport/banery-stron", 82),
]:
    cel = WYNIK / nazwa
    cel.mkdir(parents=True, exist_ok=True)
    n = 0
    for plik in sorted(zrodlo.glob("*.png")):
        wyj = cel / (plik.stem + ".webp")
        if aktualny(plik, wyj):
            continue
        zapisz(plik, wyj, jakosc)
        n += 1
    print(f"{nazwa}: {n} nowych, razem {len(list(cel.glob('*.webp')))}")
