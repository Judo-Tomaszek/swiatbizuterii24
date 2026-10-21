<?php
/**
 * Import katalogu z eksportu Shopera (dane/eksport/) do WooCommerce.
 * Uruchomienie (z korzenia repo, lokalny WordPress z docker-compose.yml):
 *
 *   skrypty/wp.sh eval-file /import/skrypty/importuj-katalog.php kategorie
 *   skrypty/wp.sh eval-file /import/skrypty/importuj-katalog.php produkty [offset] [limit] [bez-zdjec]
 *
 * Decyzja właściciela (2026-10-16): do WordPressa idą TYLKO aktywne produkty — grupy
 * `aktywne-na-stanie` i `aktywne-stan-0`. Stan 0 = „Brak w magazynie” bez zakupu, jak w Shoperze
 * („brak towaru”, przycisk „powiadom o dostępności”).
 *
 * Idempotentny: kategorie i produkty rozpoznaje po meta `_sb24_shoper_id`, zdjęcia po `_sb24_gfx_id`
 * — ponowne uruchomienie aktualizuje, nie dubluje.
 *
 * Meta zostawiane na obiektach (dla wtyczki adresów i wtyczki SEO, wybieranej później):
 *   _sb24_shoper_id, _sb24_sciezka (stary adres bez domeny), _sb24_seo_title, _sb24_seo_description,
 *   _sb24_h1, a na kategorii dodatkowo _sb24_opis_dol (tekst pod listą produktów).
 * Title/meta pochodzą z migawki strony (to widzi Google), nie z API — patrz docs/migawka-seo-2026-10-16.md.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

const SB24_DANE  = '/import/dane';
const SB24_GRUPY = [ 'aktywne-na-stanie', 'aktywne-stan-0' ];

// Cechy o powtarzalnych wartościach → atrybuty globalne (filtry w sklepie).
// Pozostałe (kod, waga, wymiary — wartość niemal per produkt) → atrybuty lokalne produktu.
const SB24_ATRYBUTY_GLOBALNE = [
	'Złoto', 'Kolor złota', 'Kamienie', 'Rodzaj zapięcia', 'Rozmiar', 'Długość', 'Srebro', 'Stal',
	'Złoto żółte', 'Złoto białe', 'Złoto czerwone', 'Złoto rutenowane',
];
// Nazwy opcji wariantów z Shopera → atrybut wariantu.
const SB24_OPCJE_WARIANTOW = [ 'rozmiar' => 'Rozmiar', 'rozmiary' => 'Rozmiar', 'opcja' => 'Opcja' ];

// Ręczne przypisania kategorii (shoper_id produktu → shoper_id kategorii), gdy w Shoperze produkt
// siedzi tylko w kategorii wyłączonej bez aktywnego rodzica.
const SB24_KATEGORIE_RECZNE = [
	2684 => [ 135 ], // „Złoty pierścionek ozdobiony cyrkoniami” — był tylko w „Biżuteria > Pierścionki” (decyzja 2026-10-16)
];

$tryb = $args[0] ?? '';

function sb24_json( $plik ) {
	$dane = json_decode( file_get_contents( SB24_DANE . '/' . $plik ), true );
	if ( null === $dane ) {
		WP_CLI::error( "Nie mogę odczytać $plik" );
	}
	return $dane;
}

function sb24_znajdz_post( $shoper_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_sb24_shoper_id' AND pm.meta_value = %s AND p.post_type = 'product' LIMIT 1",
		(string) $shoper_id
	) );
}

function sb24_znajdz_term( $shoper_id ) {
	$t = get_terms( [
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'fields'     => 'ids',
		'meta_query' => [ [ 'key' => '_sb24_shoper_id', 'value' => (string) $shoper_id ] ],
	] );
	return $t ? (int) $t[0] : 0;
}

function sb24_meta_seo( $obiekt ) {
	$ze_strony = $obiekt['seo']['ze_strony'] ?? null;
	$z_api     = $obiekt['seo']['z_api'] ?? [];
	return [
		'_sb24_seo_title'       => $ze_strony['title'] ?? ( $z_api['title'] ?: null ),
		'_sb24_seo_description' => $ze_strony['description'] ?? ( $z_api['description'] ?: null ),
		'_sb24_h1'              => $ze_strony['h1'][0] ?? null,
	];
}

/* ------------------------------------------------------------------ kategorie */

