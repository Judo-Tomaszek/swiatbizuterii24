<?php
/**
 * Plugin Name: SB24 Migracja — stare adresy Shopera
 * Description: Obsługuje adresy z Shopera bez zmian (produkty, kategorie, strony, blog) i generuje linki w starym formacie. Wymaganie nadrzędne migracji: każdy stary adres działa tak samo.
 * Version: 1.0.0
 * Author: swiatbizuterii24
 *
 * Jak działa:
 * 1. Tabela `{prefix}sb24_adresy`: stara ścieżka → obiekt WordPressa. Budowana z meta `_sb24_sciezka`
 *    / `_sb24_shoper_id`, które zostawia import (skrypty/import/importuj-katalog.php):
 *      wp sb24 przebuduj
 * 2. `do_parse_request`: ścieżka z tabeli → zapytanie WordPressa o ten obiekt (bez przekierowania).
 *    Paginacja kategorii w formacie Shopera: `/zloto/2`.
 * 3. Filtry linków (`post_type_link`, `term_link`, paginacja): sklep, menu, sitemapa i canonical
 *    pokazują stare adresy.
 * 4. Siatka bezpieczeństwa: `/pl/p/<cokolwiek>/<id>` (i `/pl/c/`, `/pl/i/`, `/pl/blog/`) → 301 na
 *    aktualny adres obiektu o tym ID w Shoperze — tak samo robi dziś Shoper.
 * Obiekty bez starego adresu (nowe produkty, nowe wpisy bloga) mają zwykłe adresy WordPressa.
 *
 * Dokumentacja: docs/adresy-url-wtyczka.md
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/bezpieczenstwo.php'; // limit prób logowania, historia w panelu, XML-RPC i nazwy użytkowników

const SB24_TYPY_SHOPERA = [ // przedrostek domyślnego adresu Shopera → typ obiektu
	'p'    => 'product',
	'c'    => 'product_cat',
	'i'    => 'page',
	'blog' => 'post',
];

// Nowe, zwykłe adresy zamiast domyślnych adresów Shopera (decyzja właściciela 2026-10-21). Stary adres
// dalej działa: siatka bezpieczeństwa (/pl/i/…/15, /pl/c/…/158) daje 301 na nowy po ID z Shopera.
const SB24_NOWE_ADRESY = [
	'/pl/i/Salon-firmowy/15'          => '/salon-firmowy',
	'/pl/c/Indywidualne-projekty/158' => '/indywidualne-projekty',
	'/pl/i/Kontakt-i-dane-firmy/9'    => '/kontakt',
	'/pl/i/Formy-platnosci/10'        => '/formy-platnosci',
	'/pl/i/Czas-i-koszty-dostawy/4'   => '/czas-i-koszty-dostawy',
	'/pl/i/Jak-i-kiedy-dostarczymy-Twoja-przesylke/5' => '/jak-i-kiedy-dostarczymy-twoja-przesylke',
	'/pl/i/Dlaczego-my/6'             => '/dlaczego-my',
];

function sb24_tabela() {
	global $wpdb;
	return $wpdb->prefix . 'sb24_adresy';
}

/* ------------------------------------------------------------ tabela adresów */

function sb24_utworz_tabele() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . sb24_tabela() . " (
		sciezka varchar(255) NOT NULL,
		typ varchar(20) NOT NULL,
		obiekt_id bigint(20) unsigned NOT NULL,
		shoper_id bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (sciezka),
		KEY obiekt (typ, obiekt_id),
		KEY shoper (typ, shoper_id)
	) {$wpdb->get_charset_collate()};" );
}
register_activation_hook( __FILE__, 'sb24_utworz_tabele' );

/** Przebudowuje tabelę z meta zostawionych przez import. Zwraca liczbę wpisów per typ. */
function sb24_przebuduj_adresy() {
	global $wpdb;
	sb24_utworz_tabele();
	$t = sb24_tabela();
	$wpdb->query( "TRUNCATE $t" );

	$posty = $wpdb->get_results(
		"SELECT p.ID id, p.post_type typ, s.meta_value sciezka, i.meta_value shoper_id
		 FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_sb24_sciezka'
		 LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = '_sb24_shoper_id'
		 WHERE p.post_status = 'publish' AND p.post_type IN ('product','page','post')"
	);
	$termy = $wpdb->get_results(
		"SELECT tt.term_id id, tt.taxonomy typ, s.meta_value sciezka, i.meta_value shoper_id
		 FROM {$wpdb->term_taxonomy} tt
		 JOIN {$wpdb->termmeta} s ON s.term_id = tt.term_id AND s.meta_key = '_sb24_sciezka'
		 LEFT JOIN {$wpdb->termmeta} i ON i.term_id = tt.term_id AND i.meta_key = '_sb24_shoper_id'
		 WHERE tt.taxonomy = 'product_cat'"
	);

	$licznik = [];
	foreach ( array_merge( $posty, $termy ) as $r ) {
		$sciezka = sb24_normalizuj( $r->sciezka );
		if ( '' === $sciezka || '/' === $sciezka ) {
			continue;
		}
		$sciezka = SB24_NOWE_ADRESY[ $sciezka ] ?? $sciezka;
		$wpdb->replace( $t, [
			'sciezka'   => $sciezka,
			'typ'       => $r->typ,
			'obiekt_id' => (int) $r->id,
			'shoper_id' => (int) $r->shoper_id,
		] );
		$licznik[ $r->typ ] = ( $licznik[ $r->typ ] ?? 0 ) + 1;
	}
	wp_cache_delete( 'sb24_po_obiekcie', 'sb24' );
	return $licznik;
}

/** Ścieżka bez domeny, query stringa i końcowego ukośnika; zdekodowana, z wiodącym `/`. */
function sb24_normalizuj( $sciezka ) {
	$sciezka = rawurldecode( (string) wp_parse_url( $sciezka, PHP_URL_PATH ) );
	return '/' . trim( $sciezka, '/' );
}

function sb24_po_sciezce( $sciezka ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . sb24_tabela() . ' WHERE sciezka = %s', $sciezka ) );
}

function sb24_po_shoper_id( $typ, $shoper_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . sb24_tabela() . ' WHERE typ = %s AND shoper_id = %d LIMIT 1', $typ, $shoper_id
	) );
}

/** Mapa „typ:obiekt_id → ścieżka” do generowania linków (jedno zapytanie na żądanie). */
function sb24_sciezka_obiektu( $typ, $id ) {
	static $mapa = null;
	if ( null === $mapa ) {
		$mapa = wp_cache_get( 'sb24_po_obiekcie', 'sb24' );
		if ( false === $mapa ) {
			global $wpdb;
			$mapa = [];
			$tabela = sb24_tabela();
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabela ) ) === $tabela ) {
				foreach ( $wpdb->get_results( "SELECT typ, obiekt_id, sciezka FROM $tabela" ) as $r ) {
					$mapa[ $r->typ . ':' . $r->obiekt_id ] = $r->sciezka;
				}
			}
			wp_cache_set( 'sb24_po_obiekcie', $mapa, 'sb24' );
		}
	}
	return $mapa[ $typ . ':' . $id ] ?? null;
}

/* ------------------------------------------------------------ obsługa wejść */

$GLOBALS['sb24_trafienie'] = false;

