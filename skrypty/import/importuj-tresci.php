<?php
/**
 * Import treści z eksportu (dane/eksport/tresci/, buduje je skrypty/eksport/buduj-tresci.mjs):
 * obrazki z tekstów, strony informacyjne, blog, strona główna.
 *
 *   skrypty/wp.sh eval-file /import/skrypty/importuj-tresci.php [obrazy|strony|blog|glowna|wszystko]
 *   (osobno: banery — banery kategorii i stron, zdjecia — poprawione zdjęcia produktów; oba z dane/eksport/zdjecia-webp/)
 *   skrypty/wp.sh sb24 przebuduj        # potem: tabela starych adresów (wtyczka sb24-migracja)
 *
 * Idempotentny: strony i wpisy po `_sb24_shoper_id` + `_sb24_typ`, obrazki po `_sb24_zrodlo`.
 * Obrazki z serwera Shopera (`/userdata/…`) trafiają do biblioteki mediów, a ich adresy w treściach
 * (stron, wpisów, strony głównej i opisów kategorii) są podmieniane — po wyłączeniu Shopera stare
 * adresy przestaną działać.
 *
 * Blog w Shoperze nie ma dat (API: 0000-00-00, strona ich nie pokazuje) — wpis bez daty dostaje
 * datę importu przesuniętą o ID (zachowuje kolejność) i meta `_sb24_brak_daty`.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

const SB24_TRESCI = '/import/dane/tresci';
const SB24_SKLEP  = 'https://swiatbizuterii24.pl';

function sb24t_json( $plik ) {
	$d = json_decode( file_get_contents( SB24_TRESCI . "/$plik" ), true );
	if ( null === $d ) {
		WP_CLI::error( "Nie mogę odczytać $plik" );
	}
	return $d;
}

function sb24t_znajdz( $typ, $shoper_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT a.post_id FROM {$wpdb->postmeta} a
		 JOIN {$wpdb->postmeta} b ON b.post_id = a.post_id AND b.meta_key = '_sb24_typ' AND b.meta_value = %s
		 WHERE a.meta_key = '_sb24_shoper_id' AND a.meta_value = %s LIMIT 1",
		$typ, (string) $shoper_id
	) );
}

function sb24t_meta( $id, $typ, $obiekt ) {
	update_post_meta( $id, '_sb24_shoper_id', (string) $obiekt['shoper_id'] );
	update_post_meta( $id, '_sb24_typ', $typ );
	update_post_meta( $id, '_sb24_sciezka', $obiekt['sciezka'] );
	update_post_meta( $id, '_sb24_seo_title', $obiekt['seo']['title'] ?? null );
	update_post_meta( $id, '_sb24_seo_description', $obiekt['seo']['description'] ?? null );
	update_post_meta( $id, '_sb24_h1', $obiekt['seo']['h1'] ?? null );
}

function sb24t_zapisz( $typ, $obiekt, $dane ) {
	$id = sb24t_znajdz( $typ, $obiekt['shoper_id'] );
	if ( $id ) {
		$dane['ID'] = $id;
	}
	$wynik = wp_insert_post( wp_slash( $dane ), true );
	if ( is_wp_error( $wynik ) ) {
		WP_CLI::warning( "$typ {$obiekt['shoper_id']}: " . $wynik->get_error_message() );
		return 0;
	}
	sb24t_meta( $wynik, $typ, $obiekt );
	return (int) $wynik;
}

/* ------------------------------------------------------------ obrazki */

/** Mapa „adres w treści Shopera → adres w WordPressie” (obrazki już zaimportowane). */
function sb24t_mapa_obrazow() {
	global $wpdb;
	$mapa = [];
	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sb24_zrodlo'" ) as $r ) {
		$mapa[ $r->meta_value ] = [ 'id' => (int) $r->post_id, 'url' => wp_get_attachment_url( $r->post_id ) ];
	}
	return $mapa;
}

