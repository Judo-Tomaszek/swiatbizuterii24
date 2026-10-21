<?php
/**
 * Konfiguracja Yoast SEO + przepisanie title/meta z Shopera do pól Yoasta.
 *
 *   skrypty/wp.sh eval-file /import/skrypty/ustaw-seo.php && skrypty/wp.sh yoast index --reindex
 *
 * Źródło title/meta: meta `_sb24_seo_title` / `_sb24_seo_description` zostawione przez importy
 * (z migawki strony — to widzi Google). Tekst przepisujemy DOSŁOWNIE, z literówkami
 * (docs/strony-kluczowe-seo-2026-10-16.md). Szablony Yoasta działają tylko tam, gdzie Shoper nie dał
 * nic (koszyk, nowe produkty itp.) i naśladują wzór Shopera: „Nazwa | Jubiler Opole | Swiatbiżuterii24”.
 * Skrypt można puszczać wielokrotnie — nadpisuje pola Yoasta wartościami z Shopera.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
if ( ! defined( 'WPSEO_VERSION' ) ) {
	WP_CLI::error( 'Yoast SEO nie jest aktywny.' );
}

const SB24_SEO_MARKA = 'Jubiler Opole %%sep%% Swiatbiżuterii24';
const SB24_SEO_KLUCZOWE = [ // strony z 1. miejsc w Google — kontrola 1:1 na końcu
	'/'                        => [ 'Jubiler Opole | Sklep jubilerski Swiatbiżuterii24', 'Odwieź nasz sklep jubilerski w Opolu przy ulicy Ozimskiej. Szeroki wybór wyrobów ze złota i srebra. Pierścionki zaręczynowe, obrączki ślubne' ],
	'/naprawa-bizuterii-opole' => [ 'Naprawa Biżuterii Opole | ŚwiatBiżuterii24.pl', 'Twoja ukochana biżuteria uległa uszkodzeniu? Sprawdź naszą ofertę. Profesjonalna naprawia biżuterii Opole' ],
	'/skup-zlota-opole'        => [ 'Skup złota Opole | Jubiler Opole | Swiatbizuterii24', 'Skup złota Opole - Najlepsze ceny, dyskrecja, pełne zadowolenie. Korzystając z usług naszego skupu złota będziesz w pełni zadowolony' ],
];

/* ------------------------------------------------------------ ustawienia ogólne */

$tytuly = get_option( 'wpseo_titles' );
$tytuly = array_merge( $tytuly, [
	'separator'                  => 'sc-pipe', // „|” jak w Shoperze
	'company_or_person'          => 'company',
	'company_name'               => 'Świat Biżuterii 24',
	'website_name'               => 'Świat Biżuterii 24',
	'title-home-wpseo'           => SB24_SEO_KLUCZOWE['/'][0], // gdyby główna pokazywała wpisy; przy stronie statycznej liczy się meta strony
	'metadesc-home-wpseo'        => SB24_SEO_KLUCZOWE['/'][1],
	'title-page'                 => '%%title%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-post'                 => 'Blog - %%title%% %%page%% %%sep%% ' . SB24_SEO_MARKA, // wzór wpisów Shopera
	'title-product'              => '%%title%% %%sep%% ' . SB24_SEO_MARKA,
	'metadesc-product'           => '%%title%% w kategorii %%ct_product_cat%%',
	'title-tax-product_cat'      => '%%term_title%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-tax-product_brand'    => '%%term_title%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-tax-product_tag'      => '%%term_title%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-tax-category'         => 'Blog - %%term_title%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-archive-wpseo'        => '%%date%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-search-wpseo'         => 'Szukaj: %%searchphrase%% %%page%% %%sep%% ' . SB24_SEO_MARKA,
	'title-404-wpseo'            => 'Nie znaleziono strony %%sep%% ' . SB24_SEO_MARKA,
	'title-ptarchive-product'    => 'Sklep %%page%% %%sep%% ' . SB24_SEO_MARKA,
	// Archiwa, których Shoper nie miał: autorzy, daty, formaty — wyłączone (nie produkują cienkich stron).
	'disable-author'             => true,
	'disable-date'               => true,
	'disable-post_format'        => true,
	'disable-attachment'         => true, // załącznik → przekierowanie na plik
	'noindex-tax-product_tag'    => true,
	'noindex-tax-post_tag'       => true,
	'breadcrumbs-enable'         => false, // okruszki daje motyw
] );
$logo = (int) get_theme_mod( 'custom_logo' );
if ( $logo ) {
	$tytuly['company_logo']    = wp_get_attachment_url( $logo );
	$tytuly['company_logo_id'] = $logo;
}
update_option( 'wpseo_titles', $tytuly );