add_filter( 'do_parse_request', function ( $parsuj, $wp ) {
	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $parsuj;
	}
	$surowa  = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	$sciezka = sb24_normalizuj( $surowa );
	if ( '/' === $sciezka ) {
		return $parsuj;
	}

	foreach ( SB24_NOWE_ADRESY as $stary => $nowy ) { // także strony listy: /pl/c/Indywidualne-projekty/158/2 → /indywidualne-projekty/2
		if ( 0 === strpos( $sciezka . '/', $stary . '/' ) ) {
			wp_safe_redirect( home_url( $nowy . substr( $sciezka, strlen( $stary ) ) ), 301, 'SB24 Migracja' );
			exit;
		}
	}

	$strona = 0;
	$wpis   = sb24_po_sciezce( $sciezka );
	if ( ! $wpis && preg_match( '#^(.+)/(\d+)$#', $sciezka, $m ) ) { // paginacja Shopera: /zloto/2
		$kandydat = sb24_po_sciezce( $m[1] );
		if ( $kandydat && 'product_cat' === $kandydat->typ ) {
			if ( (int) $m[2] <= 1 ) { // /zloto/1 = /zloto
				wp_safe_redirect( home_url( $kandydat->sciezka ), 301, 'SB24 Migracja' );
				exit;
			}
			$wpis   = $kandydat;
			$strona = (int) $m[2];
		}
	}
	if ( ! $wpis && preg_match( '#^(.+)/page/(\d+)$#', $sciezka, $m ) ) { // lista bloga: /pl/blog/page/2 (jak w Shoperze)
		$kandydat = sb24_po_sciezce( $m[1] );
		if ( $kandydat && sb24_to_lista_bloga( $kandydat ) ) {
			if ( (int) $m[2] <= 1 ) { // /pl/blog/page/1 = /pl/blog
				wp_safe_redirect( home_url( $kandydat->sciezka ), 301, 'SB24 Migracja' );
				exit;
			}
			$wpis   = $kandydat;
			$strona = (int) $m[2];
		}
	}

	if ( ! $wpis ) {
		// Paginacja w formacie WordPressa na kategorii ze starym adresem → 301 na format Shopera.
		$shoperowa = sb24_paginacja_shopera( home_url( $sciezka ) );
		if ( $shoperowa !== home_url( $sciezka ) ) {
			wp_safe_redirect( $shoperowa, 301, 'SB24 Migracja' );
			exit;
		}
		// Siatka bezpieczeństwa: domyślny adres Shopera z ID (/pl/p/<cokolwiek>/<id>) → 301 na aktualny.
		if ( preg_match( '#^/pl/(p|c|i|blog)/[^/]*/(\d+)$#', $sciezka, $m ) ) {
			$cel = sb24_po_shoper_id( SB24_TYPY_SHOPERA[ $m[1] ], (int) $m[2] );
			if ( $cel ) {
				wp_safe_redirect( home_url( $cel->sciezka ), 301, 'SB24 Migracja' );
				exit;
			}
		}
		return $parsuj;
	}

	// Stary adres z ukośnikiem na końcu → 301 na wersję bez (tak jak w Shoperze).
	if ( $surowa !== $sciezka && rtrim( $surowa, '/' ) === $sciezka ) {
		wp_safe_redirect( home_url( $sciezka ), 301, 'SB24 Migracja' );
		exit;
	}

	$zapytanie = sb24_zapytanie( $wpis, $strona );
	if ( $zapytanie ) {
		// ⚠️ NIE zwracamy false: od WP 6.0 `WP::main()` pomija wtedy całe zapytanie i każdy adres
		// pokazuje stronę główną (200, zła treść). Pozwalamy WordPressowi parsować, a w filtrze
		// `request` podmieniamy wynik na nasze zapytanie.
		$GLOBALS['sb24_trafienie'] = $zapytanie;
	}
	return $parsuj;
}, 10, 2 );

add_filter( 'request', fn( $vars ) => $GLOBALS['sb24_trafienie'] ?: $vars, 99 );

function sb24_zapytanie( $wpis, $strona ) {
	switch ( $wpis->typ ) {
		case 'product':
			$post = get_post( $wpis->obiekt_id );
			return $post ? [ 'post_type' => 'product', 'product' => $post->post_name, 'name' => $post->post_name ] : null;
		case 'page':
			return array_filter( [ 'page_id' => (int) $wpis->obiekt_id, 'paged' => $strona > 1 ? $strona : null ] );
		case 'post':
			return [ 'p' => (int) $wpis->obiekt_id ];
		case 'product_cat':
			$term = get_term( (int) $wpis->obiekt_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return null;
			}
			return array_filter( [ 'product_cat' => $term->slug, 'paged' => $strona > 1 ? $strona : null ] );
	}
	return null;
}

// Starego adresu nie poprawiamy na „kanoniczny” WordPressa (np. dopisywanie ukośnika).
add_filter( 'redirect_canonical', fn( $url ) => $GLOBALS['sb24_trafienie'] ? false : $url );

// WordPress przy 404 „zgaduje” stronę o podobnym slugu i daje 301 (np. /pl/reg → Regulamin) — 301 na
// niewłaściwą stronę jest gorszy niż 404, więc wyłączamy.
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

// Systemowe adresy Shopera (konto, koszyk) → odpowiedniki WooCommerce.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$mapa = [ 'reg' => 'myaccount', 'login' => 'myaccount', 'panel' => 'myaccount', 'basket' => 'cart' ];
	$sciezka = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( preg_match( '#^pl/(reg|login|panel|basket)(/|$)#', $sciezka, $m ) && wc_get_page_id( $mapa[ $m[1] ] ) > 0 ) {
		wp_safe_redirect( get_permalink( wc_get_page_id( $mapa[ $m[1] ] ) ), 301 );
		exit;
	}
	// Kategorie i tagi bloga Shopera (/pl/blog/category/<Nazwa>/<id>, niespójne nazwy) nie są przenoszone —
	// blog ma strukturę WordPressa (decyzja 2026-10-17), stare adresy → lista wpisów.
	if ( preg_match( '#^pl/blog/(category|tag)/#', $sciezka ) && get_option( 'page_for_posts' ) ) {
		wp_safe_redirect( get_permalink( get_option( 'page_for_posts' ) ), 301 );
		exit;
	}
} );

/* ------------------------------------------------------------ generowanie linków */

add_filter( 'post_type_link', function ( $link, $post ) {
	$s = sb24_sciezka_obiektu( $post->post_type, $post->ID );
	return $s ? home_url( $s ) : $link;
}, 20, 2 );

add_filter( 'page_link', function ( $link, $id ) {
	$s = sb24_sciezka_obiektu( 'page', $id );
	return $s ? home_url( $s ) : $link;
}, 20, 2 );

add_filter( 'post_link', function ( $link, $post ) {
	$s = sb24_sciezka_obiektu( 'post', $post->ID );
	return $s ? home_url( $s ) : $link;
}, 20, 2 );

add_filter( 'term_link', function ( $link, $term, $taksonomia ) {
	$s = sb24_sciezka_obiektu( $taksonomia, $term->term_id );
	return $s ? home_url( $s ) : $link;
}, 20, 3 );

/**
 * Paginacja kategorii w formacie Shopera (lista bloga: `/pl/blog/page/2/` → `/pl/blog/page/2`): `/zloto/page/2/` → `/zloto/2` (strona 1 bez numeru).
 * Linki stron budują różne mechanizmy (get_pagenum_link, paginate_links, blok Product Collection
 * WooCommerce składa je sam) — dlatego przepisujemy każdy URL, którego ścieżka jest starą ścieżką
 * kategorii + `/page/N`, niezależnie od źródła. Inne adresy zostają bez zmian.
 */