function sb24t_importuj_obrazy() {
	$istniejace = sb24t_mapa_obrazow();
	$n          = 0;
	foreach ( sb24t_json( 'obrazy.json' ) as $o ) {
		if ( isset( $istniejace[ $o['zrodlo'] ] ) ) {
			continue;
		}
		$plik = SB24_TRESCI . '/' . $o['plik'];
		$tmp  = wp_tempnam( basename( $plik ) );
		copy( $plik, $tmp );
		$id = media_handle_sideload( [ 'name' => sanitize_file_name( basename( $plik ) ), 'tmp_name' => $tmp ], 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			WP_CLI::warning( "Obrazek {$o['zrodlo']}: " . $id->get_error_message() );
			continue;
		}
		update_post_meta( $id, '_sb24_zrodlo', $o['zrodlo'] );
		$n++;
	}
	WP_CLI::success( "Obrazki z treści: nowe $n, wszystkich " . count( sb24t_mapa_obrazow() ) );
}

/** Podmienia adresy obrazków Shopera w HTML-u (względne i bezwzględne warianty) na biblioteką mediów. */
function sb24t_podmien_obrazy( $html ) {
	static $zamiany = null;
	if ( null === $zamiany ) {
		$zamiany = [];
		foreach ( sb24t_mapa_obrazow() as $zrodlo => $o ) {
			$sciezka = preg_replace( '#^https?://(www\.)?swiatbizuterii24\.pl#', '', $zrodlo );
			foreach ( [ "https://swiatbizuterii24.pl$sciezka", "http://swiatbizuterii24.pl$sciezka", "https://www.swiatbizuterii24.pl$sciezka", $zrodlo ] as $wariant ) {
				$zamiany[ "\"$wariant\"" ] = "\"{$o['url']}\"";
			}
		}
	}
	return sb24t_bez_blokow_wp( strtr( (string) $html, $zamiany ) );
}

/**
 * Teksty w Shoperze były kiedyś wklejone z WordPressa: zostały w nich komentarze bloków
 * (`<p><!-- wp:heading {"level":3} --></p>`), często niesparowane. Shoper ich nie widział, ale parser
 * bloków WordPressa tak — przestawiał kolejność treści (wpis „Kształty diamentów” wychodził poza
 * artykuł) i psuł edytor. Usuwamy same komentarze i puste akapity po nich; tekst bez zmian.
 */
function sb24t_bez_blokow_wp( $html ) {
	$html = preg_replace( '#<!--\s*/?wp:[^>]*?-->#', '', $html );
	return preg_replace( '#<p>(\s|&nbsp;)*</p>\s*#', '', $html );
}

/* ------------------------------------------------------------ strony, blog */

/**
 * Treść strony poprawiona ręcznie (skrypty/import/tresci-poprawione/<slug>.html) zamiast tej z Shopera.
 * Tylko dla stron, gdzie w Shoperze został szablon („Zwroty i reklamacje”: przykład z Shopera z polami
 * {shop_company_name}…). Strony z 1. miejsc w Google NIE trafiają tutaj — tam treść 1:1.
 * WZOR_PDF → adres wzoru odstąpienia od umowy w bibliotece mediów.
 */
function sb24t_tresc_poprawiona( $s ) {
	$plik = '/import/skrypty/tresci-poprawione/' . sanitize_title( basename( preg_replace( '#/\d+$#', '', $s['sciezka'] ) ) ) . '.html';
	if ( ! is_file( $plik ) ) {
		return $s['tresc'];
	}
	$pdf = sb24t_mapa_obrazow()['/userdata/public/assets//WZ%C3%93R-FORMULARZA-ODST%C4%84PIENIA-OD-UMOWY.pdf']['url'] ?? '/chcesz-oddac-zakupiony-towar';
	return str_replace( 'WZOR_PDF', $pdf, file_get_contents( $plik ) );
}