function sb24_importuj_kategorie() {
	$kategorie = sb24_json( 'kategorie.json' );
	$n         = [ 'nowe' => 0, 'zaktualizowane' => 0, 'pominiete_nieaktywne' => 0 ];

	foreach ( $kategorie as $k ) { // kolejność drzewa: rodzic zawsze przed dzieckiem
		if ( ! $k['aktywna_z_rodzicami'] ) {
			$n['pominiete_nieaktywne']++;
			continue;
		}
		$rodzic = $k['rodzic'] ? sb24_znajdz_term( $k['rodzic'] ) : 0;
		$slug   = $k['url']['seo_url'] ?: sanitize_title( $k['nazwa'] ) . '-' . $k['shoper_id'];
		$dane   = [ 'slug' => $slug, 'parent' => $rodzic, 'description' => $k['opis_gora'] ];
		$id     = sb24_znajdz_term( $k['shoper_id'] );

		if ( $id ) {
			$wynik = wp_update_term( $id, 'product_cat', $dane + [ 'name' => $k['nazwa'] ] );
			$n['zaktualizowane']++;
		} else {
			$wynik = wp_insert_term( $k['nazwa'], 'product_cat', $dane );
			$n['nowe']++;
		}
		if ( is_wp_error( $wynik ) ) {
			WP_CLI::warning( "Kategoria {$k['shoper_id']} {$k['nazwa']}: " . $wynik->get_error_message() );
			continue;
		}
		$id = (int) $wynik['term_id'];
		update_term_meta( $id, '_sb24_shoper_id', (string) $k['shoper_id'] );
		update_term_meta( $id, '_sb24_sciezka', $k['url']['sciezka'] );
		update_term_meta( $id, '_sb24_opis_dol', $k['opis_dol'] );
		update_term_meta( $id, 'order', (int) $k['kolejnosc'] );
		foreach ( sb24_meta_seo( $k ) as $klucz => $wartosc ) {
			update_term_meta( $id, $klucz, $wartosc );
		}
	}
	WP_CLI::success( 'Kategorie: ' . wp_json_encode( $n, JSON_UNESCAPED_UNICODE ) );
}

/* ------------------------------------------------------------------ atrybuty */

function sb24_atrybut_globalny( $nazwa ) {
	static $cache = [];
	$nazwa = rtrim( trim( $nazwa ), ':' );
	if ( isset( $cache[ $nazwa ] ) ) {
		return $cache[ $nazwa ];
	}
	$slug = substr( sanitize_title( $nazwa ), 0, 27 );
	$id   = wc_attribute_taxonomy_id_by_name( $slug );
	if ( ! $id ) {
		$id = wc_create_attribute( [ 'name' => $nazwa, 'slug' => $slug, 'type' => 'select', 'has_archives' => false ] );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( "Atrybut $nazwa: " . $id->get_error_message() );
		}
	}
	$taksonomia = wc_attribute_taxonomy_name( $slug );
	if ( ! taxonomy_exists( $taksonomia ) ) { // taksonomie atrybutów rejestruje się na init — tu dorejestrowujemy
		register_taxonomy( $taksonomia, [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false, 'query_var' => false, 'rewrite' => false ] );
	}
	return $cache[ $nazwa ] = [ 'id' => (int) $id, 'taksonomia' => $taksonomia, 'nazwa' => $nazwa ];
}

function sb24_term_atrybutu( $taksonomia, $wartosc ) {
	$wartosc = trim( (string) $wartosc );
	$t       = get_term_by( 'name', $wartosc, $taksonomia );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$nowy = wp_insert_term( $wartosc, $taksonomia );
	if ( is_wp_error( $nowy ) ) {
		return (int) ( $nowy->get_error_data( 'term_exists' ) ?: 0 );
	}
	return (int) $nowy['term_id'];
}