function sb24_paginacja_shopera( $url ) {
	$sciezka = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( ! preg_match( '#^(.+?)/page/(\d+)/?$#', rawurldecode( $sciezka ), $m ) ) {
		return $url;
	}
	$kat = sb24_po_sciezce( sb24_normalizuj( $m[1] ) );
	if ( ! $kat && preg_match( '#^(.+)/\d+$#', $m[1], $baza ) ) {
		// linki budowane na stronie /kategoria/3 dostają bazę „/kategoria/3” — odcinamy numer strony Shopera
		// (najpierw cała ścieżka: /pl/c/Indywidualne-projekty/158 sama kończy się liczbą)
		$kat = sb24_po_sciezce( sb24_normalizuj( $baza[1] ) );
	}
	if ( ! $kat || ( 'product_cat' !== $kat->typ && ! sb24_to_lista_bloga( $kat ) ) ) {
		return $url;
	}
	// Kategoria: /zloto/2; lista bloga: /pl/blog/page/2 — oba bez ukośnika na końcu, jak w Shoperze.
	$numer     = (int) $m[2] > 1 ? ( 'product_cat' === $kat->typ ? '/' : '/page/' ) . (int) $m[2] : '';
	$zapytanie = wp_parse_url( $url, PHP_URL_QUERY );
	return home_url( $kat->sciezka . $numer ) . ( $zapytanie ? '?' . $zapytanie : '' );
}

/** Czy wpis tabeli adresów to strona z listą wpisów bloga (/pl/blog). */
function sb24_to_lista_bloga( $wpis ) {
	return 'page' === $wpis->typ && (int) $wpis->obiekt_id === (int) get_option( 'page_for_posts' );
}
add_filter( 'get_pagenum_link', 'sb24_paginacja_shopera', 20 );
add_filter( 'paginate_links', 'sb24_paginacja_shopera', 20 );

// Link do strony 1: WooCommerce składa go z bazy „/zloto/%#%” → „/zloto/1”, a blog daje „/pl/blog/” —
// prowadzimy wprost na adres kategorii / bloga (bez numeru i bez ukośnika).
add_filter( 'paginate_links', function ( $url ) {
	$sciezka = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( preg_match( '#^(.+)/1$#', $sciezka, $m ) && ! sb24_po_sciezce( sb24_normalizuj( $sciezka ) ) ) {
		$kat = sb24_po_sciezce( sb24_normalizuj( $m[1] ) );
		if ( $kat && 'product_cat' === $kat->typ ) {
			$zapytanie = wp_parse_url( $url, PHP_URL_QUERY );
			return home_url( $kat->sciezka ) . ( $zapytanie ? '?' . $zapytanie : '' );
		}
	}
	return sb24_yoast_adres( $url );
}, 25 );

// Blok Product Collection nie przepuszcza linków przez filtry — poprawiamy wyrenderowany HTML bloku.
add_filter( 'render_block_woocommerce/product-collection', function ( $html ) {
	return preg_replace_callback(
		'#href="([^"]*/page/\d+/?[^"]*)"#',
		fn( $m ) => 'href="' . esc_url( sb24_paginacja_shopera( html_entity_decode( $m[1] ) ) ) . '"',
		$html
	);
}, 20 );

/* ------------------------------------------------------------ Yoast SEO: adresy bez ukośnika */

/**
 * Yoast zapisuje adres obiektu (indexable) z ukośnikiem na końcu, jeśli struktura linków WordPressa
 * go ma (`/blog/%postname%/`) — niezależnie od filtrów linków powyżej. Canonical wskazywałby wtedy
 * `/naprawa-bizuterii-opole/`, który daje 301 na wersję bez ukośnika. Stare adresy Shopera wracają
 * do postaci bez ukośnika (canonical, og:url, schema, mapa witryny, rel next/prev); paginacja kategorii
 * dostaje format Shopera (`/zloto/2`). Ustawienia Yoasta: skrypty/import/ustaw-seo.php.
 */
function sb24_yoast_adres( $url ) {
	if ( ! is_string( $url ) || '' === $url || 0 !== strpos( $url, home_url() ) ) {
		return $url;
	}
	$url     = sb24_paginacja_shopera( $url );
	$czesci  = wp_parse_url( $url );
	$sciezka = $czesci['path'] ?? '/';
	if ( '/' === $sciezka || '/' !== substr( $sciezka, -1 ) || ! sb24_po_sciezce( sb24_normalizuj( $sciezka ) ) ) {
		return $url;
	}
	return home_url( rtrim( $sciezka, '/' ) )
		. ( isset( $czesci['query'] ) ? '?' . $czesci['query'] : '' )
		. ( isset( $czesci['fragment'] ) ? '#' . $czesci['fragment'] : '' );
}
foreach ( [ 'wpseo_canonical', 'wpseo_opengraph_url', 'wpseo_adjacent_rel_url', 'wpseo_xml_sitemap_post_url' ] as $filtr ) {
	add_filter( $filtr, 'sb24_yoast_adres', 20 );
}
add_filter( 'wpseo_sitemap_entry', function ( $url ) {
	if ( isset( $url['loc'] ) ) {
		$url['loc'] = sb24_yoast_adres( $url['loc'] );
	}
	return $url;
}, 20 );
add_filter( 'wpseo_schema_graph', function ( $graf ) {
	// Każdy napis: adresy siedzą też w listach (np. potentialAction.target); zmienia się tylko stary adres.
	array_walk_recursive( $graf, function ( &$wartosc ) {
		$wartosc = sb24_yoast_adres( $wartosc );
	} );
	return $graf;
}, 20 );

/* ------------------------------------------------------------ treść 1:1 */

// WordPress zamienia "cudzysłowy" na „drukarskie”, -- na – itd. (wptexturize). Teksty z Shopera
// mają zostać dokładnie takie jak były (H1 produktu `"I love U"` wychodził jako `„I love U”`).
add_filter( 'run_wptexturize', '__return_false' );
// …i zamienia emotikony `:)` na obrazki emoji (teksty Shopera mają ich sporo).
add_filter( 'pre_option_use_smilies', fn() => '0' );

/* ------------------------------------------------------------ drugi opis kategorii */

/**
 * Shoper ma dwa teksty kategorii: nad produktami (= opis kategorii WooCommerce) i POD produktami
 * (`_sb24_opis_dol` z importu). Kluczowe kategorie mają pod produktami po kilka tysięcy znaków tekstu
 * SEO, więc wyświetlamy go niezależnie od motywu: w motywie klasycznym na haku WooCommerce,
 * w blokowym — po bloku listy produktów. Raz na stronę, tylko na pierwszej stronie listy — jak w Shoperze
 * (dalsze strony bez obu opisów, żeby nie powielać treści).
 */
function sb24_opis_dol_html() {
	static $wypisany = false;
	if ( $wypisany || ! is_product_category() || get_query_var( 'paged' ) > 1 ) {
		return '';
	}
	$opis = get_term_meta( get_queried_object_id(), '_sb24_opis_dol', true );
	if ( ! $opis ) {
		return '';
	}
	$wypisany = true;
	return '<div class="sb24-opis-dol term-description">' . sb24_faq_bez_akapitow( wpautop( sb24_kses_opis( $opis ) ) ) . '</div>';
}
add_action( 'woocommerce_after_shop_loop', fn() => print( sb24_opis_dol_html() ), 100 );
add_action( 'woocommerce_no_products_found', fn() => print( sb24_opis_dol_html() ), 100 );
add_filter( 'render_block_woocommerce/product-collection', function ( $html, $blok ) {
	return ( $blok['attrs']['query']['inherit'] ?? false ) ? $html . sb24_opis_dol_html() : $html;
}, 30, 2 );

/* ------------------------------------------------------------ cena ustalana indywidualnie */