function sb24t_importuj_strony() {
	$n = 0;
	foreach ( sb24t_json( 'strony.json' ) as $s ) {
		$id = sb24t_zapisz( 'strona', $s, [
			'post_type'    => 'page',
			'post_status'  => $s['aktywna'] ? 'publish' : 'draft',
			'post_title'   => $s['tytul'],
			'post_name'    => sanitize_title( basename( preg_replace( '#/\d+$#', '', $s['sciezka'] ) ) ),
			'post_content' => sb24t_podmien_obrazy( sb24t_tresc_poprawiona( $s ) ),
		] );
		if ( $id && $s['uwagi'] ) {
			update_post_meta( $id, '_sb24_uwagi', implode( '; ', $s['uwagi'] ) );
			WP_CLI::warning( "Strona „{$s['tytul']}”: " . implode( '; ', $s['uwagi'] ) );
		}
		$n += (bool) $id;
	}

	// Lista wpisów bloga pod starym adresem /pl/blog
	$blog = sb24t_zapisz( 'strona', [ 'shoper_id' => 0, 'sciezka' => '/pl/blog', 'seo' => null ], [
		'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Blog', 'post_name' => 'blog',
	] );
	update_option( 'page_for_posts', $blog );

	// Dokumenty prawne jak w Shoperze: regulamin (widżet Rzetelnego Regulaminu) i polityka prywatności —
	// WooCommerce pokazuje je przy składaniu zamówienia („Kontynuując zamówienie wyrażasz zgodę na nasz Regulamin…”).
	foreach ( [ 'woocommerce_terms_page_id' => 'regulamin', 'wp_page_for_privacy_policy' => 'polityka-prywatnosci' ] as $opcja => $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p ) {
			update_option( $opcja, $p->ID );
		}
	}
	WP_CLI::success( "Strony: $n + lista bloga (/pl/blog)" );
}

function sb24t_importuj_blog() {
	$wpisy = sb24t_json( 'blog.json' );
	// Wpisy bez daty w Shoperze: daty zastępcze co godzinę wstecz od najnowszej prawdziwej daty —
	// kolejność listy jak w Shoperze (od najwyższego ID), a nie „wszystkie bez daty przed datowanymi”.
	$daty  = array_filter( array_column( $wpisy, 'data' ) );
	$baza  = $daty ? strtotime( max( $daty ) ) : strtotime( '2026-10-16 08:00:00' );
	$maks  = max( array_column( $wpisy, 'shoper_id' ) );
	$n     = 0;
	foreach ( $wpisy as $b ) {
		$data = $b['data'] ?: gmdate( 'Y-m-d H:i:s', $baza - ( $maks - $b['shoper_id'] ) * 3600 );
		$id   = sb24t_zapisz( 'blog', $b, [
			'post_type'    => 'post',
			'post_status'  => $b['aktywny'] ? 'publish' : 'draft',
			'post_title'   => $b['tytul'],
			'post_name'    => sanitize_title( $b['tytul'] ),
			'post_content' => sb24t_podmien_obrazy( $b['tresc'] ),
			'post_excerpt' => sb24t_podmien_obrazy( $b['zajawka'] ),
			'post_date'    => $data,
		] );
		if ( ! $id ) {
			continue;
		}
		$b['data'] ? delete_post_meta( $id, '_sb24_brak_daty' ) : update_post_meta( $id, '_sb24_brak_daty', 1 );
		wp_set_post_tags( $id, $b['tagi'], false );
		if ( $b['zdjecie'] ) {
			$o = sb24t_mapa_obrazow()[ $b['zdjecie']['zrodlo'] ] ?? null;
			if ( $o ) {
				set_post_thumbnail( $id, $o['id'] );
				update_post_meta( $o['id'], '_wp_attachment_image_alt', $b['zdjecie']['alt'] );
			}
		}
		$n++;
	}
	WP_CLI::success( "Wpisy bloga: $n" );
}

/* ------------------------------------------------------------ strona główna */

/**
 * Grafiki układu strony głównej (dane/eksport/tresci/uklad/: zdjęcie z góry od właściciela, kafle,
 * banery i ikony paska zalet ze skórki Shopera) → biblioteka mediów. Zwraca [plik => url].
 */
