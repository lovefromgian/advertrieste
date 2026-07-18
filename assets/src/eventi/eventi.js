/**
 * Front-end eventi: grandi eventi (banner + countdown + locali) ed elenco eventi.
 * Mostra solo la versione approvata servita dagli endpoint. Nessuna libreria.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrEventi;
	if ( ! cfg ) {
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
		if ( ! s ) {
			return null;
		}
		var t = Date.parse( s.replace( ' ', 'T' ) );
		return isNaN( t ) ? null : t;
	}

	function statoTesto( inizio, fine ) {
		var now = Date.now();
		if ( fine && now > fine ) {
			return { txt: cfg.i18n.concluso, cls: 'concluso' };
		}
		if ( inizio && now >= inizio ) {
			return { txt: cfg.i18n.incorso, cls: 'incorso' };
		}
		if ( inizio ) {
			var s = Math.floor( ( inizio - now ) / 1000 );
			var g = Math.floor( s / 86400 );
			s -= g * 86400;
			var h = Math.floor( s / 3600 );
			s -= h * 3600;
			var m = Math.floor( s / 60 );
			return { txt: cfg.i18n.scade + ' ' + g + cfg.i18n.giorni + ' ' + h + cfg.i18n.ore + ' ' + m + cfg.i18n.minuti, cls: 'attesa' };
		}
		return { txt: '', cls: '' };
	}

	function card( ev, grande ) {
		var c = el( 'div', 'advtr-evento-card' + ( grande ? ' grande' : '' ) );
		if ( grande && ev.immagine ) {
			var img = el( 'img', 'advtr-evento-img' );
			img.src = ev.immagine;
			img.alt = '';
			c.appendChild( img );
		}
		c.appendChild( el( 'h4', 'advtr-evento-titolo', ev.titolo ) );

		var inizio = parseDate( ev.data_inizio );
		var fine = parseDate( ev.data_fine );
		var st = statoTesto( inizio, fine );
		var cd = el( 'div', 'advtr-evento-stato ' + st.cls, st.txt );
		c.appendChild( cd );

		if ( ev.contenuto ) {
			var d = el( 'div', 'advtr-evento-desc' );
			d.innerHTML = ev.contenuto;
			c.appendChild( d );
		}

		if ( grande && ev.locali && ev.locali.length ) {
			c.appendChild( el( 'div', 'advtr-evento-locali-label', cfg.i18n.locali ) );
			var ul = el( 'ul', 'advtr-evento-locali' );
			ev.locali.forEach( function ( l ) {
				ul.appendChild( el( 'li', null, l.titolo ) );
			} );
			c.appendChild( ul );
		}

		// Countdown live se in attesa.
		if ( st.cls === 'attesa' && inizio ) {
			window.setInterval( function () {
				var s2 = statoTesto( inizio, fine );
				cd.textContent = s2.txt;
				cd.className = 'advtr-evento-stato ' + s2.cls;
			}, 60000 );
		}
		return c;
	}

	function renderInto( sel, endpoint, grande ) {
		var root = document.querySelector( sel );
		if ( ! root ) {
			return;
		}
		window.fetch( endpoint, { headers: { Accept: 'application/json' } } )
			.then( function ( r ) {
				return r.ok ? r.json() : [];
			} )
			.then( function ( list ) {
				root.innerHTML = '';
				if ( ! list || ! list.length ) {
					root.appendChild( el( 'p', 'advtr-eventi-vuoto', cfg.i18n.nessuno ) );
					return;
				}
				list.forEach( function ( ev ) {
					root.appendChild( card( ev, grande ) );
				} );
			} )
			.catch( function () {
				root.appendChild( el( 'p', 'advtr-eventi-vuoto', cfg.i18n.nessuno ) );
			} );
	}

	renderInto( '[data-advtr-grandi-eventi]', cfg.grandi, true );
	renderInto( '[data-advtr-eventi]', cfg.eventi, false );
} )();
