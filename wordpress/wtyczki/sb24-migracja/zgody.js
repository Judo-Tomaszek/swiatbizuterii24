/* Zgody na pliki cookies (teksty i kategorie jak w Shoperze) + Google Consent Mode v2.
   Domyślne zgody „denied” ustawia wcześniej skrypt w <head> (przed GTM); tu tylko okno i aktualizacja.
   Wybór: ciasteczko sb24_zgody (JSON, 12 miesięcy). Ponowne otwarcie: każdy element z klasą .sb24-zgody-otworz. */
(function () {
	var NAZWA = 'sb24_zgody';
	var KAT = [
		{ id: 'niezbedne', tytul: 'Niezbędne do działania strony', opis: 'Te pliki są niezbędne do działania naszej strony internetowej, dlatego też nie możesz ich wyłączyć.', stale: true },
		{ id: 'funkcjonalne', tytul: 'Funkcjonalne', opis: 'Te pliki umożliwiają Ci korzystanie z pozostałych funkcji strony internetowej (innych niż niezbędne do jej działania). Ich włączenie da Ci dostęp do pełnej funkcjonalności strony.' },
		{ id: 'analityczne', tytul: 'Analityczne', opis: 'Te pliki pozwalają nam na dokonanie analiz dotyczących naszego sklepu internetowego, co może przyczynić się do jego lepszego funkcjonowania i dostosowania do potrzeb Użytkowników.' },
		{ id: 'marketingowe', tytul: 'Marketingowe', opis: 'Dzięki tym plikom możemy prowadzić działania marketingowe.' }
	];
	var cfg = window.sb24Zgody || {};

	function odczytaj() {
		var m = document.cookie.match(new RegExp('(?:^|; )' + NAZWA + '=([^;]*)'));
		try { return m ? JSON.parse(decodeURIComponent(m[1])) : null; } catch (e) { return null; }
	}
	function zapisz(z) {
		z.data = new Date().toISOString().slice(0, 10);
		document.cookie = NAZWA + '=' + encodeURIComponent(JSON.stringify(z)) + '; max-age=' + 365 * 86400 + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
		zastosuj(z);
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push({ event: 'sb24_zgody', zgody: z });
	}
	function zastosuj(z) {
		if (typeof window.gtag !== 'function') return;
		var t = function (b) { return b ? 'granted' : 'denied'; };
		window.gtag('consent', 'update', {
			functionality_storage: t(z.funkcjonalne),
			personalization_storage: t(z.funkcjonalne),
			analytics_storage: t(z.analityczne),
			ad_storage: t(z.marketingowe),
			ad_user_data: t(z.marketingowe),
			ad_personalization: t(z.marketingowe)
		});
	}
	function wszystkie(b) { return { funkcjonalne: b, analityczne: b, marketingowe: b }; }

	var okno;
	function zbuduj() {
		okno = document.createElement('div');
		okno.className = 'sb24-zgody';
		okno.setAttribute('role', 'dialog');
		okno.setAttribute('aria-modal', 'false');
		okno.setAttribute('aria-labelledby', 'sb24-zgody-tytul');
		var lista = KAT.map(function (k) {
			return '<label class="sb24-zgody__kat"><input type="checkbox" data-kat="' + k.id + '"' + (k.stale ? ' checked disabled' : '') + '>' +
				'<span><strong>' + k.tytul + '</strong><small>' + k.opis + '</small></span></label>';
		}).join('');
		okno.innerHTML =
			'<div class="sb24-zgody__widok" data-widok="podst">' +
				'<p class="sb24-zgody__tytul" id="sb24-zgody-tytul">Dbamy o Twoją prywatność</p>' +
				'<p>Pliki cookies i pokrewne im technologie umożliwiają poprawne działanie strony i pomagają nam dostosować ofertę do Twoich potrzeb. ' +
				'Możesz zaakceptować wykorzystanie przez nas wszystkich tych plików i przejść do sklepu lub dostosować użycie plików do swoich preferencji, wybierając opcję „Dostosuj zgody”. ' +
				'<a href="' + (cfg.polityka || '#') + '">Więcej o plikach cookies przeczytasz w naszej Polityce prywatności.</a></p>' +
				'<div class="sb24-zgody__przyciski">' +
					'<button type="button" class="sb24-zgody__link" data-akcja="dostosuj">Dostosuj zgody</button>' +
					'<button type="button" class="sb24-zgody__btn sb24-zgody__btn--jasny" data-akcja="niezbedne">Zaakceptuj tylko niezbędne</button>' +
					'<button type="button" class="sb24-zgody__btn" data-akcja="wszystkie">Zaakceptuj wszystkie</button>' +
				'</div>' +
			'</div>' +
			'<div class="sb24-zgody__widok" data-widok="ust" hidden>' +
				'<p class="sb24-zgody__tytul">Ustawienia plików cookies</p>' +
				'<p>W tym miejscu możesz określić swoje preferencje w zakresie wykorzystywania przez nas plików cookies.</p>' +
				lista +
				'<div class="sb24-zgody__przyciski">' +
					'<button type="button" class="sb24-zgody__link" data-akcja="anuluj">Anuluj</button>' +
					'<button type="button" class="sb24-zgody__btn sb24-zgody__btn--jasny" data-akcja="zaznacz">Zaznacz wszystko</button>' +
					'<button type="button" class="sb24-zgody__btn" data-akcja="zapisz">Zapisz ustawienia</button>' +
				'</div>' +
			'</div>';
		document.body.appendChild(okno);
		okno.addEventListener('click', function (e) {
			var a = e.target.closest('[data-akcja]');
			if (!a) return;
			var akcja = a.getAttribute('data-akcja');
			if (akcja === 'wszystkie') { zapisz(wszystkie(true)); zamknij(); }
			if (akcja === 'niezbedne') { zapisz(wszystkie(false)); zamknij(); }
			if (akcja === 'dostosuj') widok('ust');
			if (akcja === 'anuluj') { if (odczytaj()) zamknij(); else widok('podst'); }
			if (akcja === 'zaznacz') okno.querySelectorAll('input[data-kat]').forEach(function (i) { i.checked = true; });
			if (akcja === 'zapisz') {
				var z = {};
				okno.querySelectorAll('input[data-kat]:not([disabled])').forEach(function (i) { z[i.getAttribute('data-kat')] = i.checked; });
				zapisz(z);
				zamknij();
			}
		});
	}
	function widok(nazwa) {
		okno.querySelectorAll('[data-widok]').forEach(function (w) { w.hidden = w.getAttribute('data-widok') !== nazwa; });
	}
	function otworz(ustawienia) {
		if (!okno) zbuduj();
		var z = odczytaj() || {};
		okno.querySelectorAll('input[data-kat]:not([disabled])').forEach(function (i) { i.checked = !!z[i.getAttribute('data-kat')]; });
		widok(ustawienia ? 'ust' : 'podst');
		okno.classList.add('sb24-zgody--widoczne');
	}
	function zamknij() { okno.classList.remove('sb24-zgody--widoczne'); }

	document.addEventListener('click', function (e) {
		if (e.target.closest('.sb24-zgody-otworz')) { e.preventDefault(); otworz(true); }
	});
	function start() {
		// link „Ustawienia cookies” w dolnej belce stopki (Kadence: .site-bottom-footer-wrap)
		var belka = document.querySelector('.footer-html-inner p:last-child') || document.querySelector('.footer-html-inner');
		if (belka && !document.querySelector('.sb24-zgody-otworz')) {
			belka.insertAdjacentHTML('beforeend', ' · <a href="#" class="sb24-zgody-otworz">Ustawienia cookies</a>');
		}
		if (!odczytaj()) otworz(false);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
