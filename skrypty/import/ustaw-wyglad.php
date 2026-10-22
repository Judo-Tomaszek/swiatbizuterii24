<?php
/**
 * Ustawienia wyglądu do porównania motywów (Kadence vs Blocksy) — te same treści, menu, kolory marki.
 *
 *   skrypty/wp.sh eval-file /import/skrypty/ustaw-wyglad.php wspolne
 *   skrypty/wp.sh theme activate blocksy && skrypty/wp.sh eval-file /import/skrypty/ustaw-wyglad.php blocksy
 *   skrypty/wp.sh theme activate kadence && skrypty/wp.sh eval-file /import/skrypty/ustaw-wyglad.php kadence
 *   skrypty/wp.sh theme activate astra && skrypty/wp.sh eval-file /import/skrypty/ustaw-wyglad.php astra
 *
 * (Ustawienia Blocksy budują klasy motywu — wymagają, żeby Blocksy był aktywny w czasie skryptu.)
 * Podgląd bez przełączania: ?motyw=kadence / ?motyw=blocksy (wtyczka sb24-migracja, tylko lokalnie).
 *
 * Marka z Shopera: złoty #c9bd80, ciemny #111, Open Sans, logo 350×66 (złoty napis, przezroczyste tło).
 * Na przyciski idzie ciemniejsze złoto #8b7b3f — biały tekst na #c9bd80 ma kontrast ~1,9:1, nieczytelny.
 * Menu i stopka 1:1 z Shopera (kolejność pozycji jak w migawce strony głównej).
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

const SB24_MARKA = [
	'zloto'        => '#c9bd80',
	'zloto_ciemne' => '#8b7b3f',
	'zloto_hover'  => '#6f6232',
	'czern'        => '#111111',
	'tekst'        => '#333333',
	'tekst_jasny'  => '#666666',
	'ramka'        => '#e8e1c8',
	'tlo_jasne'    => '#f8f6ef',
];
const SB24_KONTAKT = [ 'telefon' => '+48 535 263 900', 'telefon_stacjonarny' => '+48 77 458 10 46', 'adres' => 'ul. Ozimska 17A, 45-057 Opole', 'facebook' => 'https://www.facebook.com/swiatbizuterii24' ];

function sb24w_term( $sciezka ) {
	$t = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_query' => [ [ 'key' => '_sb24_sciezka', 'value' => $sciezka ] ] ] );
	return $t ? $t[0] : null;
}
function sb24w_strona( $sciezka ) {
	$p = get_posts( [ 'post_type' => 'page', 'meta_key' => '_sb24_sciezka', 'meta_value' => $sciezka, 'numberposts' => 1 ] );
	return $p ? $p[0] : null;
}

/* ------------------------------------------------------------ wspólne */

function sb24w_logo() {
	$p = get_posts( [ 'post_type' => 'attachment', 'meta_key' => '_sb24_zrodlo', 'meta_value' => 'marka/logo.png', 'numberposts' => 1 ] );
	if ( $p ) {
		return $p[0]->ID;
	}
	$tmp = wp_tempnam( 'logo.png' );
	copy( '/import/dane/tresci/marka/logo.png', $tmp );
	$id = media_handle_sideload( [ 'name' => 'swiatbizuterii24-logo.png', 'tmp_name' => $tmp ], 0, 'Świat Biżuterii 24' );
	update_post_meta( $id, '_sb24_zrodlo', 'marka/logo.png' );
	update_post_meta( $id, '_wp_attachment_image_alt', 'Jubiler Opole | Swiatbiżuterii24' ); // alt z Shopera
	return $id;
}

/** Ikona strony (favicon). Shoper ma zamiast niej pusty GIF 1×1 — ikona zrobiona z barw marki (złote „Ś” na #111), 512×512. */
function sb24w_favicon() {
	$plik = '/import/dane/tresci/marka/favicon.png';
	$p    = get_posts( [ 'post_type' => 'attachment', 'meta_key' => '_sb24_zrodlo', 'meta_value' => 'marka/favicon.png', 'numberposts' => 1 ] );
	if ( $p && md5_file( $plik ) === get_post_meta( $p[0]->ID, '_sb24_md5', true ) ) {
		return $p[0]->ID;
	}
	$tmp = wp_tempnam( 'favicon.png' );
	copy( $plik, $tmp );
	$id = media_handle_sideload( [ 'name' => 'swiatbizuterii24-ikona.png', 'tmp_name' => $tmp ], 0, 'Ikona strony' );
	update_post_meta( $id, '_sb24_zrodlo', 'marka/favicon.png' );
	update_post_meta( $id, '_sb24_md5', md5_file( $plik ) );
	if ( $p ) {
		wp_delete_attachment( $p[0]->ID, true );
	}
	return $id;
}

// Menu ułożone 1:1 z Shopera tylko przy pierwszym uruchomieniu — potem prowadzi je właściciel w panelu
// (2026-10-20 skrócił menu główne), więc istniejącego nie nadpisujemy.
function sb24w_menu_glowne() {
	$stare = wp_get_nav_menu_object( 'Menu główne' );
	if ( $stare ) {
		return $stare->term_id;
	}
	$menu = wp_create_nav_menu( 'Menu główne' );
	$poz  = 0;
	$kat  = function ( $sciezka, $rodzic = 0 ) use ( $menu, &$poz ) {
		$t = sb24w_term( $sciezka );
		return $t ? wp_update_nav_menu_item( $menu, 0, [
			'menu-item-type' => 'taxonomy', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $t->term_id,
			'menu-item-parent-id' => $rodzic, 'menu-item-position' => ++$poz, 'menu-item-status' => 'publish',
		] ) : 0;
	};
	$str = function ( $sciezka, $tytul = '' ) use ( $menu, &$poz ) {
		$p = sb24w_strona( $sciezka );
		return $p ? wp_update_nav_menu_item( $menu, 0, [
			'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $p->ID,
			'menu-item-title' => $tytul, 'menu-item-position' => ++$poz, 'menu-item-status' => 'publish',
		] ) : 0;
	};
	$z = $kat( '/zloto' );
	foreach ( [ '/zlote-kolczyki', '/zlote-pierscionki', '/zlote-bransoletki', '/zloto-zloto-dla-dzieci', '/zlote-lancuszki', '/zloto-diamenty', '/zlote-zawieszki', '/zlote-broszki', '/zlote-medaliki', '/zlote-zegarki', '/zlote-krzyzyki' ] as $s ) {
		$kat( $s, $z );
	}
	$kat( '/zlote-kolczyki' );
	$s = $kat( '/srebro' );
	foreach ( [ '/srebrne-pierscionki', '/srebrne-bransoletki', '/srebrne-zegarki', '/srebrne-krzyzyki', '/srebrne-zawieszki', '/srebrne-lancuszki', '/srebrne-broszki', '/srebrne-medaliki', '/srebrne-kolczyki', '/srebrna-bizuteria-dla-dzieci', '/bizuteria-ze-stali' ] as $x ) {
		$kat( $x, $s );
	}
	$kat( '/obraczki' );
	$kat( '/pierscionki-zareczynowe' );
	$kat( '/pl/c/Indywidualne-projekty/158' );
	$str( '/pl/blog' );
	$str( '/pl/i/Salon-firmowy/15' );
	$str( '/naprawa-bizuterii-opole', 'Naprawa biżuterii' );
	$str( '/skup-zlota-opole' );
	return $menu;
}