function sb24t_obrazy_ukladu() {
	$istniejace = sb24t_mapa_obrazow();
	$wynik      = [];
	foreach ( glob( SB24_TRESCI . '/uklad/*.*' ) /* bez GLOB_BRACE — obraz WP-CLI to Alpine */ as $plik ) {
		$klucz = 'uklad/' . basename( $plik );
		if ( isset( $istniejace[ $klucz ] ) && md5_file( $plik ) === get_post_meta( $istniejace[ $klucz ]['id'], '_sb24_md5', true ) ) {
			$wynik[ basename( $plik ) ] = $istniejace[ $klucz ]['url'];
			continue;
		}
		if ( isset( $istniejace[ $klucz ] ) ) {
			wp_delete_attachment( $istniejace[ $klucz ]['id'], true ); // podmieniony plik (np. nowe zdjęcie na górę)
		}
		$tmp = wp_tempnam( basename( $plik ) );
		copy( $plik, $tmp );
		$id = media_handle_sideload( [ 'name' => sanitize_file_name( basename( $plik ) ), 'tmp_name' => $tmp ], 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			WP_CLI::error( "Grafika układu $klucz: " . $id->get_error_message() );
		}
		update_post_meta( $id, '_sb24_zrodlo', $klucz );
		update_post_meta( $id, '_sb24_md5', md5_file( $plik ) );
		$wynik[ basename( $plik ) ] = wp_get_attachment_url( $id );
	}
	return $wynik;
}

