/**
 * Mappa dei punti QR (area riservata).
 *
 * Carica i punti dall'endpoint autenticato `/qr-map` inviando il nonce REST e i
 * cookie di sessione. Il server rifiuta le richieste non autenticate o senza la
 * capability necessaria: qui non c'è alcun dato riservato incorporato nella
 * pagina, solo la chiamata autenticata.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrQrMap;
	if ( ! cfg || typeof L === 'undefined' ) {
		return;
	}

	var el = document.querySelector( '[data-advtr-qr-map]' );
	if ( ! el ) {
		return;
	}

	var map = L.map( el ).setView( cfg.center, cfg.zoom );
	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; OpenStreetMap'
	} ).addTo( map );

	var layer = L.layerGroup().addTo( map );

	function markerIcon() {
		return L.divIcon( {
			className: '',
			html: '<span class="advtr-marker tipo-qr"></span>',
			iconSize: [ 18, 18 ],
			iconAnchor: [ 9, 9 ]
		} );
	}

	function popup( p ) {
		var wrap = document.createElement( 'div' );
		wrap.className = 'advtr-popup';
		var title = document.createElement( 'div' );
		title.className = 'advtr-popup-title';
		title.textContent = p.etichetta || ( '#' + p.id );
		wrap.appendChild( title );
		if ( p.stato ) {
			var stato = document.createElement( 'div' );
			stato.textContent = cfg.i18n.stato + ': ' + p.stato;
			wrap.appendChild( stato );
		}
		return wrap;
	}

	window.fetch( cfg.endpoint, {
		headers: {
			Accept: 'application/json',
			'X-WP-Nonce': cfg.nonce
		},
		credentials: 'same-origin'
	} ).then( function ( r ) {
		return r.ok ? r.json() : [];
	} ).then( function ( points ) {
		var bounds = [];
		( points || [] ).forEach( function ( p ) {
			if ( typeof p.lat !== 'number' || typeof p.lng !== 'number' ) {
				return;
			}
			L.marker( [ p.lat, p.lng ], { icon: markerIcon() } ).bindPopup( popup( p ) ).addTo( layer );
			bounds.push( [ p.lat, p.lng ] );
		} );
		if ( bounds.length ) {
			map.fitBounds( bounds, { padding: [ 30, 30 ], maxZoom: 16 } );
		}
	} ).catch( function () {
		/* silenzioso: nessun dato riservato da mostrare in caso di errore */
	} );
} )();
