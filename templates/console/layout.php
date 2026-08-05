<?php
/**
 * Guscio della console: sidebar, intestazione, area contenuti.
 *
 * Variabili disponibili (da Console\Console::guscio): $conf.
 * Il markup di $conf['contenuto'], $conf['azioni'] e dell'azione dell'avviso è
 * costruito dai componenti e arriva già escapato.
 *
 * @package AdverTrieste
 */

// Guardia: nessun accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$advtr_utente = wp_parse_args(
	(array) $conf['utente'],
	array(
		'sigla' => '',
		'nome'  => '',
		'ruolo' => '',
		'esci'  => '',
	)
);
?>
<div class="advtr-console">

	<aside class="ac-lato">
		<div class="ac-marchio">
			<span class="ac-marchio-pin" aria-hidden="true"></span>
			<?php echo esc_html( $conf['marchio'] ); ?>
		</div>

		<nav>
			<?php foreach ( (array) $conf['menu'] as $advtr_gruppo => $advtr_voci ) : ?>
				<?php if ( ! $advtr_voci ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="ac-gruppo"><?php echo esc_html( $advtr_gruppo ); ?></div>
				<?php foreach ( $advtr_voci as $advtr_voce ) : ?>
					<a
						class="ac-voce<?php echo $advtr_voce['slug'] === $conf['attiva'] ? ' attiva' : ''; ?>"
						href="<?php echo esc_url( $advtr_voce['url'] ); ?>"
						<?php echo $advtr_voce['slug'] === $conf['attiva'] ? 'aria-current="page"' : ''; ?>
					><?php echo esc_html( $advtr_voce['etichetta'] ); ?></a>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</nav>

		<?php if ( $advtr_utente['nome'] ) : ?>
			<div class="ac-utente">
				<span class="ac-utente-sigla" aria-hidden="true"><?php echo esc_html( $advtr_utente['sigla'] ); ?></span>
				<span>
					<span class="ac-utente-nome"><?php echo esc_html( $advtr_utente['nome'] ); ?></span><br />
					<span class="ac-utente-ruolo"><?php echo esc_html( $advtr_utente['ruolo'] ); ?></span>
				</span>
				<?php if ( $advtr_utente['esci'] ) : ?>
					<a class="ac-esci" href="<?php echo esc_url( $advtr_utente['esci'] ); ?>">
						<?php esc_html_e( 'Esci', 'advertrieste' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</aside>

	<main class="ac-corpo">
		<div class="ac-testa">
			<div>
				<h2 class="ac-titolo"><?php echo esc_html( $conf['titolo'] ); ?></h2>
				<?php if ( $conf['sottotitolo'] ) : ?>
					<p class="ac-sottotitolo"><?php echo esc_html( $conf['sottotitolo'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $conf['azioni'] ) : ?>
				<div class="ac-testa-azioni">
					<?php echo $conf['azioni']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dei componenti, già escapato. ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $conf['avviso'] ) ) : ?>
			<?php
			$advtr_avv = wp_parse_args(
				(array) $conf['avviso'],
				array(
					'tipo'   => '',
					'titolo' => '',
					'testo'  => '',
					'azione' => '',
				)
			);
			?>
			<div class="ac-avviso <?php echo esc_attr( $advtr_avv['tipo'] ); ?>">
				<span class="ac-avviso-testo">
					<?php if ( $advtr_avv['titolo'] ) : ?>
						<span class="ac-avviso-titolo"><?php echo esc_html( $advtr_avv['titolo'] ); ?></span><br />
					<?php endif; ?>
					<span class="ac-avviso-dett"><?php echo esc_html( $advtr_avv['testo'] ); ?></span>
				</span>
				<?php if ( $advtr_avv['azione'] ) : ?>
					<?php echo $advtr_avv['azione']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup dei componenti, già escapato. ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php echo $conf['contenuto']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup delle sezioni, già escapato. ?>
	</main>
</div>
