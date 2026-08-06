<?php
/**
 * Console admin — dettaglio di un account cliente.
 *
 * Variabili disponibili: $id (int).
 *
 * @package AdverTrieste
 */

use AdverTrieste\Admin\AdminConsole;
use AdverTrieste\Console\Tabella;
use AdverTrieste\Access\Roles;
use AdverTrieste\Cpt\Locale;
use AdverTrieste\Cliente\Abbonamento;

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_u = get_userdata( $id );
if ( ! $advtr_u ) {
	echo '<div class="ac-vuoto"><p>' . esc_html__( 'Account non trovato.', 'advertrieste' ) . '</p></div>';
	return;
}

$advtr_admin  = user_can( $advtr_u, 'manage_options' );
$advtr_ruolo  = in_array( Roles::ORGANIZZATORE, (array) $advtr_u->roles, true ) ? Roles::ORGANIZZATORE : Roles::CLIENTE;
$advtr_schede = get_posts(
	array(
		'post_type'      => Locale::POST_TYPE,
		'post_status'    => array( 'publish', 'pending', 'draft' ),
		'author'         => $id,
		'posts_per_page' => -1,
	)
);
?>
<p style="margin:0 0 16px">
	<a class="ac-btn ac-btn-neutro" href="<?php echo esc_url( AdminConsole::url( 'clienti' ) ); ?>">
		← <?php esc_html_e( 'Torna all\'elenco', 'advertrieste' ); ?>
	</a>
</p>

<?php if ( $advtr_admin ) : ?>
	<div class="ac-avviso" style="margin-bottom:20px">
		<span class="ac-avviso-testo">
			<span class="ac-avviso-titolo"><?php esc_html_e( 'Account amministratore', 'advertrieste' ); ?></span><br />
			<span class="ac-avviso-dett">
				<?php esc_html_e( 'Gli account con pieni poteri non si modificano da qui: la loro gestione resta dove ci sono tutte le tutele di WordPress.', 'advertrieste' ); ?>
			</span>
		</span>
	</div>
<?php endif; ?>