/**
 * 819 produktów (wszystkie obrączki i pierścionki zaręczynowe) ma w Shoperze cenę 0 — „Cena ustalana
 * jest indywidualnie w zależności od rozmiaru” w opisie, a Shoper pokazuje „0,00 zł” + „zapytaj
 * o produkt”. Zamiast „0,00 zł” pokazujemy informację z linkiem do kontaktu, a produktu z ceną 0 nie
 * da się kupić (2 z nich są na stanie — inaczej szłyby do koszyka za 0 zł).
 */
function sb24_cena_indywidualna( $produkt ) {
	return $produkt instanceof WC_Product && ! $produkt->is_type( 'variable' ) && '' !== $produkt->get_price() && 0.0 === (float) $produkt->get_price();
}
add_filter( 'woocommerce_get_price_html', function ( $html, $produkt ) {
	if ( ! sb24_cena_indywidualna( $produkt ) ) {
		return $html;
	}
	$kontakt = home_url( '/kontakt' );
	return '<span class="sb24-cena-indywidualna">Cena ustalana indywidualnie</span>'
		. ( is_product() ? ' <a class="sb24-zapytaj" href="' . esc_url( $kontakt ) . '">Zapytaj o cenę</a>' : '' );
}, 20, 2 );
add_filter( 'woocommerce_is_purchasable', fn( $mozna, $produkt ) => sb24_cena_indywidualna( $produkt ) ? false : $mozna, 20, 2 );

/* ------------------------------------------------------------ zdjęcia produktów: ostrość */

/**
 * Zdjęcia z Shopera są małe i mocno skompresowane: 408 z 607 zdjęć głównych aktywnych produktów
 * na stanie ma ~415 px, a 900×900 waży ~30 KB (pliki bajt w bajt jak na serwerze Shopera).
 * Motyw rozciągał je do szerokości galerii (600 px, na ekranach retina 1200 px) → rozmazane.
 * 1) Zdjęcie w galerii produktu nie jest powiększane ponad naturalny rozmiar (małe zostaje małe, ostre).
 * 2) Kopie robione przez WordPress w jakości 92 zamiast 82 — źródło jest już skompresowane.
 */
add_filter( 'wp_editor_set_quality', fn() => 92 );
add_action( 'wp_head', function () {
	if ( ! is_product() ) {
		return;
	}
	echo '<style id="sb24-zdjecia">'
		. '.woocommerce-product-gallery__image img,.woocommerce-product-gallery__image a img{width:auto!important;max-width:100%;height:auto;margin:0 auto;display:block}'
		. '.woocommerce-product-gallery__image{text-align:center;background:#fff}'
		. '</style>';
} );

/* ------------------------------------------------------------ blog bez dat */

// Shoper nie miał dat wpisów (API 0000-00-00, strona ich nie pokazywała) — import nadał umowne
// daty tylko po to, żeby zachować kolejność. Nie pokazujemy ich czytelnikom, w żadnym motywie.
function sb24_brak_daty( $post = null ) {
	$post = get_post( $post );
	return $post && 'post' === $post->post_type && get_post_meta( $post->ID, '_sb24_brak_daty', true );
}
add_filter( 'get_the_date', fn( $data, $format, $post ) => sb24_brak_daty( $post ) && ! is_admin() ? '' : $data, 20, 3 );
add_filter( 'get_the_time', fn( $czas, $format, $post ) => sb24_brak_daty( $post ) && ! is_admin() ? '' : $czas, 20, 3 );
add_filter( 'post_class', fn( $klasy, $c, $id ) => sb24_brak_daty( $id ) ? array_merge( $klasy, [ 'sb24-brak-daty' ] ) : $klasy, 10, 3 );
add_action( 'wp_head', function () {
	// Puste znaczniki daty, które motywy i tak renderują (Kadence: .posted-on, Blocksy: .meta-date).
	echo '<style id="sb24-brak-daty">.sb24-brak-daty .posted-on,.sb24-brak-daty .meta-date,.sb24-brak-daty .entry-date,.sb24-brak-daty time,.sb24-brak-daty .wp-block-post-date{display:none!important}</style>';
} );

/* ------------------------------------------------------------ podgląd motywu (tylko lokalnie) */

/**
 * Porównanie motywów bez przełączania w panelu: `?motyw=kadence` / `?motyw=blocksy` przełącza
 * motyw dla tej przeglądarki (ciasteczko), `?motyw=` wraca do aktywnego. Tylko WP_ENVIRONMENT_TYPE=local.
 */
if ( 'local' === wp_get_environment_type() ) {
	add_action( 'setup_theme', function () {
		$motyw = isset( $_GET['motyw'] ) ? sanitize_key( $_GET['motyw'] ) : ( isset( $_COOKIE['sb24_motyw'] ) ? sanitize_key( $_COOKIE['sb24_motyw'] ) : '' );
		if ( isset( $_GET['motyw'] ) ) {
			setcookie( 'sb24_motyw', $motyw, $motyw ? time() + DAY_IN_SECONDS : time() - 3600, COOKIEPATH ?: '/' );
		}
		if ( $motyw && wp_get_theme( $motyw )->exists() ) {
			$szablon = wp_get_theme( $motyw )->get_template();
			add_filter( 'stylesheet', fn() => $motyw );
			add_filter( 'template', fn() => $szablon );
			// get_theme_mods() czyta get_option('stylesheet'), nie filtr — bez tego motyw z podglądu
			// dostaje ustawienia (menu, kolory, nagłówek) aktywnego motywu.
			add_filter( 'pre_option_stylesheet', fn() => $motyw );
			add_filter( 'pre_option_template', fn() => $szablon );
		}
	}, 1 );
}

/* ------------------------------------------------------------ brakujące tłumaczenia motywu */

// Blocksy nie ma polskiego tłumaczenia (tylko blocksy.pot) — teksty widoczne dla klienta
// na liście produktów i w menu. (Kadence ma pełne pl_PL — nic nie trzeba.)
add_filter( 'gettext_blocksy', function ( $tekst, $oryginal ) {
	static $pl = [
		'SOLD OUT'     => 'BRAK TOWARU', // jak „brak towaru” w Shoperze
		'Sold out'     => 'Brak towaru',
		'Out of stock' => 'Brak w magazynie',
		'More'         => 'Więcej',
		'Search'       => 'Szukaj',
		'Read More'    => 'Czytaj więcej',
		'Menu'         => 'Menu',
		'Close'        => 'Zamknij',
	];
	return $pl[ $oryginal ] ?? $tekst;
}, 10, 2 );

/* ------------------------------------------------------------ konta klientów: ochrona rejestracji */

/**
 * Formularz rejestracji na „Moje konto” bez CAPTCHA (żadnej zewnętrznej usługi ani dodatkowych
 * cookies): ukryte pole, którego człowiek nie widzi (boty wypełniają wszystko), i minimalny czas
 * od wyświetlenia formularza do wysłania. Konto przy zamówieniu wymaga złożenia całego zamówienia,
 * więc boty go nie zakładają. Ustawienia kont: skrypty/import/ustaw-konta.php.
 */
const SB24_REJESTRACJA_MIN_SEKUND = 3;

add_action( 'woocommerce_register_form', function () {
	$czas = time();
	printf(
		'<p class="sb24-pulapka" aria-hidden="true" style="position:absolute!important;left:-9999px!important"><label>Strona www<input type="text" name="sb24_www" value="" tabindex="-1" autocomplete="off"></label></p>'
		. '<input type="hidden" name="sb24_czas" value="%d:%s">',
		$czas,
		esc_attr( wp_hash( 'sb24_rejestracja' . $czas ) )
	);
} );