/** Kolumny stopki jako bloki (działają w obu motywach); linki ścieżkami — przeżyją zmianę domeny. */
function sb24w_stopka() {
	$lista = fn( $tytul, $linki ) => '<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">' . esc_html( $tytul ) . '</h4><!-- /wp:heading -->'
		. '<!-- wp:list --><ul class="wp-block-list">' . implode( '', array_map( fn( $t, $u ) => '<!-- wp:list-item --><li><a href="' . esc_attr( $u ) . '">' . esc_html( $t ) . '</a></li><!-- /wp:list-item -->', array_keys( $linki ), $linki ) ) . '</ul><!-- /wp:list -->';
	$konto = get_permalink( wc_get_page_id( 'myaccount' ) );
	return [
		$lista( 'Informacje', [ 'Blog' => '/pl/blog', 'Regulamin' => '/pl/i/Regulamin/3', 'Polityka prywatności' => '/pl/i/Polityka-prywatnosci/11', 'Chcesz oddać zakupiony towar?' => '/pl/i/Chcesz-oddac-zakupiony-towar/2' ] ),
		$lista( 'Zakupy', [ 'Formy płatności' => '/formy-platnosci', 'Czas i koszty dostawy' => '/czas-i-koszty-dostawy', 'Jak i kiedy dostarczymy Twoją przesyłkę?' => '/jak-i-kiedy-dostarczymy-twoja-przesylke', 'Moje konto' => wp_make_link_relative( $konto ) ] ),
		$lista( 'Usługi i salon', [ 'Naprawa biżuterii' => '/naprawa-bizuterii-opole', 'Skup złota Opole' => '/skup-zlota-opole', 'Projekty indywidualne' => '/pl/i/Projekty-indywidualne/13', 'Dlaczego my?' => '/dlaczego-my', 'Kontakt i dane firmy' => '/kontakt' ] ),
		'<!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Salon w Opolu</h4><!-- /wp:heading -->'
		. '<!-- wp:paragraph --><p>Potrzebujesz pomocy? Zadzwoń!<br><strong><a href="tel:+48535263900">' . SB24_KONTAKT['telefon'] . '</a><br><a href="tel:+48774581046">' . SB24_KONTAKT['telefon_stacjonarny'] . '</a></strong></p><!-- /wp:paragraph -->'
		. '<!-- wp:paragraph --><p>' . SB24_KONTAKT['adres'] . '</p><!-- /wp:paragraph -->'
		. '<!-- wp:paragraph --><p><a href="' . SB24_KONTAKT['facebook'] . '" target="_blank" rel="noopener">Facebook</a></p><!-- /wp:paragraph -->',
	];
}

function sb24w_widgety( $sidebary ) {
	$bloki        = get_option( 'widget_block', [] );
	$przypisania  = get_option( 'sidebars_widgets', [] );
	$nr           = max( array_merge( [ 1 ], array_filter( array_keys( $bloki ), 'is_int' ) ) );
	foreach ( sb24w_stopka() as $i => $html ) {
		$sidebar = $sidebary[ $i ];
		foreach ( $przypisania[ $sidebar ] ?? [] as $w ) { // idempotentnie: czyścimy poprzednie bloki tego obszaru
			unset( $bloki[ (int) str_replace( 'block-', '', $w ) ] );
		}
		$bloki[ ++$nr ]          = [ 'content' => $html ];
		$przypisania[ $sidebar ] = [ "block-$nr" ];
	}
	update_option( 'widget_block', $bloki );
	update_option( 'sidebars_widgets', $przypisania );
}

/**
 * Lewa kolumna stron kategorii jak w Shoperze: drzewo kategorii („Menu”) i „Opcje przeglądania”
 * (Złoto, Kamienie, Kolor złota, Rodzaj zapięcia + Długość i Cena). Klasyczne widgety WooCommerce —
 * filtrują główne zapytanie kategorii, działają w każdym klasycznym motywie. Atrybut pokazuje się
 * tylko tam, gdzie produkty kategorii go mają. Pominięte: Rozmiar (wartości z Shopera do uporządkowania)
 * i atrybuty „tak” (Złoto żółte itp.) — dublują Kolor złota.
 */