function sb24t_importuj_glowna() {
	global $wpdb;
	$g = sb24t_json( 'glowna.json' );

	// Shoper ma 7 produktów na głównej; w siatce 4 kolumn dopełniamy do 8 (decyzja użytkownika, 2026-10-18):
	// 5355 = najnowsze złote kolczyki z cyrkonią, na stanie.
	$ids = [];
	foreach ( array_slice( array_unique( [ ...$g['wyroznione_produkty'], 5355 ] ), 0, 8 ) as $sid ) {
		$pid = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_sb24_shoper_id' AND pm.meta_value = %s AND p.post_type = 'product'", (string) $sid
		) );
		if ( $pid ) {
			$ids[] = $pid;
			$p     = wc_get_product( $pid );
			$p->set_featured( true ); // „produkt na stronie głównej” z Shopera
			$p->save();
		}
	}

	// Układ 1:1 jak na Shoperze (od góry): duże zdjęcie, pasek zalet, H1 + produkty, blok tekstu
	// box_custom46, kafle 4 kategorii, 2 banery, mapa, blok tekstu box_custom51. Teksty bez zmian.
	$u     = sb24t_obrazy_ukladu();
	$bloki = array_column( $g['bloki'], 'html', 'id' );
	$blok  = fn( $bid ) => isset( $bloki[ $bid ] )
		? "<div class=\"sb24-blok\" id=\"sb24-$bid\">" . sb24t_podmien_obrazy( $bloki[ $bid ] ) . '</div>' : '';
	$html  = fn( $s ) => "<!-- wp:html -->\n$s\n<!-- /wp:html -->\n\n";

	$zalety = '';
	foreach ( [ [ 'SSL', 'Bezpieczne zakupy' ], [ '14', 'dni na zwrot towaru' ], [ 'Szybka', 'wysyłka' ], [ 'Korzystne', 'ceny' ], [ 'Sprawdzona', 'jakość' ] ] as $i => $z ) {
		$zalety .= '<li><img src="' . esc_url( $u[ 'ikona0' . ( $i + 1 ) . '.png' ] ) . '" alt="" width="35" height="35"><p><strong>' . $z[0] . '</strong> ' . $z[1] . '</p></li>';
	}
	$kafle = '';
	foreach ( [ [ '/pierscionki-zareczynowe', 'pierscionki-zareczynowe.jpg', 'Pierścionki zaręczynowe' ], [ '/zlote-kolczyki', 'rozowe-zloto-kolczyki.jpg', 'Złote kolczyki' ],
		[ '/obraczki', 'obraczki-slubne.jpg', 'Obrączki ślubne' ], [ '/zlote-lancuszki', 'zlote-lancuszki.jpg', 'Złote łańcuszki' ] ] as $k ) {
		$kafle .= '<a href="' . esc_url( home_url( $k[0] ) ) . '"><img src="' . esc_url( $u[ $k[1] ] ) . '" alt="' . esc_attr( $k[2] ) . '" width="640" height="640" loading="lazy"><span>' . $k[2] . '</span></a>';
	}
	// Baner „Obrączki dostępne od ręki” w Shoperze prowadzi do wyłączonej kategorii /pl/c/Dostepne-od-reki/159
	// (dziś 302 donikąd) — u nas do kategorii Obrączki. Baner rejestracji → Moje konto.
	$banery = '<a href="' . esc_url( home_url( '/obraczki' ) ) . '"><img src="' . esc_url( $u['obrczaki-slubne-jubiler-opole.jpg'] ) . '" alt="obrączki ślubne" width="1048" height="578" loading="lazy"></a>'
		. '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '"><img src="' . esc_url( $u['rejestracja-jubiler-opole.jpg'] ) . '" alt="rejestracja w sklepie" width="1048" height="578" loading="lazy"></a>';
	$mapa = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2528.819258231509!2d17.924931815966616!3d50.667616279506!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47105305da4850b1%3A0xb49873301d19ab34!2sSklep%20Jubilerski%20SwiatBizuterii24.pl!5e0!3m2!1spl!2spl!4v1635093583390!5m2!1spl!2spl';

	$tresc = $html( '<div class="sb24-hero"><img src="' . esc_url( $u['main-foto.jpg'] ) . '" alt="M. B. Drabik – indywidualna kolekcja biżuterii" width="1920" height="793" fetchpriority="high"></div>' )
		. $html( '<ul class="sb24-zalety">' . $zalety . '</ul>' )
		. $html( '<div class="sb24-kontener sb24-polecane"><h1 class="sb24-h1">' . esc_html( $g['h1'] ) . '</h1>[products ids="' . implode( ',', $ids ) . '" orderby="post__in" columns="4"]</div>' )
		. $html( $blok( 'box_custom46' ) )
		. $html( '<div class="sb24-kafle">' . $kafle . '</div>' )
		. $html( '<div class="sb24-banery">' . $banery . '</div>' )
		. $html( '<div class="sb24-mapa"><iframe src="' . esc_url( $mapa ) . '" title="Sklep Jubilerski SwiatBizuterii24.pl na mapie" loading="lazy" allowfullscreen></iframe></div>' )
		. $html( $blok( 'box_custom51' ) );

	$id = sb24t_zapisz( 'glowna', [ 'shoper_id' => 0, 'sciezka' => '/', 'seo' => $g['seo'] ], [
		'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $g['h1'], 'post_name' => 'strona-glowna',
		'post_content' => $tresc,
	] );
	delete_post_meta( $id, '_sb24_sciezka' ); // „/” obsługuje WordPress (strona główna), nie tabela adresów
	// Kadence: bez tytułu motywu (H1 jest w treści, nad produktami jak w Shoperze), pełna szerokość, bez ramki.
	foreach ( [ '_kad_post_title' => 'hide', '_kad_post_layout' => 'fullwidth', '_kad_post_content_style' => 'unboxed', '_kad_post_vertical_padding' => 'hide' ] as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $id );
	WP_CLI::success( 'Strona główna: H1 „' . $g['h1'] . '”, wyróżnione produkty: ' . count( $ids ) . ', bloki: ' . count( $g['bloki'] ) );
}

/**
 * Banery kategorii (oryginały PNG: dane/eksport/banery-kategorii/<slug>.png, generuje skrypty/zdjecia/banery-kategorii.mjs;
 * do sklepu idą WebP bez metadanych z dane/eksport/zdjecia-webp/, robi je skrypty/zdjecia/do-webp.py)
 * → biblioteka mediów + term meta _sb24_baner. Podmieniony plik = nowy załącznik.
 */
