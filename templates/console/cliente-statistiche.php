<?php
/**
 * Console cliente — Statistiche della scheda.
 *
 * Trascrizione della schermata 08 della proposta di progetto: quattro
 * indicatori con confronto sul periodo precedente, andamento nel tempo e
 * sezioni più viste.
 *
 * Variabili disponibili: $locale (WP_Post|null).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Console\Console;
use AdverTrieste\Stats\Stats;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $locale ) {
	require ADVTR_PATH . 'templates/console/cliente-senza-scheda.php';
	return;
}

$advtr_giorni    = 30;
$advtr_confronto = Stats::totali_confronto( $locale->ID, $advtr_giorni );
$advtr_ora       = $advtr_confronto['ora'];
$advtr_prima     = $advtr_confronto['prima'];

$advtr_kpi = array(
	array( __( 'Visualizzazioni scheda', 'advertrieste' ), 'view' ),
	array( __( 'Click sulla mappa', 'advertrieste' ), 'map_click' ),
	array( __( 'Coupon riscattati', 'advertrieste' ), 'coupon' ),
	array( __( 'Click contatti', 'advertrieste' ), 'contact' ),
);

// Sezioni più viste: etichette leggibili per gli slug tracciati dalla scheda.
$advtr_nomi_sezioni = array(
	'galleria' => __( 'Galleria foto', 'advertrieste' ),
	'offerte'  => __( 'Offerte', 'advertrieste' ),
	'servizi'  => __( 'Servizi', 'advertrieste' ),
	'contatti' => __( 'Contatti', 'advertrieste' ),
	'mappa'    => __( 'Mappa', 'advertrieste' ),
);

$advtr_sezioni_viste = array();
foreach ( Stats::conteggi_per_meta( $locale->ID, 'sezione', $advtr_giorni ) as $advtr_slug => $advtr_n ) {
	$advtr_sezioni_viste[] = array(
		'etichetta' => $advtr_nomi_sezioni[ $advtr_slug ] ?? $advtr_slug,
		'valore'    => $advtr_n,
	);
}
?>

<div class="ac-griglia ac-griglia-4">
	<?php
	foreach ( $advtr_kpi as $advtr_voce ) {
		list( $advtr_eti, $advtr_tipo ) = $advtr_voce;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- il componente restituisce markup già escapato.
		echo Console::kpi( $advtr_eti, $advtr_ora[ $advtr_tipo ], $advtr_prima[ $advtr_tipo ] );
	}
	?>
</div>

<div class="ac-griglia ac-griglia-3-2">

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Visualizzazioni nel tempo', 'advertrieste' ); ?></h3>
		<p class="ac-card-sottotitolo">
			<?php
			printf(
				/* translators: %d: numero di giorni */
				esc_html__( 'Andamento giornaliero · ultimi %d giorni', 'advertrieste' ),
				(int) $advtr_giorni
			);
			?>
		</p>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG costruito dal componente.
		echo Console::grafico( Stats::daily_series( $locale->ID, 'view', $advtr_giorni ) );
		?>
	</div>

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Sezioni più viste', 'advertrieste' ); ?></h3>
		<p class="ac-card-sottotitolo"><?php esc_html_e( 'Dove si soffermano i visitatori', 'advertrieste' ); ?></p>
		<?php if ( ! $advtr_sezioni_viste ) : ?>
			<p class="ac-card-sottotitolo">
				<?php esc_html_e( 'Ancora nessun dato: la rilevazione parte dalle prossime visite alla tua scheda.', 'advertrieste' ); ?>
			</p>
		<?php else : ?>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- il componente restituisce markup già escapato.
			echo Console::barre( $advtr_sezioni_viste );
			?>
		<?php endif; ?>
	</div>

</div>

<?php if ( ! Stats::soglia_raggiunta( $locale->ID ) ) : ?>
	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'La tua scheda è ancora una "Novità"', 'advertrieste' ); ?></h3>
		<p class="ac-card-sottotitolo">
			<?php
			printf(
				/* translators: 1: visite attuali, 2: soglia */
				esc_html__( 'Al pubblico mostriamo il badge "Novità" invece del numero di visite finché la scheda non supera la soglia: sei a %1$d visite su %2$d. Non gonfiamo i contatori.', 'advertrieste' ),
				(int) Stats::visite_reali( $locale->ID ),
				(int) Stats::SOGLIA_VISITE
			);
			?>
		</p>
	</div>
<?php endif; ?>
