/**
 * Form esercente per la validazione dei coupon.
 *
 * Invia il codice presentato dal cliente a `POST /offerta/{id}/redeem` (con nonce)
 * e mostra l'esito. L'autorizzazione è verificata lato server.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrValida;
	if ( ! cfg ) {
		return;
	}
	var root = document.querySelector( '[data-advtr-valida]' );
	if ( ! root ) {
		return;
	}

	var selOfferta = root.querySelector( '[data-advtr-valida-offerta]' );
	var inputCod = root.querySelector( '[data-advtr-valida-codice]' );
	var btn = root.querySelector( '[data-advtr-valida-btn]' );
	var esito = root.querySelector( '[data-advtr-valida-esito]' );

	function setEsito( testo, ok ) {
		esito.textContent = testo;
		esito.className = 'advtr-valida-esito ' + ( ok ? 'advtr-ok' : 'advtr-ko' );
	}

	btn.addEventListener( 'click', function () {
		var id = selOfferta.value;
		var codice = ( inputCod.value || '' ).trim();
		if ( ! id || ! codice ) {
			return;
		}
		btn.disabled = true;
		window.fetch( cfg.base + id + '/redeem', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			credentials: 'same-origin',
			body: JSON.stringify( { codice: codice } )
		} ).then( function ( r ) {
			return r.json().then( function ( data ) {
				return { status: r.status, data: data };
			} );
		} ).then( function ( res ) {
			if ( res.status === 200 && res.data && res.data.ok ) {
				setEsito( cfg.i18n.ok + ' ' + res.data.riscatti, true );
				inputCod.value = '';
			} else {
				var msg = ( res.data && res.data.message ) ? res.data.message : String( res.status );
				setEsito( cfg.i18n.errore + ' ' + msg, false );
			}
		} ).catch( function () {
			setEsito( cfg.i18n.errore, false );
		} ).then( function () {
			btn.disabled = false;
		} );
	} );
} )();
