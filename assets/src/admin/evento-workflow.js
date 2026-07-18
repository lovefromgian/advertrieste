/**
 * Azioni del workflow eventi in bacheca (Invia in revisione / Approva).
 *
 * Chiama gli endpoint REST con il nonce; l'autorizzazione è verificata lato
 * server. Aggiorna lo stato mostrato senza ricaricare.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrEventoWf;
	if ( ! cfg ) {
		return;
	}
	var box = document.querySelector( '[data-advtr-wf]' );
	if ( ! box ) {
		return;
	}

	var id = box.getAttribute( 'data-advtr-wf' );
	var esito = box.querySelector( '.advtr-wf-esito' );
	var statoEl = box.querySelector( '.advtr-wf-stato' );
	var btnSubmit = box.querySelector( '[data-advtr-wf-submit]' );
	var btnApprove = box.querySelector( '[data-advtr-wf-approve]' );

	if ( btnApprove && ! cfg.canApprove ) {
		btnApprove.style.display = 'none';
	}

	function call( azione, okMsg, nuovoStato ) {
		esito.textContent = '…';
		window.fetch( cfg.base + id + '/' + azione, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.json().then( function ( d ) {
				return { status: r.status, data: d };
			} );
		} ).then( function ( res ) {
			if ( res.status === 200 && res.data && res.data.ok ) {
				esito.textContent = okMsg;
				if ( statoEl && nuovoStato ) {
					statoEl.textContent = nuovoStato;
				}
			} else {
				var msg = ( res.data && res.data.message ) ? res.data.message : cfg.i18n.errore;
				esito.textContent = msg;
			}
		} ).catch( function () {
			esito.textContent = cfg.i18n.errore;
		} );
	}

	if ( btnSubmit ) {
		btnSubmit.addEventListener( 'click', function () {
			call( 'submit', cfg.i18n.inviato, 'In revisione' );
		} );
	}
	if ( btnApprove ) {
		btnApprove.addEventListener( 'click', function () {
			call( 'approve', cfg.i18n.approvato, 'Pubblicato' );
		} );
	}
} )();