$social                  = get_option( 'wpseo_social' );
$social['facebook_site'] = 'https://www.facebook.com/swiatbizuterii24';
$social['opengraph']     = true;
update_option( 'wpseo_social', $social );

$wpseo = get_option( 'wpseo' );
$wpseo = array_merge( $wpseo, [ 'enable_xml_sitemap' => true, 'show_onboarding_notice' => false ] );
update_option( 'wpseo', $wpseo );

/* ------------------------------------------------------------ title/meta z Shopera */

global $wpdb;
$posty = $wpdb->get_results( "SELECT p.ID, t.meta_value AS tytul, d.meta_value AS opis FROM {$wpdb->posts} p
	LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_sb24_seo_title'
	LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = '_sb24_seo_description'
	WHERE t.meta_value <> '' OR d.meta_value <> ''" );
foreach ( $posty as $p ) {
	if ( '' !== (string) $p->tytul ) {
		update_post_meta( $p->ID, '_yoast_wpseo_title', wp_slash( $p->tytul ) );
	}
	if ( '' !== (string) $p->opis ) {
		update_post_meta( $p->ID, '_yoast_wpseo_metadesc', wp_slash( $p->opis ) );
	}
}
WP_CLI::log( 'Wpisy/strony/produkty z title/meta z Shopera: ' . count( $posty ) );

$termy = 0;
foreach ( get_terms( [ 'taxonomy' => array_keys( get_taxonomies( [ 'public' => true ] ) ), 'hide_empty' => false ] ) as $t ) {
	$tytul = (string) get_term_meta( $t->term_id, '_sb24_seo_title', true );
	$opis  = (string) get_term_meta( $t->term_id, '_sb24_seo_description', true );
	if ( '' === $tytul && '' === $opis ) {
		continue;
	}
	WPSEO_Taxonomy_Meta::set_values( $t->term_id, $t->taxonomy, array_filter( [ 'wpseo_title' => $tytul, 'wpseo_desc' => $opis ] ) );
	++$termy;
}
WP_CLI::log( "Kategorie/producenci z title/meta z Shopera: $termy" );

// Koszyk, zamówienie, konto: noindex i poza mapą witryny (darmowy Yoast sam ich nie wyklucza).
foreach ( [ 'cart', 'checkout', 'myaccount' ] as $strona ) {
	if ( wc_get_page_id( $strona ) > 0 ) {
		update_post_meta( wc_get_page_id( $strona ), '_yoast_wpseo_meta-robots-noindex', 1 );
	}
}

/* ------------------------------------------------------------ kontrola stron kluczowych */

foreach ( SB24_SEO_KLUCZOWE as $sciezka => [ $tytul, $opis ] ) {
	$id = '/' === $sciezka ? (int) get_option( 'page_on_front' ) : (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sb24_sciezka' AND meta_value = %s", $sciezka
	) );
	if ( ! $id ) {
		WP_CLI::warning( "$sciezka: brak strony" );
		continue;
	}
	// Strony kluczowe zapisujemy wprost z dokumentu, niezależnie od importu.
	update_post_meta( $id, '_yoast_wpseo_title', wp_slash( $tytul ) );
	update_post_meta( $id, '_yoast_wpseo_metadesc', wp_slash( $opis ) );
	WP_CLI::log( "$sciezka → #$id ustawione" );
}
