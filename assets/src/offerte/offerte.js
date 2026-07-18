/**
 * Elenco pubblico delle offerte con countdown alla scadenza.
 *
 * Carica le offerte attive da `GET /offerte` e aggiorna il countdown ogni
 * secondo. Nessuna libreria esterna.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrOfferte;
	if ( ! cfg ) {
		return;
	}
	var root = document.querySelector( '[data-advtr-offerte]' );
	if ( ! root ) {
		return;
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text !== undefined ) {
			n.textContent = text;
		}
		return n;
	}

	function parseDate( s ) {
		// 'Y-m-d H:i:s' interpretata come ora locale.
		if ( ! s ) {
			return null;
		}
		var t = Date.parse( s.replace( ' ', 'T' ) );
		return isNaN( t ) ? null : t;
	}

	function fmtCountdown( ms ) {
		if ( ms <= 0 ) {
			return cfg.i18n.scaduta;
		}
		var s = Math.floor( ms / 1000 );
		var g = Math.floor( s / 86400 );
		s -= g * 86400;
		var h = Math.floor( s / 3600 );
		s -= h * 3600;
		var m = Math.floor( s / 60 );
		s -= m * 60;
		return cfg.i18n.scade + ' ' + g + cfg.i18n.giorni + ' ' + h + cfg.i18n.ore + ' ' +
			m + cfg.i18n.minuti + ' ' + s + cfg.i18n.secondi;
	}

	function render( offerte ) {
		root.innerHTML = '';
		if ( ! offerte || ! offerte.length ) {
			root.appendChild( el( 'p', 'advtr-offerte-vuoto', cfg.i18n.nessuna ) );
			return;
		}
		var timers = [];
		offerte.forEach( function ( o ) {
			var card = el( 'div', 'advtr-offerta-card' );
			card.appendChild( el( 'h4', 'advtr-offerta-titolo', o.titolo ) );
			if ( o.locale_titolo ) {
				card.appendChild( el( 'div', 'advtr-offerta-locale', o.locale_titolo ) );
			}
			if ( o.descrizione ) {
				var desc = el( 'div', 'advtr-offerta-desc' );
				desc.innerHTML = o.descrizione;
				card.appendChild( desc );
			}
			if ( o.codice ) {
				var cod = el( 'div', 'advtr-offerta-codice' );
				cod.appendChild( el( 'span', 'advtr-offerta-codice-label', cfg.i18n.codice + ': ' ) );
				cod.appendChild( el( 'code', null, o.codice ) );
				card.appendChild( cod );
			}
			var cd = el( 'div', 'advtr-offerta-countdown' );
			card.appendChild( cd );
			root.appendChild( card );

			var scad = parseDate( o.data_scadenza );
			if ( scad ) {
				var tick = function () {
					cd.textContent = fmtCountdown( scad - Date.now() );
				};
				tick();
				timers.push( window.setInterval( tick, 1000 ) );
			}
		} );
	}

	window.fetch( cfg.endpoint, { headers: { Accept: 'application/json' } } )
		.then( function ( r ) {
			return r.ok ? r.json() : [];
		} )
		.then( render )
		.catch( function () {
			root.appendChild( el( 'p', 'advtr-offerte-vuoto', cfg.i18n.nessuna ) );
		} );
} )();