add_filter( 'woocommerce_process_registration_errors', function ( $bledy ) {
	[ $czas, $podpis ] = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_POST['sb24_czas'] ?? '' ) ), 2 ), 2, '' );
	$bot = '' !== (string) ( $_POST['sb24_www'] ?? '' )
		|| ! hash_equals( wp_hash( 'sb24_rejestracja' . (int) $czas ), $podpis )
		|| time() - (int) $czas < SB24_REJESTRACJA_MIN_SEKUND;
	if ( $bot ) {
		$bledy->add( 'sb24_bot', 'Nie udało się założyć konta. Odśwież stronę i spróbuj ponownie.' );
	}
	return $bledy;
} );

/* ------------------------------------------------------------ WP-CLI */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'sb24 przebuduj', function () {
		$n = sb24_przebuduj_adresy();
		WP_CLI::success( 'Tabela adresów: ' . wp_json_encode( $n ) );
	} );
}

/* ------------------------------------------------------------ strona główna */

// Układ strony głównej jak w Shoperze (zdjęcie, pasek zalet, kafle, banery, mapa) — style niezależne od motywu.
add_action( 'wp_enqueue_scripts', function () {
	if ( is_front_page() ) {
		wp_enqueue_style( 'sb24-glowna', plugins_url( 'glowna.css', __FILE__ ), [], filemtime( __DIR__ . '/glowna.css' ) );
	}
} );

/* ------------------------------------------------------------ FAQ i strony kategorii */

// Harmonijka FAQ i klasy treści ze skórki Shopera (siatka f-row, naprawa, skup złota…) — w treściach stron,
// wpisów i opisach kategorii.
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'sb24-faq', plugins_url( 'faq.css', __FILE__ ), [], filemtime( __DIR__ . '/faq.css' ) );
	wp_enqueue_style( 'sb24-tresci', plugins_url( 'tresci.css', __FILE__ ), [], filemtime( __DIR__ . '/tresci.css' ) );
	$baner = sb24_baner();
	if ( $baner ) {
		wp_enqueue_style( 'sb24-baner', plugins_url( 'baner.css', __FILE__ ), [], filemtime( __DIR__ . '/baner.css' ) );
		wp_add_inline_style( 'sb24-baner', ':root{--sb24-baner:url("' . esc_url( $baner ) . '")}' );
	}
	if ( ! is_product_taxonomy() && ! is_shop() ) {
		return;
	}
	wp_enqueue_style( 'sb24-kategorie', plugins_url( 'kategorie.css', __FILE__ ), [], filemtime( __DIR__ . '/kategorie.css' ) );
	wp_enqueue_script( 'sb24-kategorie', plugins_url( 'kategorie.js', __FILE__ ), [], filemtime( __DIR__ . '/kategorie.js' ), true );
} );

/**
 * Adres banera w nagłówku bieżącej strony (import: importuj-tresci.php banery):
 * kategoria — term meta _sb24_baner; strona — meta _sb24_baner, a strona informacyjna z Shopera bez własnego —
 * opcja sb24_baner_stron; lista bloga, kategorie i tagi wpisów — baner strony bloga (page_for_posts).
 */
function sb24_baner() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}
	$id = 0;
	if ( is_product_category() ) {
		$id = (int) get_term_meta( get_queried_object_id(), '_sb24_baner', true );
	} elseif ( is_home() || is_category() || is_tag() ) {
		$id = (int) get_post_meta( (int) get_option( 'page_for_posts' ), '_sb24_baner', true );
	} elseif ( is_search() || is_shop() ) { // wyniki wyszukiwania i /sklep — wspólny baner stron
		$id = (int) get_option( 'sb24_baner_stron' );
	} elseif ( is_page() && ! is_front_page() ) {
		$strona = get_queried_object_id();
		$id     = (int) get_post_meta( $strona, '_sb24_baner', true );
		// wspólny baner: strony informacyjne z Shopera i strony WooCommerce (koszyk, zamówienie, moje konto)
		if ( ! $id && ( 'strona' === get_post_meta( $strona, '_sb24_typ', true ) || is_cart() || is_checkout() || is_account_page() ) ) {
			$id = (int) get_option( 'sb24_baner_stron' );
		}
	}
	$url = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
	return $url;
}

add_filter( 'body_class', fn( $klasy ) => sb24_baner() ? [ ...$klasy, 'sb24-z-banerem' ] : $klasy );

// Górny opis kategorii nad produktami (w kolumnie obok filtrów, jak w Shoperze), zwijany przez kategorie.js.
// Motyw nie pokazuje go w nagłówku (Kadence: product_archive_title_element_description wyłączony).
remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description' );
add_action( 'woocommerce_before_shop_loop', function () {
	if ( ! is_product_category() || get_query_var( 'paged' ) > 1 ) {
		return;
	}
	$opis = term_description();
	if ( $opis ) {
		echo '<div class="sb24-opis-gora term-description">' . sb24_faq_bez_akapitow( wc_format_content( sb24_kses_opis( $opis ) ) ) . '</div>';
	}
}, 5 );

/**
 * Jak wp_kses_post, ale przepuszcza ukryte pola harmonijki FAQ z Shopera (<input type="radio">) —
 * bez nich pytania się nie otwierają. Opisy kategorii pochodzą z Shopera i od administratora.
 */
function sb24_kses_opis( $html ) {
	$dozwolone          = wp_kses_allowed_html( 'post' );
	$dozwolone['input'] = [ 'type' => true, 'id' => true, 'name' => true ];
	return wp_kses( $html, $dozwolone );
}

/** wpautop owija pola i etykiety harmonijki w <p> — wtedy `input:checked + .box-faq` nie działa. Odwijamy. */
function sb24_faq_bez_akapitow( $html ) {
	return preg_replace( '#<p>\s*(<input[^>]*>|<label class="box-close-faq"[^>]*></label>)\s*</p>#', '$1', $html );
}

/* ------------------------------------------------------------ blog */

// Karty wpisów i pasek boczny bloga (lista, wpis, tagi, wyszukiwanie).
add_action( 'wp_enqueue_scripts', function () {
	if ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_search() ) {
		wp_enqueue_style( 'sb24-blog', plugins_url( 'blog.css', __FILE__ ), [], filemtime( __DIR__ . '/blog.css' ) );
	}
} );

/* ------------------------------------------------------------ zgody na cookies, Google Tag Manager */

// Kontener GTM właściciela sklepu (ten sam co w Shoperze; GTM-T68LWS w Shoperze to kontener dostawcy platformy — pomijamy).
// Ładowany tylko na docelowej domenie — lokalne testy nie trafiają do statystyk.
const SB24_GTM = 'GTM-PQPTQ7M';

function sb24_produkcja() {
	return in_array( wp_parse_url( home_url(), PHP_URL_HOST ), [ 'swiatbizuterii24.pl', 'www.swiatbizuterii24.pl' ], true );
}

// Consent Mode v2: domyślnie wszystko „denied” (poza niezbędnymi), zanim wczyta się GTM; zapisany wybór od razu przywracany.
add_action( 'wp_head', function () {
	echo '<meta name="google-site-verification" content="WyUDYTo8F_v4SvU2kXt9PfhqgkP-h35uzIb9NACbOk4" />' . "\n"; // Search Console, z Shopera
	?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied', analytics_storage:'denied', functionality_storage:'denied', personalization_storage:'denied', security_storage:'granted', wait_for_update:500});
(function(){var m=document.cookie.match(/(?:^|; )sb24_zgody=([^;]*)/);if(!m)return;try{var z=JSON.parse(decodeURIComponent(m[1])),t=function(b){return b?'granted':'denied';};
gtag('consent','update',{functionality_storage:t(z.funkcjonalne),personalization_storage:t(z.funkcjonalne),analytics_storage:t(z.analityczne),ad_storage:t(z.marketingowe),ad_user_data:t(z.marketingowe),ad_personalization:t(z.marketingowe)});}catch(e){}})();
</script>
	<?php
	if ( sb24_produkcja() ) {
		printf( "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n", esc_js( SB24_GTM ) );
	}
}, 1 );

