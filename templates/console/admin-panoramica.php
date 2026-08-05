<?php
/**
 * Console admin — panoramica: cosa richiede attenzione.
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Console;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cpt\Poi;
use AdverTrieste\Cpt\Offerta;
use AdverTrieste\Cpt\PuntoQr;
use AdverTrieste\Coupon\Coupon;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_da_fare = AdminConsole::da_fare();

$advtr_offerte_attive = 0;
foreach ( get_posts(
	array(
		'post_type'      => Offerta::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
) as $advtr_oid ) {
	if ( Coupon::is_offer_active( $advtr_oid ) ) {
		++$advtr_offerte_attive;
	}
}

$advtr_clienti = count_users();
$advtr_n_cli   = $advtr_clienti['avail_roles']['cliente_locale'] ?? 0;
?>

<?php if ( array_sum( $advtr_da_fare ) > 0 ) : ?>
	<div class="ac-avviso attesa" style="margin-bottom:20px">
		<span class="ac-avviso-testo">
			<span class="ac-avviso-titolo"><?php esc_html_e( 'Ci sono cose in attesa di te', 'advertrieste' ); ?></span><br />
			<span class="ac-avviso-dett">
				<?php
				$advtr_parti = array();
				if ( $advtr_da_fare['schede'] ) {
					/* translators: %d: numero di schede */
					$advtr_parti[] = sprintf( _n( '%d scheda da pubblicare', '%d schede da pubblicare', $advtr_da_fare['schede'], 'advertrieste' ), $advtr_da_fare['schede'] );
				}
				if ( $advtr_da_fare['eventi'] ) {
					/* translators: %d: numero di eventi */
					$advtr_parti[] = sprintf( _n( '%d evento in revisione', '%d eventi in revisione', $advtr_da_fare['eventi'], 'advertrieste' ), $advtr_da_fare['eventi'] );
				}
				if ( $advtr_da_fare['scadenza'] ) {
					/* translators: %d: numero di abbonamenti */
					$advtr_parti[] = sprintf( _n( '%d abbonamento in scadenza', '%d abbonamenti in scadenza', $advtr_da_fare['scadenza'], 'advertrieste' ), $advtr_da_fare['scadenza'] );
				}
				echo esc_html( implode( ' · ', $advtr_parti ) );
				?>
			</span>
		</span>
		<a class="ac-btn ac-btn-primario" href="<?php echo esc_url( AdminConsole::url( 'approvazioni' ) ); ?>">
			<?php esc_html_e( 'Vai alle approvazioni', 'advertrieste' ); ?>
		</a>
	</div>
<?php endif; ?>

<div class="ac-griglia ac-griglia-4">
	<?php
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- componenti già escapati.
	echo Console::kpi( __( 'Locali pubblicati', 'advertrieste' ), AdminConsole::conta( Locale::POST_TYPE ) );
	echo Console::kpi( __( 'Punti d\'interesse', 'advertrieste' ), AdminConsole::conta( Poi::POST_TYPE ) );
	echo Console::kpi( __( 'Offerte attive', 'advertrieste' ), $advtr_offerte_attive );
	echo Console::kpi( __( 'Punti QR', 'advertrieste' ), AdminConsole::conta( PuntoQr::POST_TYPE ) );
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
</div>

<div class="ac-griglia ac-griglia-2">
	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Clienti', 'advertrieste' ); ?></h3>
		<p class="ac-card-sottotitolo"><?php esc_html_e( 'Account che gestiscono una scheda in autonomia', 'advertrieste' ); ?></p>
		<p style="font-family:Georgia,serif;font-size:30px;font-weight:700;margin:0">
			<?php echo esc_html( number_format_i18n( $advtr_n_cli ) ); ?>
		</p>
		<p style="margin-top:12px">
			<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'clienti' ) ); ?>">
				<?php esc_html_e( 'Gestisci i clienti', 'advertrieste' ); ?>
			</a>
		</p>
	</div>

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Scorciatoie', 'advertrieste' ); ?></h3>
		<p class="ac-card-sottotitolo"><?php esc_html_e( 'Le operazioni che si fanno più spesso', 'advertrieste' ); ?></p>
		<div class="ac-barra-azioni">
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dei componenti, già escapato.
			echo AdminConsole::bottone_nuovo( 'locali', __( 'Nuovo locale', 'advertrieste' ) );
			echo AdminConsole::bottone_nuovo( 'qr', __( 'Nuovo punto QR', 'advertrieste' ) );
			echo AdminConsole::bottone_nuovo( 'eventi', __( 'Nuovo evento', 'advertrieste' ) );
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'clienti' ) ); ?>">
				<?php esc_html_e( 'Nuovo cliente', 'advertrieste' ); ?>
			</a>
		</div>
		<p class="ac-card-sottotitolo" style="margin-top:12px;margin-bottom:0">
			<?php esc_html_e( 'Ogni elemento nasce in bozza: si completa e si pubblica quando è pronto.', 'advertrieste' ); ?>
		</p>
	</div>
</div>