function sb24_atrybuty_produktu( $p, &$wariant_tax ) {
	$atrybuty = [];
	$globalne = [];
	$lokalne  = [];
	foreach ( $p['atrybuty'] as $a ) {
		$nazwa = rtrim( trim( $a['nazwa'] ), ':' );
		if ( in_array( $nazwa, SB24_ATRYBUTY_GLOBALNE, true ) ) {
			$globalne[ $nazwa ][] = $a['wartosc'];
		} else {
			$lokalne[ $nazwa ][] = $a['wartosc'];
		}
	}

	// Warianty: jedna opcja na wariant (sprawdzone w danych 2026-10-16)
	$wariant_tax = null;
	if ( $p['warianty'] ) {
		$opcja = strtolower( $p['warianty'][0]['opcje'][0]['opcja'] ?? 'opcja' );
		$nazwa = SB24_OPCJE_WARIANTOW[ $opcja ] ?? ucfirst( $opcja );
		$g     = sb24_atrybut_globalny( $nazwa );
		$ids   = [];
		foreach ( $p['warianty'] as $w ) {
			$ids[] = sb24_term_atrybutu( $g['taksonomia'], $w['opcje'][0]['wartosc'] ?? '' );
		}
		$attr = new WC_Product_Attribute();
		$attr->set_id( $g['id'] );
		$attr->set_name( $g['taksonomia'] );
		$attr->set_options( array_values( array_unique( array_filter( $ids ) ) ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$atrybuty[ $g['taksonomia'] ] = $attr;
		$wariant_tax                  = $g['taksonomia'];
		unset( $globalne[ $nazwa ] ); // pojedyncza cecha „Rozmiar” znika — rozmiar wybiera się w wariancie
	}

	$poz = 0;
	foreach ( $globalne as $nazwa => $wartosci ) {
		$g    = sb24_atrybut_globalny( $nazwa );
		$attr = new WC_Product_Attribute();
		$attr->set_id( $g['id'] );
		$attr->set_name( $g['taksonomia'] );
		$attr->set_options( array_map( fn( $w ) => sb24_term_atrybutu( $g['taksonomia'], $w ), array_unique( $wartosci ) ) );
		$attr->set_visible( true );
		$attr->set_position( $poz++ );
		$atrybuty[ $g['taksonomia'] ] = $attr;
	}
	foreach ( $lokalne as $nazwa => $wartosci ) {
		$attr = new WC_Product_Attribute();
		$attr->set_name( $nazwa );
		$attr->set_options( array_values( array_unique( $wartosci ) ) );
		$attr->set_visible( true );
		$attr->set_position( $poz++ );
		$atrybuty[ sanitize_title( $nazwa ) ] = $attr;
	}
	return $atrybuty;
}

/* ------------------------------------------------------------------ zdjęcia */

function sb24_zdjecie( $z, $grupa, $post_id ) {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sb24_gfx_id' AND meta_value = %s LIMIT 1",
		(string) $z['gfx_id']
	) );
	if ( ! $id ) {
		$zrodlo = SB24_DANE . "/$grupa/" . $z['plik'];
		if ( ! is_file( $zrodlo ) ) {
			WP_CLI::warning( "Brak pliku $zrodlo" );
			return 0;
		}
		$tmp = wp_tempnam( basename( $zrodlo ) );
		copy( $zrodlo, $tmp );
		$nazwa = sanitize_file_name( sanitize_title( $z['alt'] ) . '-' . $z['gfx_id'] . '.' . pathinfo( $zrodlo, PATHINFO_EXTENSION ) );
		$id    = media_handle_sideload( [ 'name' => $nazwa, 'tmp_name' => $tmp ], $post_id, $z['alt'] );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			WP_CLI::warning( "Zdjęcie {$z['gfx_id']}: " . $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, '_sb24_gfx_id', (string) $z['gfx_id'] );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $z['alt'] );
	return (int) $id;
}

/* ------------------------------------------------------------------ produkty */

function sb24_kategorie_produktu( $p, $mapa_rodzicow ) {
	if ( isset( SB24_KATEGORIE_RECZNE[ $p['shoper_id'] ] ) ) {
		return array_map( 'sb24_znajdz_term', SB24_KATEGORIE_RECZNE[ $p['shoper_id'] ] );
	}
	$ids = [];
	foreach ( $p['kategorie'] as $k ) {
		// kategoria wyłączona w Shoperze → najbliższa aktywna kategoria nadrzędna
		for ( $c = $k['id']; $c; $c = $mapa_rodzicow[ $c ] ?? null ) {
			$t = sb24_znajdz_term( $c );
			if ( $t ) {
				$ids[] = $t;
				break;
			}
		}
	}
	return array_values( array_unique( $ids ) );
}

