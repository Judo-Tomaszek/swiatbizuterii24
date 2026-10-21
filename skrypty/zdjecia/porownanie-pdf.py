# Porównanie wszystkich poprawionych zdjęć w PDF: oryginał | Real-ESRGAN | ChatGPT, pod spodem fragment ×3 (najwięcej szczegółów).
# Jedna strona = jeden produkt (A4 poziomo), strona tytułowa jak w teście z 8 zdjęciami.
# Wymaga: pip install reportlab pillow
# Uruchom: python skrypty/zdjecia/porownanie-pdf.py [na-stanie|bez-stanu|wszystkie]
import csv
import io
import json
import sys
from collections import Counter
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph, Table, TableStyle

KORZEN = Path(__file__).resolve().parents[2]
EKSPORT = KORZEN / "dane/eksport"
POPRAWA = KORZEN / "dane/poprawa-zdjec"
zakres = sys.argv[1] if len(sys.argv) > 1 else "wszystkie"
limit = int(sys.argv[2]) if len(sys.argv) > 2 else None  # do próby: tylko pierwsze N stron

pdfmetrics.registerFont(TTFont("Segoe", "C:/Windows/Fonts/segoeui.ttf"))
pdfmetrics.registerFont(TTFont("SegoeB", "C:/Windows/Fonts/segoeuib.ttf"))
pdfmetrics.registerFontFamily("Segoe", normal="Segoe", bold="SegoeB")
FONT = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 15)
W, H = landscape(A4)
M = 28
ZLOTO = HexColor("#8b7b3f")
tekst = ParagraphStyle("t", fontName="Segoe", fontSize=10.5, leading=15)
tekstB = ParagraphStyle("tb", parent=tekst, fontName="SegoeB")

# --- dane: produkty + lista małych zdjęć (kolejność jak w przerabianiu: na stanie, potem najmniejsze)
produkty = {}
for grupa in ("aktywne-na-stanie", "aktywne-stan-0"):
    for p in json.loads((EKSPORT / grupa / "produkty.json").read_text(encoding="utf-8")):
        z = next((z for z in p["zdjecia"] if z["glowne"]), p["zdjecia"][0] if p["zdjecia"] else None)
        if z:
            produkty[str(p["shoper_id"])] = {**p, "plik_glowny": EKSPORT / grupa / z["plik"], "gfx": z["gfx_id"]}

with open(EKSPORT / "male-zdjecia.csv", encoding="utf-8-sig") as f:
    wiersze = list(csv.reader(f, delimiter=";"))[1:]
lista = sorted(({"id": w[0], "nazwa": w[1], "kategoria": w[2], "stan": float(w[3] or 0), "adres": w[4], "px": int(w[5])}
                for w in wiersze if w[0] in produkty), key=lambda x: (x["stan"] <= 0, x["px"]))
if zakres == "na-stanie":
    lista = [x for x in lista if x["stan"] > 0]
elif zakres == "bez-stanu":
    lista = [x for x in lista if x["stan"] <= 0]
lista = lista[:limit]


def okno_detalu(obraz):
    """Kwadrat 1/3 boku tam, gdzie oryginał ma najwięcej krawędzi (kamienie, zapięcie), a nie puste tło w środku."""
    N, K = 30, 10
    e = obraz.convert("L").filter(ImageFilter.FIND_EDGES).resize((N, N), Image.BOX).load()
    naj, xy = -1, (K, K)
    for y in range(N - K + 1):
        for x in range(N - K + 1):
            suma = sum(e[x + i, y + j] for i in range(K) for j in range(K))
            if suma > naj:
                naj, xy = suma, (x, y)
    return xy[0] / N, xy[1] / N, K / N