function sb24w_filtry( $sidebar ) {
	$widgety = [
		[ 'woocommerce_layered_nav_filters', [ 'title' => 'Wybrane filtry' ] ],
		[ 'woocommerce_product_categories', [ 'title' => 'Kategorie', 'orderby' => 'order', 'dropdown' => 0, 'count' => 0, 'hierarchical' => 1, 'show_children_only' => 0, 'hide_empty' => 1, 'max_depth' => '' ] ],
	];
	foreach ( [ 'zloto' => 'Złoto', 'kamienie' => 'Kamienie', 'kolor-zlota' => 'Kolor złota', 'rodzaj-zapiecia' => 'Rodzaj zapięcia', 'dlugosc' => 'Długość' ] as $atrybut => $tytul ) {
		$widgety[] = [ 'woocommerce_layered_nav', [ 'title' => $tytul, 'attribute' => $atrybut, 'display_type' => 'list', 'query_type' => 'or' ] ];
	}
	$widgety[] = [ 'woocommerce_price_filter', [ 'title' => 'Cena' ] ];

	$opcje = [];
	$ids   = [];
	foreach ( $widgety as [ $baza, $ustawienia ] ) {
		$opcje[ $baza ] ??= [ '_multiwidget' => 1 ];
		$nr                    = count( $opcje[ $baza ] ); // 1, 2, … (klucz _multiwidget zajmuje „0”)
		$opcje[ $baza ][ $nr ] = $ustawienia;
		$ids[]                 = "$baza-$nr";
	}
	foreach ( $opcje as $baza => $o ) { // te typy widgetów są tylko tutaj — nadpisujemy w całości (idempotentnie)
		update_option( "widget_$baza", $o );
	}
	$przypisania             = get_option( 'sidebars_widgets', [] );
	$przypisania[ $sidebar ] = $ids;
	update_option( 'sidebars_widgets', $przypisania );
}

/** Bloki paska bocznego bloga (style: wtyczka, blog.css). Linki ścieżkami — przeżyją zmianę domeny. */
function sb24w_pasek_bloga_bloki() {
	$kolekcje = [ 'Pierścionki zaręczynowe' => '/pierscionki-zareczynowe', 'Obrączki ślubne' => '/obraczki', 'Złote kolczyki' => '/zlote-kolczyki',
		'Srebrne kolczyki' => '/srebrne-kolczyki', 'Złote łańcuszki' => '/zlote-lancuszki', 'Naprawa biżuterii' => '/naprawa-bizuterii-opole', 'Skup złota' => '/skup-zlota-opole' ];
	$lista = implode( '', array_map( fn( $t, $u ) => '<li><a href="' . esc_attr( $u ) . '">' . esc_html( $t ) . '</a></li>', array_keys( $kolekcje ), $kolekcje ) );
	return [
		'<!-- wp:search {"label":"Szukaj","showLabel":false,"placeholder":"Szukaj na blogu…","buttonText":"Szukaj","buttonUseIcon":true} /-->',
		'<!-- wp:html --><div class="sb24-pasek-salon"><p class="sb24-pasek-nadtytul">Salon jubilerski</p><h3>Odwiedź nas w Opolu</h3>'
			. '<p>' . SB24_KONTAKT['adres'] . '<br>pon.–pt. 10:00–18:00 · sob. 10:00–14:00<br>tel. <a href="tel:+48774581046">' . SB24_KONTAKT['telefon_stacjonarny'] . '</a></p>'
			. '<a class="sb24-pasek-przycisk" href="tel:+48535263900">Zadzwoń: ' . SB24_KONTAKT['telefon'] . '</a></div><!-- /wp:html -->',
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Najnowsze wpisy</h3><!-- /wp:heading -->'
			. '<!-- wp:latest-posts {"postsToShow":5,"displayFeaturedImage":true,"featuredImageAlign":"left","featuredImageSizeSlug":"thumbnail","featuredImageSizeWidth":64,"featuredImageSizeHeight":64} /-->',
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Z naszego sklepu</h3><!-- /wp:heading -->'
			. '<!-- wp:html --><ul class="sb24-pasek-kolekcje">' . $lista . '</ul><!-- /wp:html -->',
		'<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Tematy</h3><!-- /wp:heading -->'
			. '<!-- wp:tag-cloud {"smallestFontSize":"13px","largestFontSize":"13px"} /-->',
	];
}

/** Bloki do obszaru widgetów (idempotentnie: najpierw usuwa poprzednie bloki tego obszaru). */
function sb24w_bloki_w_obszarze( $sidebar, $bloki_html ) {
	$bloki       = get_option( 'widget_block', [] );
	$przypisania = get_option( 'sidebars_widgets', [] );
	foreach ( $przypisania[ $sidebar ] ?? [] as $w ) {
		unset( $bloki[ (int) str_replace( 'block-', '', $w ) ] );
	}
	$nr                      = max( array_merge( [ 1 ], array_filter( array_keys( $bloki ), 'is_int' ) ) );
	$przypisania[ $sidebar ] = [];
	foreach ( $bloki_html as $html ) {
		$bloki[ ++$nr ]            = [ 'content' => $html ];
		$przypisania[ $sidebar ][] = "block-$nr";
	}
	update_option( 'widget_block', $bloki );
	update_option( 'sidebars_widgets', $przypisania );
}