add_action( 'wp_body_open', function () {
	if ( sb24_produkcja() ) {
		printf( '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>', esc_attr( SB24_GTM ) );
	}
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'sb24-zgody', plugins_url( 'zgody.css', __FILE__ ), [], filemtime( __DIR__ . '/zgody.css' ) );
	wp_enqueue_script( 'sb24-zgody', plugins_url( 'zgody.js', __FILE__ ), [], filemtime( __DIR__ . '/zgody.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	wp_localize_script( 'sb24-zgody', 'sb24Zgody', [ 'polityka' => get_privacy_policy_url() ] );
} );

/* ------------------------------------------------------------ dostawy */

// DHL Kurier jak w Shoperze: od progu (skrypty/import/ustaw-dostawy.php) zostaje tylko darmowa wersja, bez płatnej obok.
add_filter( 'woocommerce_package_rates', function ( $stawki ) {
	foreach ( $stawki as $stawka ) {
		if ( 'free_shipping' === $stawka->get_method_id() ) {
			return array_filter( $stawki, fn( $s ) => 'flat_rate' !== $s->get_method_id() );
		}
	}
	return $stawki;
} );

/* ------------------------------------------------------------ kafelki produktów i wyszukiwarka w nagłówku */

// Kafelek produktu bez przycisku „Dodaj do koszyka / Pokaż więcej” — cały kafelek prowadzi na stronę produktu
// (rozciągnięty link tytułu, CSS w skrypty/import/ustaw-wyglad.php), po najechaniu powiększa się tylko zdjęcie.
add_action( 'init', function () {
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
} );

// Kadence: w nagłówku pole wyszukiwania produktów zamiast przycisku otwierającego wyszukiwarkę na całą stronę.
add_action( 'after_setup_theme', function () {
	if ( ! remove_action( 'kadence_header_search', 'Kadence\header_search' ) ) {
		return;
	}
	add_action( 'kadence_header_search', function () {
		?>
		<form role="search" method="get" class="sb24-szukaj" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="sb24-szukaj">Szukaj produktów</label>
			<input type="search" id="sb24-szukaj" name="s" placeholder="Szukaj biżuterii…" value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
			<button type="submit" aria-label="Szukaj"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
		</form>
		<?php
	} );
}, 20 );

/* ------------------------------------------------------------ zakupy online wyłączone */

/**
 * Na razie sklep nie sprzedaje przez internet (decyzja właściciela 2026-10-21): produktów nie da się dodać
 * do koszyka, zamiast przycisku jest zaproszenie do salonu z telefonem, koszyk znika z nagłówka,
 * a strony koszyka i kasy prowadzą do salonu. Włączenie sprzedaży:
 *   skrypty/wp.sh option update sb24_zakupy_online tak
 */
const SB24_TELEFON             = '+48 535 263 900';
const SB24_TELEFON_STACJONARNY = '+48 77 458 10 46';

function sb24_zakupy_wylaczone() {
	return 'tak' !== get_option( 'sb24_zakupy_online', 'nie' );
}

add_filter( 'woocommerce_is_purchasable', fn( $mozna ) => sb24_zakupy_wylaczone() ? false : $mozna, 30 );
add_filter( 'woocommerce_variation_is_purchasable', fn( $mozna ) => sb24_zakupy_wylaczone() ? false : $mozna, 30 );

add_action( 'woocommerce_single_product_summary', function () {
	if ( ! sb24_zakupy_wylaczone() ) {
		return;
	}
	$tel = preg_replace( '/[^+\d]/', '', SB24_TELEFON );
	?>
	<div class="sb24-kup-w-salonie">
		<p class="sb24-kup-tytul">Ten produkt kupisz w naszym salonie w Opolu</p>
		<p>Zadzwoń — sprawdzimy dostępność, rozmiar i odłożymy biżuterię dla Ciebie.</p>
		<a class="sb24-kup-zadzwon" href="tel:<?php echo esc_attr( $tel ); ?>">Zadzwoń: <?php echo esc_html( SB24_TELEFON ); ?></a>
		<p class="sb24-kup-tel2">lub stacjonarnie: <a href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', SB24_TELEFON_STACJONARNY ) ); ?>"><?php echo esc_html( SB24_TELEFON_STACJONARNY ); ?></a></p>
		<p class="sb24-kup-tel2">Wolisz napisać? <a href="<?php echo esc_url( add_query_arg( 'produkt', get_the_ID(), home_url( '/kontakt' ) ) . '#sb24-formularz' ); ?>">Zapytaj o ten produkt</a></p>
		<p class="sb24-kup-adres">ul. Ozimska 17A, Opole · pon.–pt. 10–18, sob. 10–14 · <a href="<?php echo esc_url( home_url( '/salon-firmowy' ) ); ?>">Jak do nas trafić</a></p>
	</div>
	<?php
}, 31 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ( sb24_zakupy_wylaczone() && is_product() ) || is_page( SB24_STRONY_SALONU ) ) {
		wp_enqueue_style( 'sb24-salon', plugins_url( 'salon.css', __FILE__ ), [], filemtime( __DIR__ . '/salon.css' ) );
	}
} );

/** Strony z treścią w skrypty/import/tresci-poprawione/ (klasy sb24-salon-*) — ładują salon.css. */
const SB24_STRONY_SALONU = [ 'salon-firmowy', 'kontakt-i-dane-firmy', 'formy-platnosci', 'czas-i-koszty-dostawy', 'jak-i-kiedy-dostarczymy-twoja-przesylke', 'dlaczego-my' ];

/** [sb24_zakupy_info] — na stronach płatności i dostawy: dopóki zakupy online są wyłączone, mówi, jak kupić. */
add_shortcode( 'sb24_zakupy_info', function () {
	if ( ! sb24_zakupy_wylaczone() ) {
		return '';
	}
	$tel = fn( $nr ) => '<a href="tel:' . esc_attr( preg_replace( '/[^+\d]/', '', $nr ) ) . '">' . esc_html( $nr ) . '</a>';
	return '<div class="sb24-salon-uwaga"><strong>Teraz zamówienia przyjmujemy w salonie i telefonicznie.</strong> '
		. 'Zadzwoń: ' . $tel( SB24_TELEFON ) . ' lub ' . $tel( SB24_TELEFON_STACJONARNY ) . ' — sprawdzimy dostępność, '
		. 'odłożymy biżuterię dla Ciebie albo wyślemy ją kurierem. Zasady płatności i dostawy poniżej obowiązują także wtedy.</div>';
} );

// Kadence: bez koszyka w nagłówku (komputer i telefon).
foreach ( [ 'header_desktop_items', 'header_mobile_items' ] as $mod ) {
	add_filter( "theme_mod_$mod", function ( $uklad ) {
		if ( ! sb24_zakupy_wylaczone() || ! is_array( $uklad ) ) {
			return $uklad;
		}
		foreach ( $uklad as &$rzad ) {
			foreach ( $rzad as &$miejsce ) {
				if ( is_array( $miejsce ) ) {
					$miejsce = array_values( array_diff( $miejsce, [ 'cart', 'mobile-cart' ] ) );
				}
			}
		}
		return $uklad;
	} );
}

