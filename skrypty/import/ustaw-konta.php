<?php
/**
 * Konta klientów: rejestracja + polskie adresy podstron konta (docs/plany/konfiguracja-sklepu.md, pkt 10–11).
 *
 *   skrypty/wp.sh eval-file /import/skrypty/ustaw-konta.php
 *
 * - Rejestracja na „Moje konto” i przy zamówieniu; zakup bez konta zostaje (jak w Shoperze).
 * - Klient sam ustawia hasło (nie dostaje wygenerowanego e-mailem); login tworzony z e-maila.
 * - Podstrony konta po polsku. Uwaga: końcówki są globalne (działają przy każdej stronie), więc
 *   żadna nie może się nazywać jak istniejąca strona — np. „zamowienie” to adres kasy, stąd
 *   „szczegoly-zamowienia”.
 * 301 ze starych `/pl/login`, `/pl/panel`, `/pl/reg` → `/moje-konto/` robi wtyczka sb24-migracja,
 * ochronę rejestracji przed botami też (sekcja „konta klientów”).
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

$opcje = [
	'woocommerce_enable_myaccount_registration'         => 'yes',
	'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
	'woocommerce_enable_guest_checkout'                 => 'yes',
	'woocommerce_enable_checkout_login_reminder'        => 'yes',
	'woocommerce_registration_generate_password'        => 'no',
	'woocommerce_registration_generate_username'        => 'yes',
	// Podstrony „Moje konto”
	'woocommerce_myaccount_orders_endpoint'             => 'zamowienia',
	'woocommerce_myaccount_view_order_endpoint'         => 'szczegoly-zamowienia',
	'woocommerce_myaccount_downloads_endpoint'          => 'pliki',
	'woocommerce_myaccount_edit_account_endpoint'       => 'edytuj-konto',
	'woocommerce_myaccount_edit_address_endpoint'       => 'adresy',
	'woocommerce_myaccount_payment_methods_endpoint'    => 'metody-platnosci',
	'woocommerce_myaccount_lost_password_endpoint'      => 'odzyskaj-haslo',
	'woocommerce_logout_endpoint'                       => 'wyloguj',
	'woocommerce_myaccount_add_payment_method_endpoint' => 'dodaj-metode-platnosci',
	'woocommerce_myaccount_delete_payment_method_endpoint'      => 'usun-metode-platnosci',
	'woocommerce_myaccount_set_default_payment_method_endpoint' => 'domyslna-metoda-platnosci',
	// Podstrony kasy
	'woocommerce_checkout_pay_endpoint'                 => 'zaplac',
	'woocommerce_checkout_order_received_endpoint'      => 'przyjete',
];

// Kontrola kolizji z istniejącymi stronami/wpisami.
global $wpdb;
$koncowki = array_filter( $opcje, fn( $k ) => str_ends_with( $k, '_endpoint' ), ARRAY_FILTER_USE_KEY );
foreach ( $koncowki as $slug ) {
	$kolizja = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' AND post_type IN ('page','post') LIMIT 1", $slug ) );
	if ( $kolizja ) {
		WP_CLI::error( "Końcówka „{$slug}” koliduje ze stroną #{$kolizja}" );
	}
}

foreach ( $opcje as $klucz => $wartosc ) {
	update_option( $klucz, $wartosc );
}
// Nowe końcówki muszą trafić do reguł przepisywania.
WC()->query->init_query_vars();
WC()->query->add_endpoints();
flush_rewrite_rules( false );
WP_CLI::success( 'Konta: rejestracja włączona, podstrony po polsku (' . count( $koncowki ) . ' końcówek).' );
