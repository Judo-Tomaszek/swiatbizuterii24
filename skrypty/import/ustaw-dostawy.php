<?php
/**
 * Dostawy i adres sklepu 1:1 z Shopera (docs/plany/konfiguracja-sklepu.md, pkt 1 i 6).
 *
 *   skrypty/wp.sh eval-file /import/skrypty/ustaw-dostawy.php
 *
 * Shoper (API `shippings`, odczyt 2026-10-20), wysyłka tylko do Polski:
 * - odbiór osobisty 0 zł w salonie → „Odbiór” kasy blokowej (lokalizacja: salon);
 * - DHL Kurier: 21,50 zł, od 70 zł zamówienia 0 zł (przedziały wartości 0–70 / 70+) → strefa
 *   „Polska”: stawka ryczałtowa + darmowa wysyłka od 70 zł, obie nazwane „DHL Kurier”; płatną
 *   chowa wtyczka sb24-migracja, gdy darmowa jest dostępna (sekcja „dostawy”).
 * - Limit 10 kg z Shopera pominięty: żaden produkt nie ma wagi.
 * - Dopłata 20 zł za pobranie w Shoperze należy do metody płatności (pkt 4), nie do dostawy.
 * Wznawialny: metody w strefie są usuwane i zakładane od nowa.
 */

if ( ! defined( 'WP_CLI' ) ) {
	exit;
}

const SB24D_KURIER   = 'DHL Kurier';
const SB24D_CENA     = '21.50';
const SB24D_DARMOWA  = '70';

foreach ( [
	'woocommerce_store_address'               => 'ul. Ozimska 17A',
	'woocommerce_store_address_2'             => '',
	'woocommerce_store_city'                  => 'Opole',
	'woocommerce_store_postcode'              => '45-057',
	'woocommerce_default_country'             => 'PL:OP',
	'woocommerce_allowed_countries'           => 'specific',
	'woocommerce_specific_allowed_countries'  => [ 'PL' ],
	'woocommerce_ship_to_countries'           => '',  // = tam, gdzie sprzedajemy
	'woocommerce_shipping_cost_requires_address' => 'no',
] as $opcja => $wartosc ) {
	update_option( $opcja, $wartosc );
}

// Strefa „Polska”
$strefa = null;
foreach ( WC_Shipping_Zones::get_zones() as $z ) {
	if ( 'Polska' === $z['zone_name'] ) {
		$strefa = new WC_Shipping_Zone( $z['id'] );
	}
}
$strefa = $strefa ?: new WC_Shipping_Zone();
$strefa->set_zone_name( 'Polska' );
$strefa->set_zone_order( 0 );
$strefa->set_locations( [ [ 'code' => 'PL', 'type' => 'country' ] ] );
$strefa->save();
foreach ( $strefa->get_shipping_methods() as $m ) {
	$strefa->delete_shipping_method( $m->instance_id );
}

$metody = [
	'flat_rate'     => [ 'title' => SB24D_KURIER, 'tax_status' => 'taxable', 'cost' => SB24D_CENA ],
	'free_shipping' => [ 'title' => SB24D_KURIER, 'requires' => 'min_amount', 'min_amount' => SB24D_DARMOWA, 'ignore_discounts' => 'no' ],
];
foreach ( $metody as $typ => $ustawienia ) {
	$id = $strefa->add_shipping_method( $typ );
	update_option( "woocommerce_{$typ}_{$id}_settings", $ustawienia );
}

// Odbiór osobisty (kasa blokowa: zakładka „Odbiór”)
update_option( 'woocommerce_pickup_location_settings', [
	'enabled'    => 'yes',
	'title'      => 'Odbiór osobisty',
	'tax_status' => 'taxable',
	'cost'       => '',
] );
update_option( 'pickup_location_pickup_locations', [ [
	'name'    => 'Salon Świat Biżuterii 24',
	'address' => [ 'address_1' => 'ul. Ozimska 17A', 'city' => 'Opole', 'state' => 'OP', 'postcode' => '45-057', 'country' => 'PL' ],
	'details' => 'Odbiór w siedzibie firmy, ul. Ozimska 17A, 45-057 Opole.',
	'enabled' => true,
] ] );

WC_Cache_Helper::get_transient_version( 'shipping', true );
WP_CLI::success( 'Dostawy: strefa „Polska” (#' . $strefa->get_id() . '): ' . SB24D_KURIER . ' ' . SB24D_CENA . ' zł, od ' . SB24D_DARMOWA . ' zł darmowa; odbiór osobisty w salonie; adres sklepu ustawiony.' );
