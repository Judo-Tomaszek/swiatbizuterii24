<?php
/**
 * Bezpieczeństwo logowania (dołączany z sb24-migracja.php). Ukrycie strony logowania panelu robi osobna
 * wtyczka WPS Hide Login (adres w opcji `whl_page`); tutaj:
 *
 * 1. Limit prób po adresie IP — każde logowanie (panel, „Moje konto” WooCommerce, XML-RPC) przechodzi przez
 *    filtr `authenticate`: 5 nieudanych w 15 min → blokada 20 min; 3. blokada w ciągu doby → 24 h.
 *    Bez blokowania po nazwie użytkownika (ktoś obcy mógłby celowo zablokować właściciela).
 * 2. Jeden komunikat dla złego loginu i złego hasła — nie da się sprawdzić, czy konto istnieje.
 * 3. Historia prób w tabeli `{prefix}sb24_logowania` (Narzędzia → Próby logowania, z odblokowaniem IP):
 *    nieudane, odrzucone w czasie blokady, blokady oraz udane logowania pracowników (nie klientów).
 *    IP i wpisany login to dane osobowe — wpisy starsze niż 30 dni kasuje codzienny cron.
 * 4. XML-RPC wyłączony, nazwy użytkowników nie do podejrzenia przez /?author=N i /wp-json/wp/v2/users.
 */

defined( 'ABSPATH' ) || exit;

const SB24_LOG_PROBY          = 5;   // nieudane próby…
const SB24_LOG_OKNO_MIN       = 15;  // …w tylu minutach
const SB24_LOG_BLOKADA_MIN    = 20;
const SB24_LOG_DLUGA_PO       = 3;   // która blokada w ciągu doby jest długa
const SB24_LOG_DLUGA_GODZ     = 24;
const SB24_LOG_PRZECHOWYWANIE = 30;  // dni

function sb24_log_tabela() {
	global $wpdb;
	return $wpdb->prefix . 'sb24_logowania';
}

add_action( 'init', function () { // tabela zakładana przy pierwszym żądaniu (wtyczka jest już aktywna — hak aktywacji nie zadziała)
	if ( '1' === get_option( 'sb24_logowania_wersja' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . sb24_log_tabela() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		czas datetime NOT NULL,
		ip varchar(45) NOT NULL,
		login varchar(190) NOT NULL DEFAULT '',
		miejsce varchar(20) NOT NULL DEFAULT '',
		wynik varchar(20) NOT NULL,
		do_kiedy datetime NULL,
		PRIMARY KEY  (id),
		KEY czas (czas),
		KEY ip (ip, wynik, czas)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'sb24_logowania_wersja', '1' );
} );

function sb24_log_ip() {
	$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ); // nie ufamy X-Forwarded-For — łatwo go podrobić
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function sb24_log_miejsce() {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		return 'xmlrpc';
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return 'rest';
	}
	return isset( $_POST['woocommerce-login-nonce'] ) ? 'moje-konto' : 'panel';
}

function sb24_log_zapisz( $wynik, $login, $do_kiedy = null ) {
	global $wpdb;
	$wpdb->insert( sb24_log_tabela(), [
		'czas'     => current_time( 'mysql', true ),
		'ip'       => sb24_log_ip(),
		'login'    => mb_substr( sanitize_user( (string) $login ), 0, 190 ),
		'miejsce'  => sb24_log_miejsce(),
		'wynik'    => $wynik,
		'do_kiedy' => $do_kiedy,
	] );
}

/** Koniec trwającej blokady IP (timestamp) albo 0. */
function sb24_log_blokada( $ip ) {
	return (int) get_transient( 'sb24_blokada_' . md5( $ip ) );
}

// Blokada sprawdzana na końcu (priorytet 99): wcześniejszy WP_Error rdzeń i tak by pominął i sprawdził hasło.
add_filter( 'authenticate', function ( $user ) {
	$koniec = sb24_log_blokada( sb24_log_ip() );
	if ( $koniec > time() ) {
		$minuty = max( 1, (int) ceil( ( $koniec - time() ) / 60 ) );
		return new WP_Error( 'sb24_blokada', sprintf( 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za %s.',
			$minuty >= 120 ? round( $minuty / 60 ) . ' godz.' : $minuty . ' min' ) );
	}
	if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), [ 'invalid_username', 'invalid_email', 'incorrect_password' ] ) ) {
		return new WP_Error( 'sb24_bledne_dane', 'Nieprawidłowy login lub hasło.' );
	}
	return $user;
}, 99 );