add_action( 'template_redirect', function () {
	if ( sb24_zakupy_wylaczone() && ( is_cart() || ( is_checkout() && ! is_wc_endpoint_url() ) ) ) {
		wp_safe_redirect( home_url( '/salon-firmowy' ), 302, 'SB24 zakupy wyłączone' );
		exit;
	}
} );

/* ------------------------------------------------------------ jeden nagłówek H1 */

/**
 * Motyw wypisuje tytuł strony / wpisu / produktu jako H1, a część treści z Shopera ma własny <h1>.
 * H1 w treści o tym samym tekście co tytuł usuwamy (powtórzenie), inny zamieniamy na <h2> (tekst zostaje).
 * „Naprawa biżuterii” ma tytuł „Naprawa biżuterii Opole” = H1 z Shopera, więc jedyny H1 jest 1:1 z Shoperem.
 */
add_filter( 'the_content', function ( $html ) {
	if ( is_admin() || ! is_singular() || false === stripos( $html, '<h1' ) ) {
		return $html;
	}
	if ( is_front_page() || 'hide' === get_post_meta( get_queried_object_id(), '_kad_post_title', true ) ) {
		return $html; // tytuł ukryty (strona główna) — H1 z treści jest jedynym H1
	}
	$norm  = fn( $t ) => mb_strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES ) ) ) );
	$tytul = $norm( get_the_title( get_queried_object_id() ) );
	return preg_replace_callback( '#<h1(\s[^>]*)?>(.*?)</h1>#is', function ( $m ) use ( $norm, $tytul ) {
		return $norm( $m[2] ) === $tytul ? '' : '<h2' . ( $m[1] ?? '' ) . '>' . $m[2] . '</h2>';
	}, $html );
}, 20 );

/* ------------------------------------------------------------ formularz kontaktowy */

/**
 * [sb24_formularz] — na stronach „Kontakt” i „Salon firmowy” (treści: skrypty/import/tresci-poprawione/).
 * Wiadomość idzie e-mailem na adresy z opcji `sb24_email_kontakt` (po przecinku; skrzynka sklepu i właściciela,
 * ustawiane w bazie, nie w repozytorium; brak → admin_email), z Reply-To nadawcy — „Odpowiedz” trafia do klienta.
 * Ochrona: pole-pułapka, podpisany czas (min. 3 s, jak rejestracja), limit 5 wiadomości na godzinę z jednego IP.
 * Nic nie zapisujemy w bazie — dane osobowe tylko w wysłanym e-mailu.
 * ?produkt=<ID> (link ze strony produktu) wpisuje pytanie o ten produkt.
 */
const SB24_FORMULARZ_TEMATY = [ 'Pytanie o produkt', 'Naprawa biżuterii', 'Skup złota', 'Projekt indywidualny', 'Inne' ];

function sb24_formularz_adres() {
	$adresy = array_filter( array_map( 'sanitize_email', explode( ',', (string) get_option( 'sb24_email_kontakt' ) ) ) );
	return $adresy ?: [ get_option( 'admin_email' ) ];
}

// Cała poczta strony (formularz, konta, WordPress) z tej samej skrzynki sklepu co e-maile WooCommerce, nie z wordpress@<domena>.
add_filter( 'wp_mail_from', fn( $od ) => sanitize_email( (string) get_option( 'woocommerce_email_from_address' ) ) ?: $od );
add_filter( 'wp_mail_from_name', fn( $nazwa ) => get_option( 'woocommerce_email_from_name' ) ?: $nazwa );

// Wysyłka przez SMTP skrzynki sklepu (bez wtyczki). Dane ze zmiennych środowiskowych SB24_SMTP_* (lokalnie z .env
// przez docker-compose.yml; na serwerze — zmienne środowiska albo stałe o tych nazwach w wp-config.php). Brak → zwykłe mail().
function sb24_smtp( $nazwa ) {
	return defined( $nazwa ) ? (string) constant( $nazwa ) : (string) getenv( $nazwa );
}
add_action( 'phpmailer_init', function ( $mailer ) {
	$mailpit = sb24_smtp( 'SB24_MAILPIT' ); // tylko lokalnie: host:port Mailpita — testowa poczta nie wychodzi do ludzi
	if ( '' !== $mailpit ) {
		[ $host, $port ] = array_pad( explode( ':', $mailpit, 2 ), 2, 1025 );
		$mailer->isSMTP();
		$mailer->Host        = $host;
		$mailer->Port        = (int) $port;
		$mailer->SMTPAuth    = false;
		$mailer->SMTPSecure  = '';
		$mailer->SMTPAutoTLS = false;
		return;
	}
	if ( '' === sb24_smtp( 'SB24_SMTP_HOST' ) ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host       = sb24_smtp( 'SB24_SMTP_HOST' );
	$mailer->Port       = (int) ( sb24_smtp( 'SB24_SMTP_PORT' ) ?: 587 );
	$mailer->SMTPSecure = 465 === $mailer->Port ? 'ssl' : 'tls';
	$mailer->SMTPAuth   = true;
	$mailer->Username   = sb24_smtp( 'SB24_SMTP_USER' );
	$mailer->Password   = sb24_smtp( 'SB24_SMTP_PASSWORD' );
	$mailer->Sender     = $mailer->Username; // koperta (Return-Path) = skrzynka, której dotyczy logowanie
	if ( 'local' === wp_get_environment_type() ) {
		// Tylko lokalnie: Norton Antivirus na komputerze dewelopera podmienia certyfikat SMTP na własny (skanowanie poczty).
		$mailer->SMTPOptions = [ 'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false ] ];
	}
} );

$GLOBALS['sb24_formularz'] = [ 'bledy' => [], 'dane' => [] ];

add_action( 'template_redirect', function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['sb24_formularz'] ) ) {
		return;
	}
	$p    = wp_unslash( $_POST );
	$dane = [
		'imie'      => sanitize_text_field( $p['sb24_imie'] ?? '' ),
		'email'     => sanitize_email( $p['sb24_email'] ?? '' ),
		'telefon'   => sanitize_text_field( $p['sb24_telefon'] ?? '' ),
		'temat'     => in_array( $p['sb24_temat'] ?? '', SB24_FORMULARZ_TEMATY, true ) ? $p['sb24_temat'] : 'Inne',
		'wiadomosc' => sanitize_textarea_field( $p['sb24_wiadomosc'] ?? '' ),
		'zgoda'     => ! empty( $p['sb24_zgoda'] ),
	];
	$bledy = [];
	[ $czas, $podpis ] = array_pad( explode( ':', sanitize_text_field( $p['sb24_czas'] ?? '' ), 2 ), 2, '' );
	$ip_klucz = 'sb24_formularz_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
	if ( '' !== (string) ( $p['sb24_www'] ?? '' ) || ! hash_equals( wp_hash( 'sb24_formularz' . (int) $czas ), $podpis )
		|| time() - (int) $czas < SB24_REJESTRACJA_MIN_SEKUND ) {
		$bledy[] = 'Nie udało się wysłać wiadomości. Odśwież stronę i spróbuj ponownie.';
	} elseif ( (int) get_transient( $ip_klucz ) >= 5 ) {
		$bledy[] = 'Wysłano już kilka wiadomości. Spróbuj ponownie za godzinę albo zadzwoń: ' . SB24_TELEFON . '.';
	} else {
		if ( '' === $dane['imie'] ) {
			$bledy[] = 'Podaj imię i nazwisko.';
		}
		if ( ! is_email( $dane['email'] ) ) {
			$bledy[] = 'Podaj poprawny adres e-mail — na niego odpowiemy.';
		}
		if ( mb_strlen( $dane['wiadomosc'] ) < 5 ) {
			$bledy[] = 'Wpisz treść wiadomości.';
		}
		if ( ! $dane['zgoda'] ) {
			$bledy[] = 'Zaznacz zgodę na przetwarzanie danych — bez niej nie możemy odpowiedzieć.';
		}
	}
	if ( ! $bledy ) {
		$temat    = "Wiadomość ze strony: {$dane['temat']} — {$dane['imie']}";
		$naglowki = [ 'Reply-To: ' . str_replace( [ '<', '>', ',', '"', "\r", "\n" ], '', $dane['imie'] ) . " <{$dane['email']}>" ];
		if ( function_exists( 'WC' ) ) { // HTML w szablonie e-maili sklepu (logo, kolory, stopka — jak potwierdzenia zamówień)
			$mailer  = WC()->mailer();
			$wyslano = $mailer->send( sb24_formularz_adres(), $temat, $mailer->wrap_message( 'Nowa wiadomość ze strony', sb24_formularz_html( $dane ) ),
				array_merge( [ 'Content-Type: text/html; charset=UTF-8' ], $naglowki ) );
		} else {
			$wyslano = wp_mail( sb24_formularz_adres(), $temat, wp_strip_all_tags( sb24_formularz_html( $dane ) ), $naglowki );
		}
		if ( $wyslano ) {
			set_transient( $ip_klucz, (int) get_transient( $ip_klucz ) + 1, HOUR_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'wyslano', 1, remove_query_arg( [ 'wyslano', 'produkt' ] ) ) . '#sb24-formularz', 303 );
			exit;
		}
		$bledy[] = 'Nie udało się wysłać wiadomości. Zadzwoń do nas: ' . SB24_TELEFON . ' albo ' . SB24_TELEFON_STACJONARNY . '.';
	}
	$GLOBALS['sb24_formularz'] = [ 'bledy' => $bledy, 'dane' => $dane ];
} );