function sb24_importuj_produkt( $p, $mapa_rodzicow, $ze_zdjeciami ) {
	$id       = sb24_znajdz_post( $p['shoper_id'] );
	$atrybuty = sb24_atrybuty_produktu( $p, $wariant_tax );
	$klasa    = $wariant_tax ? WC_Product_Variable::class : WC_Product_Simple::class;

	if ( $id && get_class( wc_get_product( $id ) ) !== $klasa ) {
		wp_set_object_terms( $id, $wariant_tax ? 'variable' : 'simple', 'product_type' );
	}
	$produkt = $id ? new $klasa( $id ) : new $klasa();

	$produkt->set_name( trim( $p['nazwa'] ) );
	$produkt->set_slug( $p['url']['seo_url'] ?: sanitize_title( trim( $p['nazwa'] ) ) . '-' . $p['shoper_id'] );
	$produkt->set_status( 'publish' );
	$produkt->set_catalog_visibility( 'visible' );
	$produkt->set_description( $p['opis'] );
	$produkt->set_short_description( $p['krotki_opis'] );
	$produkt->set_category_ids( sb24_kategorie_produktu( $p, $mapa_rodzicow ) );
	$produkt->set_attributes( $atrybuty );
	$produkt->set_date_created( $p['dodany'] );
	if ( ! $id ) {
		$produkt->set_sku( $p['kod'] ); // SKU unikalne w danych (sprawdzone); przy aktualizacji bez zmian
	}
	if ( $wariant_tax ) {
		$produkt->set_manage_stock( false );
	} else {
		$produkt->set_regular_price( (string) $p['cena'] );
		$produkt->set_manage_stock( true );
		$produkt->set_stock_quantity( (int) $p['stan'] );
		$produkt->set_backorders( 'no' );
	}
	$id = $produkt->save();

	update_post_meta( $id, '_sb24_shoper_id', (string) $p['shoper_id'] );
	update_post_meta( $id, '_sb24_sciezka', $p['url']['sciezka'] );
	update_post_meta( $id, '_sb24_grupa', $p['grupa'] );
	update_post_meta( $id, '_sb24_powiazane', $p['powiazane'] );
	foreach ( sb24_meta_seo( $p ) as $klucz => $wartosc ) {
		update_post_meta( $id, $klucz, $wartosc );
	}

	if ( $wariant_tax ) {
		$istniejace = [];
		foreach ( $produkt->get_children() as $vid ) {
			$istniejace[ get_post_meta( $vid, '_sb24_stock_id', true ) ] = $vid;
		}
		foreach ( $p['warianty'] as $i => $w ) {
			$wartosc = trim( (string) ( $w['opcje'][0]['wartosc'] ?? '' ) );
			$term    = get_term_by( 'name', $wartosc, $wariant_tax );
			$v       = new WC_Product_Variation( $istniejace[ $w['stock_id'] ] ?? 0 );
			$v->set_parent_id( $id );
			$v->set_attributes( [ $wariant_tax => $term ? $term->slug : sanitize_title( $wartosc ) ] );
			$v->set_regular_price( (string) $w['cena'] );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( (int) $w['stan'] );
			$v->set_backorders( 'no' );
			$v->set_menu_order( $i );
			$v->set_status( 'publish' );
			$vid = $v->save();
			update_post_meta( $vid, '_sb24_stock_id', (string) $w['stock_id'] );
			unset( $istniejace[ $w['stock_id'] ] );
		}
		foreach ( $istniejace as $vid ) {
			wp_delete_post( $vid, true );
		}
		WC_Product_Variable::sync( $id );
	}

	if ( $ze_zdjeciami ) {
		$zdjecia = array_values( array_filter( $p['zdjecia'], fn( $z ) => ! $z['ukryte'] ) );
		usort( $zdjecia, fn( $a, $b ) => ( $b['glowne'] <=> $a['glowne'] ) ?: ( $a['kolejnosc'] <=> $b['kolejnosc'] ) );
		$ids = array_values( array_filter( array_map( fn( $z ) => sb24_zdjecie( $z, $p['grupa'], $id ), $zdjecia ) ) );
		$produkt = wc_get_product( $id );
		$produkt->set_image_id( $ids[0] ?? '' );
		$produkt->set_gallery_image_ids( array_slice( $ids, 1 ) );
		$produkt->save();
	}
	return $id;
}

function sb24_importuj_produkty( $offset, $limit, $ze_zdjeciami ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$mapa_rodzicow = [];
	foreach ( sb24_json( 'kategorie.json' ) as $k ) {
		$mapa_rodzicow[ $k['shoper_id'] ] = $k['rodzic'];
	}
	$produkty = [];
	foreach ( SB24_GRUPY as $g ) {
		array_push( $produkty, ...sb24_json( "$g/produkty.json" ) );
	}
	usort( $produkty, fn( $a, $b ) => $a['shoper_id'] <=> $b['shoper_id'] );
	$partia = array_slice( $produkty, $offset, $limit ?: null );

	wp_defer_term_counting( true );
	$start = microtime( true );
	foreach ( $partia as $i => $p ) {
		try {
			sb24_importuj_produkt( $p, $mapa_rodzicow, $ze_zdjeciami );
		} catch ( Throwable $e ) {
			WP_CLI::warning( "Produkt {$p['shoper_id']}: " . $e->getMessage() );
		}
		if ( 0 === ( $i + 1 ) % 25 ) {
			WP_CLI::log( sprintf( '%d/%d (%.0f s)', $offset + $i + 1, count( $produkty ), microtime( true ) - $start ) );
			wp_cache_flush();
		}
	}
	wp_defer_term_counting( false );
	WP_CLI::success( sprintf( 'Produkty %d–%d z %d gotowe.', $offset + 1, $offset + count( $partia ), count( $produkty ) ) );
}

/* ------------------------------------------------------------------ start */

if ( 'kategorie' === $tryb ) {
	sb24_importuj_kategorie();
} elseif ( 'produkty' === $tryb ) {
	sb24_importuj_produkty( (int) ( $args[1] ?? 0 ), (int) ( $args[2] ?? 0 ), 'bez-zdjec' !== ( $args[3] ?? '' ) );
} else {
	WP_CLI::error( 'Tryb: kategorie | produkty [offset] [limit] [bez-zdjec]' );
}