add_action( 'wp_login_failed', function ( $login, $blad = null ) {
	global $wpdb;
	if ( $blad instanceof WP_Error && 'sb24_blokada' === $blad->get_error_code() ) {
		sb24_log_zapisz( 'zablokowane', $login );
		return;
	}
	if ( $blad instanceof WP_Error && array_intersect( $blad->get_error_codes(), [ 'empty_username', 'empty_password' ] ) ) {
		return; // puste pole formularza to nie próba zgadywania
	}
	sb24_log_zapisz( 'nieudane', $login );
	$ip     = sb24_log_ip();
	$tabela = sb24_log_tabela();
	$ile    = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $tabela WHERE ip = %s AND wynik = 'nieudane' AND czas > %s AND czas > COALESCE(
			(SELECT MAX(czas) FROM $tabela WHERE ip = %s AND wynik IN ('blokada', 'odblokowane')), '1970-01-01')",
		$ip, gmdate( 'Y-m-d H:i:s', time() - SB24_LOG_OKNO_MIN * MINUTE_IN_SECONDS ), $ip
	) );
	if ( $ile < SB24_LOG_PROBY ) {
		return;
	}
	$blokad = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $tabela WHERE ip = %s AND wynik = 'blokada' AND czas > %s", $ip, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
	) );
	$sekundy = $blokad + 1 >= SB24_LOG_DLUGA_PO ? SB24_LOG_DLUGA_GODZ * HOUR_IN_SECONDS : SB24_LOG_BLOKADA_MIN * MINUTE_IN_SECONDS;
	set_transient( 'sb24_blokada_' . md5( $ip ), time() + $sekundy, $sekundy );
	sb24_log_zapisz( 'blokada', $login, gmdate( 'Y-m-d H:i:s', time() + $sekundy ) );
}, 10, 2 );

add_action( 'wp_login', function ( $login, $user ) {
	if ( user_can( $user, 'edit_posts' ) ) { // pracownicy — ślad udanych wejść do panelu; klientów nie śledzimy
		sb24_log_zapisz( 'udane', $login );
	}
}, 10, 2 );

// Sprzątanie: wpisy starsze niż 30 dni.
add_action( 'sb24_logowania_sprzatanie', function () {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . sb24_log_tabela() . ' WHERE czas < %s',
		gmdate( 'Y-m-d H:i:s', time() - SB24_LOG_PRZECHOWYWANIE * DAY_IN_SECONDS ) ) );
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'sb24_logowania_sprzatanie' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sb24_logowania_sprzatanie' );
	}
} );

/* ------------------------------------------------------------ XML-RPC i nazwy użytkowników */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', fn() => [] ); // także pingbacki
add_filter( 'wp_headers', function ( $naglowki ) {
	unset( $naglowki['X-Pingback'] );
	return $naglowki;
} );

// /?author=1 → WordPress przekierowałby na /author/<login>/ i zdradził login.
add_action( 'parse_request', function ( $wp ) {
	if ( ! is_user_logged_in() && isset( $_GET['author'] ) ) {
		$wp->query_vars = [ 'error' => '404' ];
	}
} );
add_filter( 'redirect_canonical', fn( $url ) => ( ! is_user_logged_in() && isset( $_GET['author'] ) ) ? false : $url );

// REST: lista użytkowników tylko dla zalogowanych z prawem do edycji (edytor bloków jej używa).
add_filter( 'rest_endpoints', function ( $trasy ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		foreach ( array_keys( $trasy ) as $trasa ) {
			if ( 0 === strpos( $trasa, '/wp/v2/users' ) ) {
				unset( $trasy[ $trasa ] );
			}
		}
	}
	return $trasy;
} );

/* ------------------------------------------------------------ podgląd w panelu: Narzędzia → Próby logowania */

add_action( 'admin_menu', function () {
	add_management_page( 'Próby logowania', 'Próby logowania', 'manage_options', 'sb24-logowania', 'sb24_log_strona' );
} );

add_action( 'admin_post_sb24_odblokuj', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Brak uprawnień.' );
	}
	check_admin_referer( 'sb24_odblokuj' );
	$ip = sanitize_text_field( wp_unslash( $_POST['ip'] ?? '' ) );
	if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		delete_transient( 'sb24_blokada_' . md5( $ip ) );
		global $wpdb;
		$wpdb->insert( sb24_log_tabela(), [ 'czas' => current_time( 'mysql', true ), 'ip' => $ip, 'login' => wp_get_current_user()->user_login,
			'miejsce' => 'panel', 'wynik' => 'odblokowane' ] );
	}
	wp_safe_redirect( admin_url( 'tools.php?page=sb24-logowania&odblokowano=1' ) );
	exit;
} );