def zestawienie(p):
    """Obraz 3 kolumny × (całość, środek ×3), jak zestawienie.ps1 z testu."""
    nazwa = f"{p['id']}-{produkty[p['id']]['gfx']}"
    pliki = [produkty[p["id"]]["plik_glowny"], POPRAWA / "esrgan" / f"{nazwa}.jpg", POPRAWA / "chatgpt" / f"{nazwa}.png"]
    etykiety = ["Oryginał (Shoper)", "Real-ESRGAN x4", "ChatGPT / Codex"]
    with Image.open(pliki[0]) as o:
        fx, fy, fb = okno_detalu(o)
    P, S = 340, 12
    img = Image.new("RGB", (3 * P + 4 * S, 2 * P + 3 * S + 26), "white")
    d = ImageDraw.Draw(img)
    for i, plik in enumerate(pliki):
        x = S + i * (P + S)
        if not plik.exists():
            d.text((x + 110, P // 2), "brak wyniku", fill="black", font=FONT)
            continue
        with Image.open(plik) as o:
            o = o.convert("RGB")
            img.paste(o.resize((P, P), Image.LANCZOS), (x, S))
            w, h = o.size
            okno = (int(fx * w), int(fy * h), int((fx + fb) * w), int((fy + fb) * h))
            img.paste(o.crop(okno).resize((P, P), Image.LANCZOS), (x, 2 * S + P))
            etykieta = f"{etykiety[i]} · {w} px"
        d.rectangle((x, S, x + P, S + P), outline="#d0d0d0")
        d.rectangle((x, 2 * S + P, x + P, 2 * S + 2 * P), outline="#d0d0d0")
        d.text((x, 2 * P + 2 * S + 4), etykieta, fill="black", font=FONT)
    bufor = io.BytesIO()
    img.save(bufor, "JPEG", quality=72, optimize=True)
    bufor.seek(0)
    return ImageReader(bufor), img.size


wynik = POPRAWA / f"porownanie-esrgan-vs-chatgpt-{zakres}{'-proba' if limit else ''}.pdf"
c = canvas.Canvas(str(wynik), pagesize=(W, H))
c.setTitle(f"Poprawa zdjęć — Real-ESRGAN vs ChatGPT ({zakres})")
c.setAuthor("swiatbizuterii24 — migracja")

# --- strona tytułowa
ile_esrgan = sum((POPRAWA / "esrgan" / f"{x['id']}-{produkty[x['id']]['gfx']}.jpg").exists() for x in lista)
ile_chatgpt = sum((POPRAWA / "chatgpt" / f"{x['id']}-{produkty[x['id']]['gfx']}.png").exists() for x in lista)
kategorie = Counter(x["kategoria"].split(" > ")[0] for x in lista)
c.setFont("SegoeB", 22)
c.drawString(M, H - M - 20, "Poprawa zdjęć produktów: Real-ESRGAN vs ChatGPT")
c.setFillColor(ZLOTO)
c.setFont("Segoe", 12)
opis_zakresu = {"na-stanie": "produkty na stanie", "bez-stanu": "produkty bez stanu", "wszystkie": "wszystkie aktywne produkty"}[zakres]
c.drawString(M, H - M - 42, f"swiatbizuterii24.pl · {opis_zakresu} z głównym zdjęciem < 800 px · {len(lista)} zdjęć")
c.setFillColor(HexColor("#000000"))

dane = [
    [Paragraph("", tekstB), Paragraph("Real-ESRGAN x4", tekstB), Paragraph("ChatGPT (Codex)", tekstB)],
    [Paragraph("Jak działa", tekstB), Paragraph("powiększa i wyostrza istniejące zdjęcie, lokalnie na komputerze", tekst),
     Paragraph("rysuje zdjęcie od nowa na podstawie oryginału (generowanie obrazu)", tekst)],
    [Paragraph("Wynik", tekstB), Paragraph("4× większe (415 px → 1660 px), łagodnie ostrzejsze, efekt „plastiku”", tekst),
     Paragraph("1024 px, ostre jak zdjęcie katalogowe", tekst)],
    [Paragraph("Wierność", tekstB), Paragraph("kształt zachowany; artefakty przy drobnych kamieniach", tekst),
     Paragraph("<b>może zmienić szczegóły</b> (kolor kamienia, faktura, zawias) — w tej serii prompt zabrania "
               "zmiany faktury i nasycenia kolorów, ale każde zdjęcie trzeba obejrzeć", tekst)],
    [Paragraph("Czas", tekstB), Paragraph("~1–2 s na zdjęcie", tekst),
     Paragraph("~1 min na zdjęcie; limit planu ChatGPT ≈ 150 zdjęć, potem kilka godzin przerwy", tekst)],
    [Paragraph("Gotowe w tym pliku", tekstB), Paragraph(f"{ile_esrgan} / {len(lista)}", tekst), Paragraph(f"{ile_chatgpt} / {len(lista)}", tekst)],
]
t = Table(dane, colWidths=[150, 300, 330])
t.setStyle(TableStyle([
    ("GRID", (0, 0), (-1, -1), 0.5, HexColor("#d8d2bb")),
    ("BACKGROUND", (0, 0), (-1, 0), HexColor("#f3efe2")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("TOPPADDING", (0, 0), (-1, -1), 6), ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
]))
_, th = t.wrapOn(c, W - 2 * M, H)
t.drawOn(c, M, H - M - 70 - th)

kat_txt = ", ".join(f"{k} {v}" for k, v in kategorie.most_common())
info = Paragraph(
    "<b>Jak czytać kolejne strony:</b> górny rząd — całe zdjęcie; dolny rząd — fragment powiększony ×3, wybrany "
    "automatycznie tam, gdzie oryginał ma najwięcej szczegółów (tu widać różnice). Kolumny: oryginał z Shopera · Real-ESRGAN · ChatGPT. Pod nazwą: kategoria, stan, ID w Shoperze "
    "i adres w sklepie — przy akceptacji wystarczy podać ID i wybraną kolumnę (np. „4421 – ChatGPT”, „1698 – ESRGAN”, "
    "„2363 – oryginał”).<br/><br/>"
    f"<b>Kolejność:</b> najpierw produkty na stanie, w każdej grupie od najmniejszych zdjęć. <b>Kategorie:</b> {kat_txt}.", tekst)
_, ih = info.wrapOn(c, W - 2 * M, H)
info.drawOn(c, M, H - M - 90 - th - ih)
c.showPage()

# --- po jednej stronie na produkt
for i, p in enumerate(lista, 1):
    c.setFont("SegoeB", 13)
    c.drawString(M, H - M - 8, f"{i}. {p['nazwa']}")
    c.setFont("Segoe", 9.5)
    c.setFillColor(ZLOTO)
    stan = f"stan {p['stan']:g}" if p["stan"] > 0 else "brak na stanie"
    c.drawString(M, H - M - 22, f"{p['kategoria']} · {stan} · ID Shoper {p['id']} · swiatbizuterii24.pl{p['adres']}")
    c.setFillColor(HexColor("#000000"))
    obraz, (iw, ih) = zestawienie(p)
    skala = min((W - 2 * M) / iw, (H - 2 * M - 46) / ih)
    w, h = iw * skala, ih * skala
    c.drawImage(obraz, (W - w) / 2, M + 6, w, h)
    c.setFont("Segoe", 8)
    c.drawRightString(W - M, M - 12, f"{i + 1} / {len(lista) + 1}")
    c.showPage()
    if i % 100 == 0:
        print(i, flush=True)

c.save()
print(wynik, f"{wynik.stat().st_size / 1e6:.0f} MB")
