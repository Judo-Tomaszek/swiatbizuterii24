<?php
/**
 * Strony informacyjne z nowym wyglądem i adresem — treść z tresci-poprawione/<slug>.html, stare adresy Shopera
 * w SB24_NOWE_ADRESY (wtyczka sb24-migracja) dają 301 na nowe. Idempotentny: lokalnie i na serwerze to samo.
 * Uruchom: skrypty/wp.sh eval-file /import/skrypty/strony-informacyjne.php
 *   (na serwerze: wp eval-file strony-informacyjne.php — obok katalog tresci-poprawione/)
 */
const SB24S_STRONY = [ 'formy-platnosci', 'czas-i-koszty-dostawy', 'jak-i-kiedy-dostarczymy-twoja-przesylke', 'dlaczego-my' ];

foreach ( SB24S_STRONY as $slug ) {
	$strona = get_page_by_path( $slug );
	$plik   = __DIR__ . "/tresci-poprawione/$slug.html";
	if ( ! $strona || ! is_readable( $plik ) ) {
		WP_CLI::warning( "pomijam $slug: " . ( $strona ? 'brak pliku' : 'brak strony' ) );
		continue;
	}
	wp_update_post( [ 'ID' => $strona->ID, 'post_content' => file_get_contents( $plik ) ] );
	WP_CLI::log( "treść: $slug ({$strona->ID})" );
	$strony_id[] = $strona->ID;
}

// linki w stopce (bloki widżetów) i w menu: stare adresy → nowe, bez czekania na 301
$bloki = get_option( 'widget_block', [] );
$zmiany = 0;
foreach ( $bloki as $nr => $blok ) {
	if ( is_array( $blok ) && isset( $blok['content'] ) ) {
		$nowa = $blok['content'];
		foreach ( SB24_NOWE_ADRESY as $stary => $nowy ) {
			$nowa = str_replace( "\"$stary\"", "\"$nowy\"", $nowa );
		}
		if ( $nowa !== $blok['content'] ) {
			$bloki[ $nr ]['content'] = $nowa;
			$zmiany++;
		}
	}
}
update_option( 'widget_block', $bloki );
WP_CLI::log( "stopka: zmienione bloki $zmiany" );

foreach ( get_posts( [ 'post_type' => 'nav_menu_item', 'numberposts' => -1, 'meta_key' => '_menu_item_type', 'meta_value' => 'custom' ] ) as $el ) {
	$url  = get_post_meta( $el->ID, '_menu_item_url', true );
	$sciezka = wp_parse_url( $url, PHP_URL_PATH );
	if ( isset( SB24_NOWE_ADRESY[ $sciezka ] ) ) {
		update_post_meta( $el->ID, '_menu_item_url', SB24_NOWE_ADRESY[ $sciezka ] );
		WP_CLI::log( "menu: $sciezka → " . SB24_NOWE_ADRESY[ $sciezka ] );
	}
}

sb24_przebuduj_adresy();

// Yoast trzyma adres strony w swojej tabeli — bez tego canonical i sitemap zostają przy starym adresie Shopera
if ( function_exists( 'YoastSEO' ) ) {
	$repo = YoastSEO()->classes->get( Yoast\WP\SEO\Repositories\Indexable_Repository::class );
	foreach ( $strony_id ?? [] as $id ) {
		$ind = $repo->find_by_id_and_type( $id, 'post' );
		$ind->permalink = get_permalink( $id );
		$ind->save();
	}
}
if ( isset( $GLOBALS['wp_fastest_cache'] ) ) {
	$GLOBALS['wp_fastest_cache']->deleteCache( true );
}
WP_CLI::success( 'strony informacyjne gotowe' );
