<?php
/**
 * Sezione "Logo e foto": upload e rimozione, limitati ai file del cliente.
 *
 * Variabili disponibili: $locale (WP_Post).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Frontend\ClientArea;
use AdverTrieste\Cliente\Media;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_logo_id  = (int) get_post_meta( $locale->ID, 'advtr_logo_id', true );
$advtr_galleria = Media::galleria( $locale->ID );
$advtr_azione   = esc_url( ClientArea::url( 'immagini' ) );
?>
<section class="advtr-media">

	<h3><?php esc_html_e( 'Logo', 'advertrieste' ); ?></h3>
	<div class="advtr-media-riga">
		<?php if ( $advtr_logo_id ) : ?>
			<figure class="advtr-media-item">
				<?php echo wp_get_attachment_image( $advtr_logo_id, 'thumbnail' ); ?>
				<form method="post" action="<?php echo $advtr_azione; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già passata da esc_url. ?>">
					<?php wp_nonce_field( ClientArea::NONCE ); ?>
					<input type="hidden" name="advtr_azione" value="media_elimina" />
					<input type="hidden" name="advtr_ruolo" value="logo" />
					<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $locale->ID ); ?>" />
					<input type="hidden" name="advtr_allegato_id" value="<?php echo esc_attr( $advtr_logo_id ); ?>" />
					<button type="submit" class="advtr-btn advtr-btn-rimuovi"><?php esc_html_e( 'Rimuovi', 'advertrieste' ); ?></button>
				</form>
			</figure>
		<?php else : ?>
			<p class="advtr-nota"><?php esc_html_e( 'Nessun logo caricato.', 'advertrieste' ); ?></p>
		<?php endif; ?>

		<form class="advtr-upload" method="post" enctype="multipart/form-data" action="<?php echo $advtr_azione; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già passata da esc_url. ?>">
			<?php wp_nonce_field( ClientArea::NONCE ); ?>
			<input type="hidden" name="advtr_azione" value="media_carica" />
			<input type="hidden" name="advtr_ruolo" value="logo" />
			<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $locale->ID ); ?>" />
			<input type="file" name="advtr_file" accept="image/jpeg,image/png,image/webp,image/gif" required />
			<button type="submit" class="advtr-btn advtr-btn-primario"><?php esc_html_e( 'Carica logo', 'advertrieste' ); ?></button>
		</form>
	</div>

	<h3><?php esc_html_e( 'Galleria', 'advertrieste' ); ?></h3>
	<p class="advtr-aiuto">
		<?php
		printf(
			/* translators: 1: foto caricate, 2: numero massimo */
			esc_html__( '%1$d foto su un massimo di %2$d. Formati ammessi: JPG, PNG, WebP, GIF — fino a 5 MB.', 'advertrieste' ),
			count( $advtr_galleria ),
			(int) Media::MAX_GALLERIA
		);
		?>
	</p>

	<div class="advtr-media-griglia">
		<?php foreach ( $advtr_galleria as $advtr_att_id ) : ?>
			<figure class="advtr-media-item">
				<?php echo wp_get_attachment_image( $advtr_att_id, 'medium' ); ?>
				<form method="post" action="<?php echo $advtr_azione; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già passata da esc_url. ?>">
					<?php wp_nonce_field( ClientArea::NONCE ); ?>
					<input type="hidden" name="advtr_azione" value="media_elimina" />
					<input type="hidden" name="advtr_ruolo" value="galleria" />
					<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $locale->ID ); ?>" />
					<input type="hidden" name="advtr_allegato_id" value="<?php echo esc_attr( $advtr_att_id ); ?>" />
					<button type="submit" class="advtr-btn advtr-btn-rimuovi"><?php esc_html_e( 'Rimuovi', 'advertrieste' ); ?></button>
				</form>
			</figure>
		<?php endforeach; ?>
	</div>

	<?php if ( count( $advtr_galleria ) < Media::MAX_GALLERIA ) : ?>
		<form class="advtr-upload" method="post" enctype="multipart/form-data" action="<?php echo $advtr_azione; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già passata da esc_url. ?>">
			<?php wp_nonce_field( ClientArea::NONCE ); ?>
			<input type="hidden" name="advtr_azione" value="media_carica" />
			<input type="hidden" name="advtr_ruolo" value="galleria" />
			<input type="hidden" name="advtr_locale_id" value="<?php echo esc_attr( $locale->ID ); ?>" />
			<input type="file" name="advtr_file" accept="image/jpeg,image/png,image/webp,image/gif" required />
			<button type="submit" class="advtr-btn advtr-btn-primario"><?php esc_html_e( 'Aggiungi foto', 'advertrieste' ); ?></button>
		</form>
	<?php endif; ?>

</section>