// na serwerze produkcyjnym WebP-y leżą gdzie indziej: SB24_WEBP=../wdrozenie/zdjecia-webp wp eval-file …
define( 'SB24_WEBP', getenv( 'SB24_WEBP' ) ?: '/import/dane/zdjecia-webp' );

function sb24t_importuj_banery() {
	$n = 0;
	foreach ( glob( SB24_WEBP . '/banery-kategorii/*.webp' ) as $plik ) {
		$term = get_term_by( 'slug', basename( $plik, '.webp' ), 'product_cat' );
		if ( ! $term ) {
			WP_CLI::warning( 'Baner bez kategorii: ' . basename( $plik ) );
			continue;
		}
		$id = sb24t_zaladuj_baner( $plik, 'baner-' . $term->slug, $term->name, (int) get_term_meta( $term->term_id, '_sb24_baner', true ) );
		if ( $id ) {
			update_term_meta( $term->term_id, '_sb24_baner', $id );
			$n++;
		}
	}
	WP_CLI::success( "Banery kategorii: nowe/zmienione $n" );

	// Strony informacyjne i blog: zdjecia-webp/banery-stron/<slug strony>.webp → meta _sb24_baner strony;
	// „_strony.webp” → opcja sb24_baner_stron (wspólny baner pozostałych stron informacyjnych).
	$n = 0;
	foreach ( glob( SB24_WEBP . '/banery-stron/*.webp' ) as $plik ) {
		$slug = basename( $plik, '.webp' );
		if ( '_strony' === $slug ) {
			$id = sb24t_zaladuj_baner( $plik, 'baner-strony', 'Baner stron', (int) get_option( 'sb24_baner_stron' ) );
			if ( $id ) {
				update_option( 'sb24_baner_stron', $id, false );
				$n++;
			}
			continue;
		}
		$strona = get_page_by_path( $slug );
		if ( ! $strona ) {
			WP_CLI::warning( 'Baner bez strony: ' . basename( $plik ) );
			continue;
		}
		$id = sb24t_zaladuj_baner( $plik, 'baner-' . $slug, $strona->post_title, (int) get_post_meta( $strona->ID, '_sb24_baner', true ) );
		if ( $id ) {
			update_post_meta( $strona->ID, '_sb24_baner', $id );
			$n++;
		}
	}
	WP_CLI::success( "Banery stron: nowe/zmienione $n" );
}

/** WebP do biblioteki mediów; 0, gdy plik się nie zmienił (md5) albo błąd. Stary załącznik usuwa. */
function sb24t_zaladuj_baner( $plik, $nazwa, $tytul, $stary ) {
	if ( $stary && md5_file( $plik ) === get_post_meta( $stary, '_sb24_md5', true ) ) {
		return 0;
	}
	$tmp = wp_tempnam( 'baner' );
	copy( $plik, $tmp );
	$id = media_handle_sideload( [ 'name' => $nazwa . '.webp', 'tmp_name' => $tmp ], 0, $tytul );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( basename( $plik ) . ': ' . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, '_sb24_md5', md5_file( $plik ) );
	update_post_meta( $id, '_wp_attachment_image_alt', '' ); // dekoracyjny — H1 jest tekstem
	if ( $stary ) {
		wp_delete_attachment( $stary, true );
	}
	return $id;
}

/**
 * Poprawione zdjęcia produktów (zdjecia-webp/produkty/<shoperId>-<gfxId>.webp, bez metadanych) podmieniane
 * w istniejących załącznikach (po `_sb24_gfx_id`) — ID zostaje, więc produkty i galerie się nie zmieniają;
 * stary plik i jego miniatury znikają z uploads (oryginał zostaje w dane/eksport/). Idempotentne po md5,
 * więc wersja z ESRGAN jest podmieniana na ChatGPT przy kolejnym uruchomieniu (po do-webp.py).
 * Źródło (chatgpt|esrgan) z produkty/_zrodla.json → meta `_sb24_poprawione_zrodlo`.
 */