/** Treść e-maila z formularza: tabela pól, wiadomość i przycisk „Odpowiedz klientowi”. */
function sb24_formularz_html( $d ) {
	$wiersz = fn( $etykieta, $wartosc ) => '<tr><th style="text-align:left;padding:8px 12px;border-bottom:1px solid #eeeeee;width:140px;color:#666666;font-weight:normal;">'
		. esc_html( $etykieta ) . '</th><td style="text-align:left;padding:8px 12px;border-bottom:1px solid #eeeeee;">' . $wartosc . '</td></tr>';
	$tel    = $d['telefon'] ? '<a href="tel:' . esc_attr( preg_replace( '/[^+\d]/', '', $d['telefon'] ) ) . '">' . esc_html( $d['telefon'] ) . '</a>' : '—';
	$strona = home_url( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) );
	return '<p>Klient wysłał wiadomość przez formularz na stronie. Odpowiedz na tego e-maila — odpowiedź trafi wprost do klienta.</p>'
		. '<table cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;">'
		. $wiersz( 'Imię i nazwisko', '<strong>' . esc_html( $d['imie'] ) . '</strong>' )
		. $wiersz( 'E-mail', '<a href="mailto:' . esc_attr( $d['email'] ) . '">' . esc_html( $d['email'] ) . '</a>' )
		. $wiersz( 'Telefon', $tel )
		. $wiersz( 'Temat', esc_html( $d['temat'] ) )
		. '</table>'
		. '<h2>Wiadomość</h2>'
		. '<div style="padding:16px 18px;background:#f8f6ef;border-left:3px solid #c9bd80;margin:0 0 24px;">' . wpautop( esc_html( $d['wiadomosc'] ) ) . '</div>'
		. '<p><a href="mailto:' . esc_attr( $d['email'] ) . '?subject=' . rawurlencode( 'Re: ' . $d['temat'] ) . '" style="display:inline-block;padding:12px 24px;background:#111111;color:#c9bd80;text-decoration:none;font-weight:bold;">Odpowiedz klientowi</a></p>'
		. '<p style="color:#999999;font-size:12px;">Wysłano z formularza: <a href="' . esc_url( $strona ) . '">' . esc_html( $strona ) . '</a></p>';
}

add_shortcode( 'sb24_formularz', function () {
	$stan    = $GLOBALS['sb24_formularz'];
	$d       = $stan['dane'] + [ 'imie' => '', 'email' => '', 'telefon' => '', 'temat' => '', 'wiadomosc' => '', 'zgoda' => false ];
	$produkt = isset( $_GET['produkt'] ) && function_exists( 'wc_get_product' ) ? wc_get_product( absint( $_GET['produkt'] ) ) : null;
	if ( $produkt && '' === $d['wiadomosc'] ) {
		$d['temat']     = 'Pytanie o produkt';
		$d['wiadomosc'] = 'Dzień dobry, mam pytanie o produkt: ' . $produkt->get_name() . ' (' . $produkt->get_permalink() . ").\n\n";
	}
	$czas = time();
	ob_start();
	?>
	<form id="sb24-formularz" class="sb24-formularz" method="post" action="#sb24-formularz">
		<?php if ( isset( $_GET['wyslano'] ) && ! $stan['bledy'] ) : ?>
			<p class="sb24-formularz-ok" role="status">Dziękujemy! Wiadomość została wysłana — odpowiemy najszybciej, jak to możliwe.</p>
		<?php endif; ?>
		<?php if ( $stan['bledy'] ) : ?>
			<ul class="sb24-formularz-bledy" role="alert">
				<?php foreach ( $stan['bledy'] as $b ) : ?><li><?php echo esc_html( $b ); ?></li><?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<input type="hidden" name="sb24_formularz" value="1">
		<input type="hidden" name="sb24_czas" value="<?php echo esc_attr( $czas . ':' . wp_hash( 'sb24_formularz' . $czas ) ); ?>">
		<p class="sb24-formularz-pulapka" aria-hidden="true"><label>Strona www <input type="text" name="sb24_www" tabindex="-1" autocomplete="off"></label></p>
		<div class="sb24-formularz-pola">
			<p><label for="sb24-imie">Imię i nazwisko *</label><input id="sb24-imie" type="text" name="sb24_imie" required autocomplete="name" value="<?php echo esc_attr( $d['imie'] ); ?>"></p>
			<p><label for="sb24-email">E-mail *</label><input id="sb24-email" type="email" name="sb24_email" required autocomplete="email" value="<?php echo esc_attr( $d['email'] ); ?>"></p>
			<p><label for="sb24-telefon">Telefon</label><input id="sb24-telefon" type="tel" name="sb24_telefon" autocomplete="tel" value="<?php echo esc_attr( $d['telefon'] ); ?>"></p>
			<p><label for="sb24-temat">Temat</label><select id="sb24-temat" name="sb24_temat">
				<?php foreach ( SB24_FORMULARZ_TEMATY as $t ) : ?><option<?php selected( $d['temat'], $t ); ?>><?php echo esc_html( $t ); ?></option><?php endforeach; ?>
			</select></p>
		</div>
		<p><label for="sb24-wiadomosc">Wiadomość *</label><textarea id="sb24-wiadomosc" name="sb24_wiadomosc" rows="6" required><?php echo esc_textarea( $d['wiadomosc'] ); ?></textarea></p>
		<p class="sb24-formularz-zgoda"><label><input type="checkbox" name="sb24_zgoda" value="1" required<?php checked( $d['zgoda'] ); ?>>
			<span>Wyrażam zgodę na przetwarzanie moich danych w celu odpowiedzi na wiadomość. Więcej w <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">polityce prywatności</a>. *</span></label></p>
		<p><button type="submit" class="sb24-salon-przycisk">Wyślij wiadomość</button></p>
	</form>
	<?php
	return ob_get_clean();
} );