<div class="ac-griglia ac-griglia-3-2" style="align-items:start">

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Account', 'advertrieste' ); ?></h3>

		<?php if ( $advtr_admin ) : ?>
			<p class="ac-card-sottotitolo"><?php echo esc_html( $advtr_u->display_name ); ?> · <?php echo esc_html( $advtr_u->user_email ); ?></p>
		<?php else : ?>
			<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'clienti', array( 'id' => $id ) ) ); ?>">
				<?php wp_nonce_field( AdminConsole::NONCE ); ?>
				<input type="hidden" name="advtr_azione" value="cliente_salva" />
				<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

				<label for="ac-cl-login"><?php esc_html_e( 'Nome utente', 'advertrieste' ); ?></label>
				<input type="text" id="ac-cl-login" value="<?php echo esc_attr( $advtr_u->user_login ); ?>" readonly />
				<p class="advtr-aiuto"><?php esc_html_e( 'È ciò che il cliente scrive per accedere, insieme alla password. Non si cambia: WordPress lo fissa alla creazione.', 'advertrieste' ); ?></p>

				<label for="ac-cl-nome"><?php esc_html_e( 'Nome visualizzato', 'advertrieste' ); ?></label>
				<input type="text" id="ac-cl-nome" name="advtr_nome" required value="<?php echo esc_attr( $advtr_u->display_name ); ?>" />

				<label for="ac-cl-mail"><?php esc_html_e( 'Email', 'advertrieste' ); ?></label>
				<input type="email" id="ac-cl-mail" name="advtr_email" required value="<?php echo esc_attr( $advtr_u->user_email ); ?>" />
				<p class="advtr-aiuto"><?php esc_html_e( 'È l\'indirizzo a cui arrivano gli avvisi di scadenza.', 'advertrieste' ); ?></p>

				<label for="ac-cl-ruolo"><?php esc_html_e( 'Ruolo', 'advertrieste' ); ?></label>
				<select id="ac-cl-ruolo" name="advtr_ruolo">
					<option value="<?php echo esc_attr( Roles::CLIENTE ); ?>" <?php selected( Roles::CLIENTE, $advtr_ruolo ); ?>>
						<?php esc_html_e( 'Cliente (locale)', 'advertrieste' ); ?>
					</option>
					<option value="<?php echo esc_attr( Roles::ORGANIZZATORE ); ?>" <?php selected( Roles::ORGANIZZATORE, $advtr_ruolo ); ?>>
						<?php esc_html_e( 'Organizzatore evento', 'advertrieste' ); ?>
					</option>
				</select>

				<div class="advtr-form-azioni">
					<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Salva', 'advertrieste' ); ?></button>
				</div>
			</form>

			<p class="advtr-aiuto" style="margin-top:14px">
				<?php esc_html_e( 'La password non si imposta da qui: il cliente la reimposta da solo dall\'area clienti, ricevendo un link via email.', 'advertrieste' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="ac-card">
		<h3 class="ac-card-titolo"><?php esc_html_e( 'Schede collegate', 'advertrieste' ); ?></h3>
		<?php if ( ! $advtr_schede ) : ?>
			<p class="ac-card-sottotitolo">
				<?php esc_html_e( 'Nessuna scheda. Per collegarne una, aprila da Locali e impostalo come cliente proprietario.', 'advertrieste' ); ?>
			</p>
		<?php else : ?>
			<ul style="list-style:none;margin:0;padding:0">
				<?php foreach ( $advtr_schede as $advtr_s ) : ?>
					<?php $advtr_st = Abbonamento::stato( $advtr_s->ID ); ?>
					<li style="padding:9px 0;border-bottom:1px solid var(--ac-bordo)">
						<a href="<?php echo esc_url( AdminConsole::url( 'locali', array( 'id' => $advtr_s->ID ) ) ); ?>">
							<?php echo esc_html( $advtr_s->post_title ); ?>
						</a><br />
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup del componente, già escapato.
						echo Tabella::pill( $advtr_st['etichetta'], $advtr_st['pill'] );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>
<?php if ( ! $advtr_admin ) : ?>
<div class="ac-card" style="margin-top:20px">
	<h3 class="ac-card-titolo"><?php esc_html_e( 'Accesso', 'advertrieste' ); ?></h3>
	<p class="ac-card-sottotitolo">
		<?php esc_html_e( 'Il cliente entra dall\'area clienti con il proprio nome utente e la password. La via consigliata è la prima: così la password la conosce solo lui.', 'advertrieste' ); ?>
	</p>

	<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'clienti', array( 'id' => $id ) ) ); ?>"
		style="margin-bottom:18px">
		<?php wp_nonce_field( AdminConsole::NONCE ); ?>
		<input type="hidden" name="advtr_azione" value="password_link" />
		<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />
		<div class="advtr-form-azioni" style="margin-top:0">
			<button type="submit" class="ac-btn ac-btn-verde"><?php esc_html_e( 'Invia il link per impostare la password', 'advertrieste' ); ?></button>
		</div>
		<p class="advtr-aiuto" style="margin-top:8px">
			<?php
			printf(
				/* translators: %s: indirizzo email del cliente */
				esc_html__( 'Arriva a %s. Se la posta del sito non funziona, usa il campo qui sotto.', 'advertrieste' ),
				esc_html( $advtr_u->user_email )
			);
			?>
		</p>
	</form>

	<form class="advtr-form" method="post" action="<?php echo esc_url( AdminConsole::url( 'clienti', array( 'id' => $id ) ) ); ?>"
		data-advtr-conferma="<?php esc_attr_e( 'Confermi? Scollega le sue sessioni', 'advertrieste' ); ?>">
		<?php wp_nonce_field( AdminConsole::NONCE ); ?>
		<input type="hidden" name="advtr_azione" value="password_cliente" />
		<input type="hidden" name="advtr_id" value="<?php echo esc_attr( $id ); ?>" />

		<label for="ac-cl-pass"><?php esc_html_e( 'Oppure imposta tu una password', 'advertrieste' ); ?></label>
		<input type="text" id="ac-cl-pass" name="advtr_password" autocomplete="new-password" spellcheck="false"
			minlength="<?php echo esc_attr( \AdverTrieste\Admin\Salva::PASSWORD_MIN ); ?>"
			data-advtr-genera="<?php esc_attr_e( 'Genera', 'advertrieste' ); ?>" />
		<p class="advtr-aiuto">
			<?php
			printf(
				/* translators: %d: numero minimo di caratteri */
				esc_html__( 'Almeno %d caratteri. Il campo è in chiaro apposta: devi poterla leggere per dettarla. Dopo il salvataggio non è più recuperabile da nessuna parte, nemmeno da qui, e le sessioni aperte del cliente decadono.', 'advertrieste' ),
				(int) \AdverTrieste\Admin\Salva::PASSWORD_MIN
			);
			?>
		</p>

		<div class="advtr-form-azioni">
			<button type="submit" class="ac-btn ac-btn-neutro"><?php esc_html_e( 'Imposta la password', 'advertrieste' ); ?></button>
		</div>
	</form>
</div>
<?php endif; ?>
<?php
$advtr_zona = AdminConsole::zona_pericolosa_cliente( $id );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup del componente, già escapato.
echo $advtr_zona;
