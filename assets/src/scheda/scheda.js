/**
 * Scheda attività: registra la visita, traccia i click sui contatti e disegna
 * la mini-mappa della posizione. Nessuna libreria oltre Leaflet.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrScheda;
	if ( ! cfg || ! cfg.id ) {
		return;
	}

	function track( tipo, meta ) {
		if ( ! cfg.trackBase || ! cfg.nonce ) {
			return;
		}
		var body = { tipo: tipo };
		if ( meta ) {
			body.meta = meta;
		}
		window.fetch( cfg.trackBase + cfg.id + '/track', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			credentials: 'same-origin',
			body: JSON.stringify( body )
		} ).catch( function () {} );
	}

	// Visita (§1.6): una per caricamento, il server applica il rate-limit.
	track( 'view' );

	// Click sui contatti.
	document.querySelectorAll( '[data-advtr-contact]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function () {
			track( 'contact', a.getAttribute( 'data-advtr-contact' ) );
		} );
	} );

	// Mini-mappa.
	var el = document.querySelector( '[data-advtr-scheda-map]' );
	if ( el && typeof L !== 'undefined' && cfg.lat && cfg.lng ) {
		var map = L.map( el, { scrollWheelZoom: false } ).setView( [ cfg.lat, cfg.lng ], 16 );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		} ).addTo( map );
		L.marker( [ cfg.lat, cfg.lng ], {
			icon: L.divIcon( {
				className: '',
				html: '<span class="advtr-marker tipo-locale"></span>',
				iconSize: [ 18, 18 ],
				iconAnchor: [ 9, 9 ]
			} )
		} ).addTo( map );
	}
} )();