function sb24w_css_wspolny() {
	$m = SB24_MARKA;
	return <<<CSS
/* SB24 — wspólne akcenty marki (Kadence i Blocksy) */
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&display=swap');
h1, h2, h3, .woocommerce-loop-product__title, .product_title { font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 600; letter-spacing: .01em; }
h1, .product_title { color: {$m['czern']}; }
.sb24-opis-dol, .term-description { max-width: 900px; margin: 2.5rem auto 0; line-height: 1.75; }
.sb24-opis-dol h2, .sb24-opis-dol h3, .term-description h2, .term-description h3 { margin-top: 1.6em; }
.woocommerce ul.products li.product .price, .woocommerce div.product p.price { color: {$m['zloto_ciemne']}; font-weight: 600; }
.woocommerce span.onsale { background: {$m['zloto_ciemne']}; }
.stock.out-of-stock { color: #a33; }
.sb24-blok { max-width: 1100px; margin: 3rem auto; line-height: 1.75; }
.sb24-blok img { max-width: 100%; height: auto; }
CSS;
}

function sb24w_wspolne() {
	update_option( 'blogname', 'Świat Biżuterii 24' );
	update_option( 'blogdescription', 'Jubiler Opole' );
	update_option( 'woocommerce_catalog_columns', 3 );
	update_option( 'woocommerce_catalog_rows', 5 ); // 15 na stronę — nie mniej niż dotychczasowe 16, więc stare numery stron (/zloto/2) dalej istnieją
	$logo = sb24w_logo();
	update_option( 'site_icon', sb24w_favicon() );
	// Opinie o produktach: tylko od kupujących (zweryfikowani właściciele), z gwiazdkami, każda zatwierdzana ręcznie.
	// Bez opinii „na start” — wymyślone opinie to w UE nieuczciwa praktyka (dyrektywa Omnibus, UOKiK).
	foreach ( [ 'woocommerce_enable_reviews' => 'yes', 'woocommerce_enable_review_rating' => 'yes', 'woocommerce_review_rating_required' => 'yes',
		'woocommerce_review_rating_verification_label' => 'yes', 'woocommerce_review_rating_verification_required' => 'yes', 'comment_moderation' => 1 ] as $k => $v ) {
		update_option( $k, $v );
	}
	$menu = sb24w_menu_glowne();
	update_option( 'sb24_wyglad', [ 'logo' => $logo, 'menu' => $menu ] );
	WP_CLI::success( "Wspólne: logo $logo, menu $menu (" . count( wp_get_nav_menu_items( $menu ) ) . ' pozycji)' );
}

function sb24w_mods( $motyw, $mods ) {
	update_option( "theme_mods_$motyw", array_replace( get_option( "theme_mods_$motyw", [] ) ?: [], $mods ) );
}

/* ------------------------------------------------------------ Kadence */

function sb24w_kadence() {
	$w = get_option( 'sb24_wyglad' );
	$m = SB24_MARKA;
	$paleta = [];
	foreach ( [ $m['zloto_ciemne'], $m['zloto_hover'], $m['czern'], $m['tekst'], $m['tekst_jasny'], '#999999', $m['ramka'], $m['tlo_jasne'], '#ffffff' ] as $i => $kolor ) {
		$paleta[] = [ 'color' => $kolor, 'slug' => 'palette' . ( $i + 1 ), 'name' => 'Palette Color ' . ( $i + 1 ) ];
	}
	// Kolory pomocnicze 10–15 jak w domyślnej palecie Kadence (palette_defaults w inc/components/options).
	$pomocnicze = [ [ '#FfFfFf', 'Complement' ], [ '#13612e', 'Success' ], [ '#1159af', 'Info' ], [ '#b82105', 'Alert' ], [ '#f7630c', 'Warning' ], [ '#f5a524', 'Rating' ] ];
	foreach ( $pomocnicze as $i => [ $kolor, $nazwa ] ) {
		$paleta[] = [ 'color' => $kolor, 'slug' => 'palette' . ( $i + 10 ), 'name' => "Palette Color $nazwa" ];
	}
	$pelna = $paleta;
	update_option( 'kadence_global_palette', wp_json_encode( [ 'palette' => $pelna, 'second-palette' => $pelna, 'third-palette' => $pelna, 'active' => 'palette' ] ) );

	$puste5 = fn( $p ) => array_fill_keys( array_map( fn( $s ) => "{$p}_$s", [ 'left', 'left_center', 'center', 'right_center', 'right' ] ), [] );
	sb24w_mods( 'kadence', [
		'custom_logo'          => $w['logo'],
		'logo_width'           => [ 'size' => [ 'mobile' => 200, 'tablet' => 240, 'desktop' => 280 ], 'unit' => [ 'mobile' => 'px', 'tablet' => 'px', 'desktop' => 'px' ] ],
		'logo_layout'          => [ 'include' => [ 'mobile' => '', 'tablet' => '', 'desktop' => 'logo_only' ], 'layout' => [ 'mobile' => '', 'tablet' => '', 'desktop' => 'standard' ] ],
		'nav_menu_locations'   => [ 'primary' => $w['menu'], 'mobile' => $w['menu'] ],
		'header_desktop_items' => [
			'top'    => array_replace( $puste5( 'top' ), [ 'top_left' => [ 'html' ] ] ),
			'main'   => array_replace( $puste5( 'main' ), [ 'main_left' => [ 'logo' ], 'main_right' => [ 'search', 'cart' ] ] ),
			'bottom' => array_replace( $puste5( 'bottom' ), [ 'bottom_center' => [ 'navigation' ] ] ),
		],
		'header_mobile_items'  => [
			'popup'  => [ 'popup_content' => [ 'mobile-navigation' ] ],
			'top'    => [ 'top_left' => [], 'top_center' => [], 'top_right' => [] ],
			'main'   => [ 'main_left' => [ 'mobile-logo' ], 'main_center' => [], 'main_right' => [ 'mobile-cart', 'popup-toggle' ] ],
			'bottom' => [ 'bottom_left' => [], 'bottom_center' => [], 'bottom_right' => [] ],
		],
		'header_html_content'  => '📞 <a href="tel:+48535263900">' . SB24_KONTAKT['telefon'] . '</a> &nbsp;·&nbsp; <a href="tel:+48774581046">' . SB24_KONTAKT['telefon_stacjonarny'] . '</a> &nbsp;·&nbsp; Salon: ' . SB24_KONTAKT['adres'] . ' &nbsp;·&nbsp; <a href="' . SB24_KONTAKT['facebook'] . '" target="_blank" rel="noopener">Facebook</a>',
		'header_social_items'  => [ 'items' => [ [ 'id' => 'facebook', 'enabled' => true, 'source' => 'icon', 'url' => '', 'imageid' => '', 'width' => 24, 'icon' => 'facebook', 'label' => 'Facebook' ] ] ],
		'facebook_link'        => SB24_KONTAKT['facebook'],
		'header_top_background'    => [ 'desktop' => [ 'color' => $m['czern'] ] ],
		'header_bottom_background' => [ 'desktop' => [ 'color' => '#ffffff' ] ],
		'header_bottom_top_border' => [ 'desktop' => [ 'width' => 1, 'unit' => 'px', 'style' => 'solid', 'color' => $m['ramka'] ] ],
		'base_font'            => [ 'size' => [ 'desktop' => 16 ], 'lineHeight' => [ 'desktop' => 1.7 ], 'family' => 'Open Sans', 'google' => true, 'weight' => '400', 'variant' => 'regular', 'color' => 'palette4' ],
		'load_fonts_local'     => true, // fonty z serwera sklepu, nie z Google (RODO)
		'footer_items'         => [
			'top'    => [ 'top_1' => [], 'top_2' => [], 'top_3' => [], 'top_4' => [], 'top_5' => [] ],
			'middle' => [ 'middle_1' => [ 'footer-widget1' ], 'middle_2' => [ 'footer-widget2' ], 'middle_3' => [ 'footer-widget3' ], 'middle_4' => [ 'footer-widget4' ], 'middle_5' => [] ],
			'bottom' => [ 'bottom_1' => [ 'footer-html' ], 'bottom_2' => [], 'bottom_3' => [], 'bottom_4' => [], 'bottom_5' => [] ],
		],
		'footer_middle_columns'    => '4',
		'footer_middle_background' => [ 'desktop' => [ 'color' => $m['czern'] ] ],
		'footer_bottom_background' => [ 'desktop' => [ 'color' => '#000000' ] ],
		'footer_html_content'      => '© {year} Świat Biżuterii 24 · Jubiler Opole · Wykonanie: <a href="https://www.nethouse.tech/" target="_blank" rel="noopener">NETHOUSE</a>',
		// Kategorie: filtry po lewej (osobny obszar — sidebar-primary zostaje na bloga), okruszki w nagłówku,
		// górny opis nie w nagłówku, tylko nad produktami (wypisuje go wtyczka, zwijany).
		'product_archive_layout'                    => 'left',
		'product_archive_sidebar_id'                => 'sidebar-secondary',
		'product_archive_title_element_description' => [ 'enabled' => false ],
		'product_archive_title_element_breadcrumb'  => [ 'enabled' => true, 'show_title' => true ],
		'product_archive_style'                     => 'action-visible', // bez wysuwania przy najechaniu (przycisk i tak usuwa wtyczka)
		// Blog: lista w 2 kolumnach kart (zdjęcie, tytuł, zajawka, „Czytaj dalej”) i wpis — oba z paskiem
		// bocznym po prawej. Bez autora i kategorii (jedna, domyślna); daty ukrywa wtyczka, gdy Shoper ich nie miał.
		'post_archive_home_title'                   => true,
		'post_archive_layout'                       => 'right',
		'post_archive_content_style'                => 'unboxed',
		'post_archive_columns'                      => '2',
		'post_archive_sidebar_id'                   => 'sidebar-primary',
		'post_archive_element_categories'           => [ 'enabled' => false, 'style' => 'normal', 'divider' => 'vline' ],
		'post_archive_element_meta'                 => [ 'id' => 'meta', 'enabled' => false ],
		'post_archive_element_feature'              => [ 'enabled' => true, 'ratio' => '9-16', 'size' => 'medium_large', 'imageLink' => true ],
		'post_archive_element_excerpt'              => [ 'enabled' => true, 'words' => 28, 'fullContent' => false ],
		'post_archive_element_readmore'             => [ 'enabled' => true, 'label' => 'Czytaj dalej' ],
		'post_layout'                               => 'right',
		'post_sidebar_id'                           => 'sidebar-primary',
		'post_title_element_meta'                   => [ 'id' => 'meta', 'enabled' => false ],
		'post_title_element_categories'             => [ 'enabled' => false, 'style' => 'normal', 'divider' => 'vline' ],
	] );
	sb24w_widgety( [ 'footer1', 'footer2', 'footer3', 'footer4' ] );
	sb24w_filtry( 'sidebar-secondary' );
	sb24w_bloki_w_obszarze( 'sidebar-primary', sb24w_pasek_bloga_bloki() );
	wp_update_custom_css_post( sb24w_css_wspolny() . <<<CSS

/* Kadence */
.site-top-header-wrap, .site-top-header-wrap a, .site-footer-row-container-inner, .site-footer a { color: #eee; }
.site-top-header-wrap a:hover, .site-footer a:hover { color: {$m['zloto']}; }
.site-footer h4 { color: {$m['zloto']}; font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.3rem; }
.site-footer ul { list-style: none; padding: 0; }
.site-footer li { margin: .35em 0; }
/* Menu główne: ciemne napisy, złota kreska pod pozycją (najechanie / bieżąca), jasne podmenu, długie w 2 kolumnach */
.site-bottom-header-wrap { border-bottom: 1px solid {$m['ramka']}; }
.header-navigation .header-menu-container .menu > li > a { position: relative; text-transform: uppercase; letter-spacing: .09em; font-size: 14px; font-weight: 500; color: {$m['czern']}; padding: 1.15em 1em; transition: color .2s; }
.header-navigation .header-menu-container .menu > li > a::after { content: ''; position: absolute; left: 1em; right: 1em; bottom: .75em; height: 1px; background: {$m['zloto_ciemne']}; transform: scaleX(0); transition: transform .25s ease; }
.header-navigation .header-menu-container .menu > li:is(:hover, :focus-within, .current-menu-item, .current-menu-ancestor) > a { color: {$m['zloto_ciemne']}; }
.header-navigation .header-menu-container .menu > li:is(:hover, :focus-within, .current-menu-item, .current-menu-ancestor) > a::after { transform: scaleX(1); }
.header-navigation .header-menu-container ul ul.sub-menu { background: #fff; border-top: 2px solid {$m['zloto']}; box-shadow: 0 14px 34px rgba(17, 17, 17, .1); padding: .6rem 0; }
.header-navigation .header-menu-container li:is(:hover, :focus-within, .menu-item--toggled-on) > ul.sub-menu:has(> li:nth-child(8)) { display: grid; grid-template-columns: repeat(2, auto); } /* tylko otwarte — zamknięte ukrywa Kadence przez display: none */
.header-navigation .header-menu-container ul ul li.menu-item { border-bottom: 0; }
.header-navigation .header-menu-container ul ul li.menu-item > a { width: 220px; padding: .55em 1.5em; color: {$m['tekst']}; font-size: 14.5px; text-transform: none; letter-spacing: 0; }
.header-navigation .header-menu-container ul ul li.menu-item > a:hover, .header-navigation .header-menu-container ul ul li.menu-item.current-menu-item > a { color: {$m['zloto_ciemne']}; background: {$m['tlo_jasne']}; }
/* Wyszukiwarka w nagłówku: pole zamiast przycisku z ciemną nakładką (formularz z wtyczki sb24-migracja) */
.sb24-szukaj { display: flex; width: 280px; border: 1px solid {$m['ramka']}; border-radius: 3px; background: #fff; }
.sb24-szukaj input[type=search] { flex: 1; min-width: 0; border: 0; padding: .55rem .8rem; font-size: 14px; background: transparent; box-shadow: none; }
.sb24-szukaj button { border: 0; background: transparent; color: {$m['zloto_ciemne']}; padding: 0 .75rem; cursor: pointer; box-shadow: none; }
.sb24-szukaj:focus-within { border-color: {$m['zloto_ciemne']}; }
.sb24-szukaj button:hover { background: transparent; color: {$m['czern']}; }
/* Kafelki produktów: cały kafelek to link do produktu, po najechaniu powiększa się samo zdjęcie */
.woocommerce ul.products li.product { position: relative; }
.woocommerce ul.products li.product .woocommerce-loop-product__title a::after { content: ''; position: absolute; inset: 0; z-index: 1; }
.woocommerce ul.products li.product .woocommerce-loop-image-link { display: block; overflow: hidden; }
.woocommerce ul.products li.product .woocommerce-loop-image-link img { transition: transform .5s ease; }
.woocommerce ul.products li.product:hover .woocommerce-loop-image-link img { transform: scale(1.07); }
.woocommerce ul.products li.product .product-action-wrap:empty { display: none; }
body { overflow-x: clip; } /* sekcje alignfull Kadence (np. podobne wpisy) liczą 100vw razem z paskiem przewijania */
CSS, [ 'stylesheet' => 'kadence' ] );
	WP_CLI::success( 'Kadence ustawiony.' );
}

/* ------------------------------------------------------------ Blocksy */

function sb24w_blocksy() {
	if ( ! function_exists( 'blocksy_manager' ) ) {
		WP_CLI::error( 'Blocksy musi być aktywny: skrypty/wp.sh theme activate blocksy' );
	}
	$w = get_option( 'sb24_wyglad' );
	$m = SB24_MARKA;

	// Nagłówek: logo | szukaj + koszyk, menu w osobnym pasku pod spodem; telefon i adres w górnym pasku.
	$naglowek = blocksy_manager()->header_builder->get_default_value();
	foreach ( $naglowek['sections'][0]['desktop'] as &$wiersz ) {
		$uklad = [
			'top-row'    => [ 'start' => [ 'text' ], 'end' => [ 'socials' ] ],
			'middle-row' => [ 'start' => [ 'logo' ], 'end' => [ 'search', 'cart' ] ],
			'bottom-row' => [ 'middle' => [ 'menu' ] ],
		][ $wiersz['id'] ] ?? null;
		if ( $uklad ) {
			foreach ( $wiersz['placements'] as &$p ) {
				$p['items'] = $uklad[ $p['id'] ] ?? [];
			}
			unset( $p );
		}
	}
	unset( $wiersz );
	foreach ( $naglowek['sections'][0]['mobile'] as &$wiersz ) {
		if ( 'middle-row' === $wiersz['id'] ) {
			foreach ( $wiersz['placements'] as &$p ) {
				$p['items'] = [ 'start' => [ 'logo' ], 'end' => [ 'cart', 'trigger' ] ][ $p['id'] ] ?? [];
			}
			unset( $p );
		}
	}
	unset( $wiersz );
	$naglowek['sections'][0]['items'] = [
		[ 'id' => 'text', 'values' => [ 'header_text' => '📞 <a href="tel:+48535263900">' . SB24_KONTAKT['telefon'] . '</a> &nbsp;·&nbsp; <a href="tel:+48774581046">' . SB24_KONTAKT['telefon_stacjonarny'] . '</a> &nbsp;·&nbsp; Salon: ' . SB24_KONTAKT['adres'] ] ],
		[ 'id' => 'socials', 'values' => [ 'header_socials' => [ [ 'id' => 'facebook', 'enabled' => true ] ] ] ],
		[ 'id' => 'logo', 'values' => [ 'logoMaxHeight' => [ 'mobile' => 36, 'tablet' => 44, 'desktop' => 54 ], 'has_site_title' => 'no' ] ],
	];

	$stopka_b = new Blocksy_Footer_Builder();
	$stopka   = [ 'current_section' => 'type-1', 'sections' => [ $stopka_b->get_structure_for( [
		'id'   => 'type-1',
		'rows' => [
			'middle-row' => [ 'columns' => [ [ 'widget-area-1' ], [ 'widget-area-2' ], [ 'widget-area-3' ], [ 'widget-area-4' ] ] ],
			'bottom-row' => [ 'columns' => [ [ 'copyright' ] ] ],
		],
	] ) ] ];
	$stopka['sections'][0]['items'] = [ [ 'id' => 'copyright', 'values' => [ 'copyright_text' => '© {current_year} Świat Biżuterii 24 · Jubiler Opole · Wykonanie: <a href="https://www.nethouse.tech/" target="_blank" rel="noopener">NETHOUSE</a>' ] ] ];

	$paleta = [];
	foreach ( [ $m['zloto_ciemne'], $m['zloto_hover'], $m['tekst'], $m['czern'], $m['ramka'], $m['tlo_jasne'], '#fcfbf7', '#ffffff' ] as $i => $kolor ) {
		$paleta[ 'color' . ( $i + 1 ) ] = [ 'color' => $kolor ];
	}
	sb24w_mods( 'blocksy', [
		'custom_logo'        => $w['logo'],
		'nav_menu_locations' => [ 'menu_1' => $w['menu'], 'menu_mobile' => $w['menu'] ],
		'header_placements'  => $naglowek,
		'footer_placements'  => $stopka,
		'colorPalette'       => $paleta,
		'rootTypography'     => [ 'family' => 'Open Sans', 'variation' => 'n4', 'size' => '16px', 'line-height' => '1.7', 'letter-spacing' => '0em', 'text-transform' => 'none', 'text-decoration' => 'none' ],
		'facebook'           => SB24_KONTAKT['facebook'],
		'woocommerce_catalog_columns' => 4,
	] );
	sb24w_widgety( [ 'ct-footer-sidebar-1', 'ct-footer-sidebar-2', 'ct-footer-sidebar-3', 'ct-footer-sidebar-4' ] );
	wp_update_custom_css_post( sb24w_css_wspolny() . <<<CSS

/* Blocksy */
[data-row="top"], [data-row="top"] *, [data-row="top"] a { background: {$m['czern']}; color: #eee !important; font-size: 13px; }
[data-row="top"] a:hover { color: {$m['zloto']}; }
[data-row="bottom"] { border-top: 1px solid {$m['ramka']}; }
[data-row="bottom"] .menu > li > a { text-transform: uppercase; letter-spacing: .06em; font-size: 13px; }
.ct-footer { background: {$m['czern']}; color: #ddd; }
.ct-footer a { color: #ddd; }
.ct-footer a:hover { color: {$m['zloto']}; }
.ct-footer h4 { color: {$m['zloto']}; font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.3rem; }
.ct-footer ul { list-style: none; padding: 0; }
.ct-footer [data-row="bottom"] { background: #000; border: 0; }
CSS, [ 'stylesheet' => 'blocksy' ] );
	// Blocksy zapisuje CSS z ustawień do pliku (uploads/blocksy/css/global.css) tylko przy zapisie
	// w panelu — po zmianie ustawień z kodu trzeba go przebudować.
	do_action( 'blocksy:dynamic-css:refresh-caches' );
	WP_CLI::success( 'Blocksy ustawiony.' );
}

/* ------------------------------------------------------------ Astra */

/**
 * Astra (darmowa): ten sam układ co Kadence — czarny pasek z telefonem, logo | szukaj + koszyk, menu
 * w pasku pod spodem, 4 kolumny stopki. Banery kategorii, stron i bloga: „Banner Layout 2” Astry
 * (pełna szerokość nad treścią), zdjęcie podstawia wtyczka (baner.css). Ustawienia w opcji `astra-settings`.
 * Bez importu „Starter Templates” — nadpisałby strony, menu i ustawienia sklepu treścią demo.
 */
function sb24w_astra() {
	if ( ! defined( 'ASTRA_THEME_VERSION' ) ) {
		WP_CLI::error( 'Astra musi być aktywna: skrypty/wp.sh theme activate astra' );
	}
	$w     = get_option( 'sb24_wyglad' );
	$m     = SB24_MARKA;
	$puste = fn( $p, $n = 5 ) => array_fill_keys( 5 === $n
		? array_map( fn( $s ) => "{$p}_$s", [ 'left', 'left_center', 'center', 'right_center', 'right' ] )
		: array_map( fn( $s ) => "{$p}_$s", [ 'left', 'center', 'right' ] ), [] );
	$tlo   = fn( $kolor ) => [ 'desktop' => [ 'background-color' => $kolor, 'background-type' => 'color' ], 'tablet' => [ 'background-color' => '' ], 'mobile' => [ 'background-color' => '' ] ];
	$ust   = array_replace( get_option( 'astra-settings', [] ) ?: [], [
		// Paleta Astry: 0 marka, 1 marka (hover), 2 nagłówki, 3 tekst, 4 tło, 5 tło pomocnicze, 6 tło ciemne, 7 obramowania, 8 inne
		'global-color-palette'             => [ 'palette' => [ $m['zloto_ciemne'], $m['zloto_hover'], $m['czern'], $m['tekst'], '#ffffff', $m['tlo_jasne'], $m['czern'], $m['ramka'], $m['tekst_jasny'] ] ],
		'body-font-family'                 => "'Open Sans', sans-serif",
		'body-font-variant'                => '400',
		'body-font-weight'                 => '400',
		'font-size-body'                   => [ 'desktop' => 16, 'tablet' => '', 'mobile' => '', 'desktop-unit' => 'px', 'tablet-unit' => 'px', 'mobile-unit' => 'px' ],
		'body-font-extras'                 => [ 'line-height' => '1.7', 'line-height-unit' => '', 'letter-spacing' => '', 'letter-spacing-unit' => 'px', 'text-transform' => '', 'text-decoration' => '' ],
		'headings-font-family'             => "'Cormorant Garamond', serif",
		'headings-font-weight'             => '600',
		'load-google-fonts-locally'        => true, // fonty z serwera sklepu, nie z Google (RODO)
		'display-site-title-responsive'    => [ 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ],
		'display-site-tagline-responsive'  => [ 'desktop' => 0, 'tablet' => 0, 'mobile' => 0 ],
		'ast-header-responsive-logo-width' => [ 'desktop' => 280, 'tablet' => 240, 'mobile' => 200 ],
		// Nagłówek
		'header-desktop-items'             => [
			'popup'   => [ 'popup_content' => [ 'mobile-menu' ] ],
			'above'   => array_replace( $puste( 'above' ), [ 'above_left' => [ 'html-1' ] ] ),
			'primary' => array_replace( $puste( 'primary' ), [ 'primary_left' => [ 'logo' ], 'primary_right' => [ 'search', 'woo-cart' ] ] ),
			'below'   => array_replace( $puste( 'below' ), [ 'below_center' => [ 'menu-1' ] ] ),
		],
		'header-mobile-items'              => [
			'popup'   => [ 'popup_content' => [ 'mobile-menu' ] ],
			'above'   => $puste( 'above', 3 ),
			'primary' => array_replace( $puste( 'primary', 3 ), [ 'primary_left' => [ 'logo' ], 'primary_right' => [ 'woo-cart', 'mobile-trigger' ] ] ),
			'below'   => $puste( 'below', 3 ),
		],
		'header-html-1'                    => '📞 <a href="tel:+48535263900">' . SB24_KONTAKT['telefon'] . '</a> &nbsp;·&nbsp; <a href="tel:+48774581046">' . SB24_KONTAKT['telefon_stacjonarny'] . '</a> &nbsp;·&nbsp; Salon: ' . SB24_KONTAKT['adres'] . ' &nbsp;·&nbsp; <a href="' . SB24_KONTAKT['facebook'] . '" target="_blank" rel="noopener">Facebook</a>',
		'hba-header-bg-obj-responsive'     => $tlo( $m['czern'] ),
		'hba-header-height'                => [ 'desktop' => 40, 'tablet' => '', 'mobile' => '' ],
		'hba-header-separator'             => 0,
		'hbb-header-bg-obj-responsive'     => $tlo( '#ffffff' ),
		'hbb-header-separator'             => 1,
		'hbb-header-bottom-border-color'   => $m['ramka'],
		'hb-header-main-sep'               => 0,
		// Stopka: 4 kolumny widgetów (wspólne bloki), pod spodem prawa autorskie
		'footer-desktop-items'             => [
			'above'   => array_fill_keys( [ 'above_1', 'above_2', 'above_3', 'above_4', 'above_5' ], [] ),
			'primary' => [ 'primary_1' => [ 'widget-1' ], 'primary_2' => [ 'widget-2' ], 'primary_3' => [ 'widget-3' ], 'primary_4' => [ 'widget-4' ], 'primary_5' => [] ],
			'below'   => [ 'below_1' => [ 'copyright' ], 'below_2' => [], 'below_3' => [], 'below_4' => [], 'below_5' => [] ],
		],
		'hb-footer-column'                 => '4',
		'hb-footer-layout'                 => [ 'desktop' => '4-equal', 'tablet' => '2-equal', 'mobile' => 'full' ],
		'hb-footer-bg-obj-responsive'      => $tlo( $m['czern'] ),
		'hbb-footer-bg-obj-responsive'     => $tlo( '#000000' ),
		'hbb-footer-separator'             => 0,
		'footer-copyright-editor'          => '© [current_year] Świat Biżuterii 24 · Jubiler Opole · Wykonanie: <a href="https://www.nethouse.tech/" target="_blank" rel="noopener">NETHOUSE</a>',
		// Sklep: filtry po lewej, 4 produkty w rzędzie, karty bez przycisku (jak Kadence), okruszki na produkcie
		'archive-product-sidebar-layout'   => 'left-sidebar',
		'single-product-sidebar-layout'    => 'no-sidebar',
		'shop-grids'                       => [ 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ],
		'shop-product-structure'           => [ 'title', 'ratings', 'price' ],
		'single-product-breadcrumb-disable' => false,
		'ast-archive-product-title'        => true,
		'ast-dynamic-archive-product-layout' => 'layout-2',
		// W banerze tylko tytuł i okruszki — opis kategorii wypisuje wtyczka nad produktami (zwijany).
		'ast-dynamic-archive-product-structure' => [ 'ast-dynamic-archive-product-breadcrumb', 'ast-dynamic-archive-product-title' ],
		'ast-dynamic-archive-post-structure'    => [ 'ast-dynamic-archive-post-title' ],
		'ast-dynamic-single-page-structure'     => [ 'ast-dynamic-single-page-breadcrumb', 'ast-dynamic-single-page-title' ],
		// Strony i blog: baner nad treścią; blog z paskiem po prawej, bez autora i dat
		'ast-dynamic-single-page-layout'   => 'layout-2',
		'ast-dynamic-archive-post-layout'  => 'layout-2',
		'ast-dynamic-archive-post-banner-on-blog' => true,
		'archive-post-sidebar-layout'      => 'right-sidebar',
		'single-post-sidebar-layout'       => 'right-sidebar',
		'blog-layout'                      => 'blog-layout-4',
		'blog-grid'                        => 2,
		'blog-post-structure'              => [ 'image', 'title', 'excerpt' ],
		'blog-meta'                        => [],
		'ast-dynamic-single-post-metadata' => [],
	] );
	update_option( 'astra-settings', $ust );
	sb24w_mods( 'astra', [
		'custom_logo'        => $w['logo'],
		'nav_menu_locations' => [ 'primary' => $w['menu'], 'mobile_menu' => $w['menu'] ],
	] );
	// Strona główna: bez tytułu i banera, na pełną szerokość (układ z wtyczki, glowna.css).
	$glowna = (int) get_option( 'page_on_front' );
	foreach ( [ 'site-post-title' => 'disabled', 'ast-banner-title-visibility' => 'disabled', 'ast-site-content-layout' => 'full-width-container', 'site-content-style' => 'unboxed', 'site-sidebar-layout' => 'no-sidebar' ] as $k => $v ) {
		update_post_meta( $glowna, $k, $v );
	}
	sb24w_widgety( [ 'footer-widget-1', 'footer-widget-2', 'footer-widget-3', 'footer-widget-4' ] );
	sb24w_filtry( 'astra-woo-shop-sidebar' );
	sb24w_bloki_w_obszarze( 'sidebar-1', sb24w_pasek_bloga_bloki() );
	wp_update_custom_css_post( sb24w_css_wspolny() . <<<CSS

/* Astra */
.ast-above-header-bar, .ast-above-header-bar a { color: #eee; font-size: 13px; }
.ast-above-header-bar a:hover { color: {$m['zloto']}; }
.ast-below-header-bar .main-header-menu > .menu-item > .menu-link { text-transform: uppercase; letter-spacing: .04em; font-size: 12px; padding-left: .7em; padding-right: .7em; }
.site-primary-footer-wrap, .site-primary-footer-wrap a { color: #ddd; }
.site-footer a:hover { color: {$m['zloto']}; }
.site-footer h4 { color: {$m['zloto']}; font-size: 1.3rem; }
.site-footer ul { list-style: none; margin: 0; padding: 0; }
.site-footer li { margin: .35em 0; }
.site-below-footer-wrap, .site-below-footer-wrap a { color: #bbb; }
.single-product .product_title { font-size: clamp(1.8rem, 3vw, 2.4rem); line-height: 1.2; }
.woocommerce ul.products li.product .woocommerce-loop-product__title { font-size: 1.1rem; }
CSS, [ 'stylesheet' => 'astra' ] );
	WP_CLI::success( 'Astra ustawiona.' );
}

$tryb = $args[0] ?? '';
match ( $tryb ) {
	'wspolne' => sb24w_wspolne(),
	'kadence' => sb24w_kadence(),
	'blocksy' => sb24w_blocksy(),
	'astra'   => sb24w_astra(),
	default   => WP_CLI::error( 'Tryb: wspolne | kadence | blocksy | astra' ),
};
