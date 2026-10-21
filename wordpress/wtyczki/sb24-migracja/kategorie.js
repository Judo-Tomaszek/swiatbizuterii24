// Zwijany górny opis kategorii: długi tekst SEO zostaje w HTML-u, na ekranie pierwsze linie + „Czytaj więcej”.
document.querySelectorAll('.sb24-opis-gora').forEach((opis) => {
	if (opis.scrollHeight < 260) return;
	opis.classList.add('sb24-zwiniety');
	opis.id ||= 'sb24-opis-gora';
	const przycisk = document.createElement('button');
	przycisk.type = 'button';
	przycisk.className = 'sb24-wiecej';
	przycisk.textContent = 'Czytaj więcej';
	przycisk.setAttribute('aria-expanded', 'false');
	przycisk.setAttribute('aria-controls', opis.id);
	przycisk.addEventListener('click', () => {
		const zwiniety = opis.classList.toggle('sb24-zwiniety');
		przycisk.textContent = zwiniety ? 'Czytaj więcej' : 'Zwiń';
		przycisk.setAttribute('aria-expanded', String(!zwiniety));
		if (zwiniety) opis.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	});
	opis.after(przycisk);
});
