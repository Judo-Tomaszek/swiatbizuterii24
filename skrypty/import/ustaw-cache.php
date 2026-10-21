<?php
/**
 * WP Fastest Cache — ustawienia sklepu (zapis jak przyciskiem „Zapisz” w panelu, razem z regułami w .htaccess).
 *
 *   skrypty/wp.sh plugin install wp-fastest-cache --activate
 *   skrypty/wp.sh eval-file /import/skrypty/ustaw-cache.php
 *
 * - Cache stron dla niezalogowanych; zalogowani (klienci w „Moje konto”, panel) zawsze dostają świeżą stronę.
 *   Koszyk, kasę i „Moje konto” wtyczka pomija sama (strony WooCommerce + DONOTCACHEPAGE), adresy z ?zapytaniem
 *   (wyszukiwarka, filtry, ?wyslano formularza) i POST — też.
 * - Ta sama wersja na telefon i komputer (motyw responsywny).
 * - Czyszczenie: nowy wpis/produkt → cały cache; edycja → cały cache (sklep jest mały, kategorie pokazują produkty).
 * - Wstępne ładowanie (preload) przez WP-Cron: strona główna, strony, wpisy, produkty, kategorie — 4 strony na minutę.
 * - Minifikacja HTML i CSS, łączenie CSS, gzip, cache przeglądarki, bez emoji WordPressa.
 *   Łączenie JS wyłączone: zysk mały, a ryzyko dla skryptów zgód/GTM i WooCommerce.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}
if ( ! class_exists( 'WpFastestCache' ) ) {
	WP_CLI::error( 'Brak aktywnej wtyczki WP Fastest Cache.' );
}

$_POST = [
	'wpFastestCachePage'                     => 'options',
	'wpFastestCacheStatus'                   => 'on',
	'wpFastestCacheLoggedInUser'             => 'on', // = nie pokazuj cache zalogowanym
	'wpFastestCacheNewPost'                  => 'on',
	'wpFastestCacheNewPost_type'             => 'all',
	'wpFastestCacheUpdatePost'               => 'on',
	'wpFastestCacheUpdatePost_type'          => 'all',
	'wpFastestCacheMinifyHtml'               => 'on',
	'wpFastestCacheMinifyCss'                => 'on',
	'wpFastestCacheCombineCss'               => 'on',
	'wpFastestCacheGzip'                     => 'on',
	'wpFastestCacheLBC'                      => 'on',
	'wpFastestCacheDisableEmojis'            => 'on',
	'wpFastestCachePreload'                  => 'on',
	'wpFastestCachePreload_homepage'         => 'on',
	'wpFastestCachePreload_page'             => 'on',
	'wpFastestCachePreload_post'             => 'on',
	'wpFastestCachePreload_customposttypes'  => 'on',
	'wpFastestCachePreload_category'         => 'on',
	'wpFastestCachePreload_custom'           => 'on',
	'wpFastestCachePreload_number'           => '4',
	'wpFastestCachePreload_restart'          => 'on',
	'wpFastestCacheLanguage'                 => 'eng',
];
wp_set_current_user( (int) ( get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] )[0] ?? 0 ) ); // wtyczka zapisuje tylko jako administrator
$_SERVER['HTTP_HOST'] = (string) wp_parse_url( home_url(), PHP_URL_HOST ) . ( wp_parse_url( home_url(), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url(), PHP_URL_PORT ) : '' ); // reguły .htaccess biorą domenę z żądania
require_once WP_PLUGIN_DIR . '/wp-fastest-cache/inc/admin.php';
$admin = new WpFastestCacheAdmin();
$admin->saveOption();
$zapisane = json_decode( (string) get_option( 'WpFastestCache' ), true );
if ( empty( $zapisane['wpFastestCacheStatus'] ) ) {
	WP_CLI::error( 'Ustawienia nie zapisane (sprawdź uprawnienia do .htaccess i wp-content/cache).' );
}
WP_CLI::success( 'WP Fastest Cache: ' . count( $zapisane ) . ' ustawień zapisanych, reguły w .htaccess: '
	. ( false !== strpos( (string) file_get_contents( ABSPATH . '.htaccess' ), 'WpFastestCache' ) ? 'tak' : 'NIE' ) );
