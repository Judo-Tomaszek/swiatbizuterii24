<?php
/**
 * Import kont klientów z Shopera (dane/eksport/konta/klienci.json, buduje skrypty/eksport/buduj-konta.mjs).
 *
 *   skrypty/wp.sh eval-file /import/skrypty/importuj-konta.php            — import / aktualizacja
 *   skrypty/wp.sh eval-file /import/skrypty/importuj-konta.php testowe    — konto testowe (wypisuje login i hasło)
 *
 * - Rola `customer`, losowe hasło: klient ustawia własne przez „Nie pamiętasz hasła?” (API Shopera nie
 *   daje haseł). Żadnych e-maili przy imporcie — wp_insert_user ich nie wysyła.
 * - Wznawialny: konto szukane po `_sb24_shoper_user_id`, potem po e-mailu; istniejące jest aktualizowane
 *   (hasło nie jest ruszane).
 * - Meta: _sb24_shoper_user_id, _sb24_ostatnia_wizyta, _sb24_newsletter (zgoda z Shopera, tylko informacja).
 * - Konto testowe ma meta `_sb24_testowe` — usunąć przed startem:
 *     skrypty/wp.sh user list --meta_key=_sb24_testowe --field=ID | xargs skrypty/wp.sh user delete --yes
 * Dane osobowe: skrypt wypisuje tylko liczby.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

function sb24k_adres( WC_Customer $klient, array $a ) {
	foreach ( [ 'billing', 'shipping' ] as $typ ) {
		$klient->{"set_{$typ}_first_name"}( $a['imie'] );
		$klient->{"set_{$typ}_last_name"}( $a['nazwisko'] );
		$klient->{"set_{$typ}_company"}( $a['firma'] );
		$klient->{"set_{$typ}_address_1"}( $a['ulica'] );
		$klient->{"set_{$typ}_address_2"}( $a['ulica_2'] );
		$klient->{"set_{$typ}_city"}( $a['miasto'] );
		$klient->{"set_{$typ}_postcode"}( $a['kod'] );
		$klient->{"set_{$typ}_country"}( $a['kraj'] );
		$klient->{"set_{$typ}_phone"}( $a['telefon'] );
	}
}

if ( 'testowe' === ( $args[0] ?? '' ) ) {
	$email = 'klient-testowy@swiatbizuterii24.pl';
	$haslo = wp_generate_password( 14, false );
	$id    = email_exists( $email );
	if ( $id ) {
		wp_set_password( $haslo, $id );
	} else {
		$id = wp_insert_user( [
			'user_login' => 'klient-testowy',
			'user_email' => $email,
			'user_pass'  => $haslo,
			'first_name' => 'Klient',
			'last_name'  => 'Testowy',
			'role'       => 'customer',
		] );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id );
		}
	}
	$klient = new WC_Customer( $id );
	sb24k_adres( $klient, [ 'imie' => 'Klient', 'nazwisko' => 'Testowy', 'firma' => '', 'ulica' => 'ul. Ozimska 17A', 'ulica_2' => '', 'miasto' => 'Opole', 'kod' => '45-057', 'kraj' => 'PL', 'telefon' => '' ] );
	$klient->set_billing_email( $email );
	$klient->save();
	update_user_meta( $id, '_sb24_testowe', 1 );
	WP_CLI::success( "Konto testowe #$id — login: klient-testowy (albo $email), hasło: $haslo" );
	return;
}

$plik = '/import/dane/konta/klienci.json';
if ( ! is_readable( $plik ) ) {
	WP_CLI::error( "Brak $plik — najpierw: node skrypty/eksport/buduj-konta.mjs" );
}
$licz = [ 'nowe' => 0, 'zaktualizowane' => 0, 'bledy' => 0 ];

foreach ( json_decode( file_get_contents( $plik ), true ) as $k ) {
	$istniejace = get_users( [ 'meta_key' => '_sb24_shoper_user_id', 'meta_value' => $k['shoper_id'], 'fields' => 'ID', 'number' => 1 ] );
	$id         = $istniejace[0] ?? email_exists( $k['email'] );
	$dane       = [
		'user_email'      => $k['email'],
		'first_name'      => $k['imie'],
		'last_name'       => $k['nazwisko'],
		'display_name'    => trim( $k['imie'] . ' ' . $k['nazwisko'] ) ?: $k['email'],
		'user_registered' => get_gmt_from_date( $k['data_rejestracji'] ),
	];
	if ( $id ) {
		if ( user_can( $id, 'edit_posts' ) ) { // administrator/redaktor z tym samym e-mailem — nie ruszamy
			++$licz['bledy'];
			WP_CLI::warning( "Shoper #{$k['shoper_id']}: e-mail należy do konta z uprawnieniami — pominięte" );
			continue;
		}
		$wynik = wp_update_user( [ 'ID' => $id ] + $dane );
		$klucz = 'zaktualizowane';
	} else {
		$wynik = wp_insert_user( $dane + [
			'user_login' => wc_create_new_customer_username( $k['email'] ),
			'user_pass'  => wp_generate_password( 24 ),
			'role'       => 'customer',
		] );
		$klucz = 'nowe';
	}
	if ( is_wp_error( $wynik ) ) {
		++$licz['bledy'];
		WP_CLI::warning( "Shoper #{$k['shoper_id']}: " . $wynik->get_error_code() );
		continue;
	}
	$id     = (int) $wynik;
	$klient = new WC_Customer( $id );
	if ( $k['adres'] ) {
		sb24k_adres( $klient, $k['adres'] );
	} else {
		$klient->set_billing_first_name( $k['imie'] );
		$klient->set_billing_last_name( $k['nazwisko'] );
	}
	$klient->set_billing_email( $k['email'] );
	$klient->save();
	update_user_meta( $id, '_sb24_shoper_user_id', $k['shoper_id'] );
	update_user_meta( $id, '_sb24_ostatnia_wizyta', $k['ostatnia_wizyta'] );
	update_user_meta( $id, '_sb24_newsletter', $k['newsletter'] ? 1 : 0 );
	++$licz[ $klucz ];
}

WP_CLI::success( 'Konta klientów: ' . wp_json_encode( $licz ) );
