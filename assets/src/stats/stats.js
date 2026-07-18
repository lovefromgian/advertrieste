/**
 * Dashboard statistiche cliente.
 *
 * Carica i dati da `GET /stats/{id}` (autenticato con nonce) e li renderizza in
 * stat tiles + un grafico a barre in SVG, senza librerie esterne.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrStats;
	if ( ! cfg ) {
		return;
	}
	var root = document.querySelector( '[data-advtr-stats]' );
	if ( ! root ) {
		return;
	}
	var panel = root.querySelector( '[data-advtr-stats-panel]' );

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

	function tile( label, value ) {
		var t = el( 'div', 'advtr-stat-tile' );
		t.appendChild( el( 'div', 'advtr-stat-value', String( value ) ) );
		t.appendChild( el( 'div', 'advtr-stat-label', label ) );
		return t;
	}

	function barChart( series ) {
		var days = Object.keys( series );
		var max = 0;
		days.forEach( function ( d ) {
			if ( series[ d ] > max ) {
				max = series[ d ];
			}
		} );
		var w = Math.max( days.length * 12, 200 );
		var h = 120;
		var svgns = 'http://www.w3.org/2000/svg';
		var svg = document.createElementNS( svgns, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 ' + w + ' ' + h );
		svg.setAttribute( 'class', 'advtr-barchart' );
		svg.setAttribute( 'preserveAspectRatio', 'none' );
		var bw = w / days.length;
		days.forEach( function ( d, i ) {
			var val = series[ d ];
			var bh = max > 0 ? ( val / max ) * ( h - 4 ) : 0;
			var rect = document.createElementNS( svgns, 'rect' );
			rect.setAttribute( 'x', ( i * bw + 1 ).toFixed( 1 ) );
			rect.setAttribute( 'y', ( h - bh ).toFixed( 1 ) );
			rect.setAttribute( 'width', Math.max( bw - 2, 1 ).toFixed( 1 ) );
			rect.setAttribute( 'height', bh.toFixed( 1 ) );
			rect.setAttribute( 'class', 'advtr-bar' );
			var title = document.createElementNS( svgns, 'title' );
			title.textContent = d + ': ' + val;
			rect.appendChild( title );
			svg.appendChild( rect );
		} );
		return svg;
	}

	function render( data ) {
		panel.innerHTML = '';

		var title = el( 'h4', 'advtr-stats-titolo', data.titolo || '' );
		panel.appendChild( title );

		if ( data.novita ) {
			panel.appendChild( el( 'p', 'advtr-stats-novita', cfg.i18n.novita ) );
		}

		var tiles = el( 'div', 'advtr-stat-tiles' );
		var t = data.totali || {};
		tiles.appendChild( tile( cfg.i18n.view, t.view || 0 ) );
		tiles.appendChild( tile( cfg.i18n.map_click, t.map_click || 0 ) );
		tiles.appendChild( tile( cfg.i18n.coupon, t.coupon || 0 ) );
		tiles.appendChild( tile( cfg.i18n.contact, t.contact || 0 ) );
		panel.appendChild( tiles );

		panel.appendChild( el( 'h5', 'advtr-stats-sub', cfg.i18n.andamento ) );
		panel.appendChild( barChart( data.serie_visite || {} ) );
	}

	function load( id ) {
		panel.textContent = '…';
		window.fetch( cfg.base + id, {
			headers: { Accept: 'application/json', 'X-WP-Nonce': cfg.nonce },
			credentials: 'same-origin'
		} ).then( function ( r ) {
			return r.ok ? r.json() : null;
		} ).then( function ( data ) {
			if ( data ) {
				render( data );
			} else {
				panel.textContent = cfg.i18n.errore;
			}
		} ).catch( function () {
			panel.textContent = cfg.i18n.errore;
		} );
	}

	var select = root.querySelector( '[data-advtr-stats-select]' );
	var single = root.querySelector( '[data-advtr-stats-single]' );
	if ( select ) {
		select.addEventListener( 'change', function () {
			load( select.value );
		} );
		load( select.value );
	} else if ( single ) {
		load( single.getAttribute( 'data-advtr-stats-single' ) );
	} else if ( cfg.schede && cfg.schede.length ) {
		load( cfg.schede[ 0 ].id );
	}
} )();