function sb24t_importuj_zdjecia_webp() {
	global $wpdb;
	$n      = 0;
	$bez    = 0;
	$zrodla = json_decode( (string) @file_get_contents( SB24_WEBP . '/produkty/_zrodla.json' ), true ) ?: [];
	foreach ( glob( SB24_WEBP . '/produkty/*.webp' ) as $plik ) {
		$nazwa     = basename( $plik, '.webp' );
		[ , $gfx ] = explode( '-', $nazwa );
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sb24_gfx_id' AND meta_value = %s LIMIT 1", $gfx
		) );
		if ( ! $id ) {
			$bez++;
			continue;
		}
		$md5 = md5_file( $plik );
		if ( get_post_meta( $id, '_sb24_poprawione', true ) === $md5 ) {
			continue;
		}
		$stary = get_attached_file( $id );
		$meta  = wp_get_attachment_metadata( $id );
		wp_delete_attachment_files( $id, is_array( $meta ) ? $meta : [], get_post_meta( $id, '_wp_attachment_backup_sizes', true ) ?: [], $stary );
		$katalog = dirname( $stary );
		$nowy    = $katalog . '/' . wp_unique_filename( $katalog, preg_replace( '/-scaled$/', '', pathinfo( $stary, PATHINFO_FILENAME ) ) . '.webp' );
		copy( $plik, $nowy );
		update_attached_file( $id, $nowy );
		wp_update_post( [ 'ID' => $id, 'post_mime_type' => 'image/webp' ] );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $nowy ) );
		update_post_meta( $id, '_sb24_poprawione', $md5 );
		update_post_meta( $id, '_sb24_poprawione_zrodlo', $zrodla[ $nazwa ] ?? 'chatgpt' );
		if ( 0 === ++$n % 100 ) {
			WP_CLI::log( "… $n" );
		}
	}
	if ( $bez ) {
		WP_CLI::warning( "Zdjęcia bez załącznika w WordPressie: $bez" );
	}
	WP_CLI::success( "Podmienione zdjęcia produktów (WebP): $n" );
}

/** Opisy kategorii (zaimportowane wcześniej z Shopera) — podmiana adresów obrazków. */
function sb24t_obrazy_w_kategoriach() {
	$n = 0;
	foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
		$nowy = sb24t_podmien_obrazy( $t->description );
		if ( $nowy !== $t->description ) {
			wp_update_term( $t->term_id, 'product_cat', [ 'description' => $nowy ] );
			$n++;
		}
		$dol  = get_term_meta( $t->term_id, '_sb24_opis_dol', true );
		$nowy = sb24t_podmien_obrazy( $dol );
		if ( $nowy !== $dol ) {
			update_term_meta( $t->term_id, '_sb24_opis_dol', $nowy );
			$n++;
		}
	}
	WP_CLI::success( "Opisy kategorii z podmienionymi obrazkami: $n" );
}

/* ------------------------------------------------------------ start */

$tryb = $args[0] ?? 'wszystko';
if ( in_array( $tryb, [ 'obrazy', 'wszystko' ], true ) ) {
	sb24t_importuj_obrazy();
	sb24t_obrazy_w_kategoriach();
}
if ( in_array( $tryb, [ 'strony', 'wszystko' ], true ) ) {
	sb24t_importuj_strony();
}
if ( in_array( $tryb, [ 'blog', 'wszystko' ], true ) ) {
	sb24t_importuj_blog();
}
if ( in_array( $tryb, [ 'glowna', 'wszystko' ], true ) ) {
	sb24t_importuj_glowna();
}
if ( 'banery' === $tryb ) {
	sb24t_importuj_banery();
}
if ( 'zdjecia' === $tryb ) {
	sb24t_importuj_zdjecia_webp();
}