function sb24_log_strona() {
	global $wpdb;
	$t      = sb24_log_tabela();
	$teraz  = gmdate( 'Y-m-d H:i:s' );
	$doba   = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	$filtr  = sanitize_key( $_GET['wynik'] ?? '' );
	$strona = max( 1, absint( $_GET['str'] ?? 1 ) );
	$na_str = 100;
	$gdzie  = $filtr ? $wpdb->prepare( 'WHERE wynik = %s', $filtr ) : '';
	$wpisy  = $wpdb->get_results( "SELECT * FROM $t $gdzie ORDER BY id DESC LIMIT $na_str OFFSET " . ( ( $strona - 1 ) * $na_str ) );
	$razem  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t $gdzie" );
	$blokady = $wpdb->get_results( $wpdb->prepare(
		"SELECT ip, MAX(do_kiedy) do_kiedy, MAX(login) login FROM $t WHERE wynik = 'blokada' AND do_kiedy > %s GROUP BY ip ORDER BY do_kiedy DESC", $teraz
	) );
	$blokady = array_filter( $blokady, fn( $b ) => sb24_log_blokada( $b->ip ) > time() ); // pomija już odblokowane
	$stat    = $wpdb->get_results( $wpdb->prepare( "SELECT wynik, COUNT(*) n FROM $t WHERE czas > %s GROUP BY wynik", $doba ), OBJECT_K );
	$n       = fn( $w ) => (int) ( $stat[ $w ]->n ?? 0 );
	$czas    = fn( $utc ) => $utc ? esc_html( get_date_from_gmt( $utc, 'Y-m-d H:i:s' ) ) : '';
	$opisy   = [
		'nieudane'    => [ 'Nieudana próba', '#b26200' ],
		'zablokowane' => [ 'Odrzucona (blokada)', '#a33' ],
		'blokada'     => [ 'Nałożona blokada', '#a33' ],
		'odblokowane' => [ 'Odblokowano ręcznie', '#1159af' ],
		'udane'       => [ 'Udane (pracownik)', '#13612e' ],
	];
	$miejsca = [ 'panel' => 'panel admina', 'moje-konto' => 'Moje konto (klient)', 'xmlrpc' => 'XML-RPC', 'rest' => 'REST API' ];
	?>
	<div class="wrap">
		<h1>Próby logowania</h1>
		<p><?php echo esc_html( sprintf( 'Blokada: %d nieudanych prób w %d min z jednego adresu IP → %d min; %d. blokada w ciągu doby → %d godz. Historia: %d dni.',
			SB24_LOG_PROBY, SB24_LOG_OKNO_MIN, SB24_LOG_BLOKADA_MIN, SB24_LOG_DLUGA_PO, SB24_LOG_DLUGA_GODZ, SB24_LOG_PRZECHOWYWANIE ) ); ?></p>
		<?php if ( isset( $_GET['odblokowano'] ) ) : ?><div class="notice notice-success"><p>Adres odblokowany.</p></div><?php endif; ?>

		<h2>Ostatnie 24 godziny</h2>
		<p>Nieudane próby: <strong><?php echo $n( 'nieudane' ); ?></strong> · odrzucone w czasie blokady: <strong><?php echo $n( 'zablokowane' ); ?></strong>
			· nałożone blokady: <strong><?php echo $n( 'blokada' ); ?></strong> · udane logowania pracowników: <strong><?php echo $n( 'udane' ); ?></strong></p>

		<h2>Zablokowane teraz</h2>
		<?php if ( ! $blokady ) : ?>
			<p>Brak zablokowanych adresów.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th>Adres IP</th><th>Ostatnio wpisany login</th><th>Blokada do</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $blokady as $b ) : ?>
					<tr><td><code><?php echo esc_html( $b->ip ); ?></code></td><td><?php echo esc_html( $b->login ); ?></td><td><?php echo $czas( $b->do_kiedy ); ?></td>
						<td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'sb24_odblokuj' ); ?><input type="hidden" name="action" value="sb24_odblokuj"><input type="hidden" name="ip" value="<?php echo esc_attr( $b->ip ); ?>">
							<button class="button">Odblokuj</button></form></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>Historia</h2>
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( remove_query_arg( [ 'wynik', 'str', 'odblokowano' ] ) ); ?>"<?php echo $filtr ? '' : ' class="current"'; ?>>Wszystkie</a> |</li>
			<?php foreach ( $opisy as $klucz => [ $opis ] ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( [ 'wynik' => $klucz, 'str' => 1 ], remove_query_arg( 'odblokowano' ) ) ); ?>"<?php echo $filtr === $klucz ? ' class="current"' : ''; ?>><?php echo esc_html( $opis ); ?></a><?php echo array_key_last( $opisy ) === $klucz ? '' : ' |'; ?></li>
			<?php endforeach; ?>
		</ul>
		<table class="widefat striped">
			<thead><tr><th>Czas</th><th>Wynik</th><th>Wpisany login</th><th>Adres IP</th><th>Gdzie</th><th>Blokada do</th></tr></thead>
			<tbody>
			<?php if ( ! $wpisy ) : ?>
				<tr><td colspan="6">Brak wpisów.</td></tr>
			<?php endif; ?>
			<?php foreach ( $wpisy as $w ) : [ $opis, $kolor ] = $opisy[ $w->wynik ] ?? [ $w->wynik, '#333' ]; ?>
				<tr>
					<td><?php echo $czas( $w->czas ); ?></td>
					<td><strong style="color:<?php echo esc_attr( $kolor ); ?>"><?php echo esc_html( $opis ); ?></strong></td>
					<td><?php echo esc_html( $w->login ); ?></td>
					<td><code><?php echo esc_html( $w->ip ); ?></code></td>
					<td><?php echo esc_html( $miejsca[ $w->miejsce ] ?? $w->miejsce ); ?></td>
					<td><?php echo $czas( $w->do_kiedy ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $razem > $na_str ) : ?>
			<p><?php echo paginate_links( [ 'base' => add_query_arg( 'str', '%#%' ), 'format' => '', 'current' => $strona, 'total' => (int) ceil( $razem / $na_str ) ] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
