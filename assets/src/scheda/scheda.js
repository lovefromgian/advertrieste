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

	// Recensioni Google (se attive): caricamento asincrono.
	var revBox = document.querySelector( '[data-advtr-reviews]' );
	if ( revBox && cfg.reviewsUrl ) {
		window.fetch( cfg.reviewsUrl, { headers: { Accept: 'application/json' } } )
			.then( function ( r ) {
				return r.ok ? r.json() : null;
			} )
			.then( function ( d ) {
				if ( ! d || ! d.recensioni || ! d.recensioni.length ) {
					return;
				}
				renderReviews( revBox, d );
				revBox.hidden = false;
			} )
			.catch( function () {} );
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

	function stelle( rating ) {
		var full = Math.round( rating );
		var s = '';
		for ( var i = 0; i < 5; i++ ) {
			s += i < full ? '★' : '☆';
		}
		return s;
	}

	function renderReviews( box, d ) {
		box.innerHTML = '';
		var h = el( 'h2', null, ( cfg.i18n && cfg.i18n.recensioni ) || 'Recensioni' );
		box.appendChild( h );
		if ( d.media_rating ) {
			var head = el( 'div', 'advtr-rev-media' );
			head.appendChild( el( 'span', 'advtr-rev-stelle', stelle( d.media_rating ) ) );
			head.appendChild( el( 'span', 'advtr-rev-media-num',
				d.media_rating.toFixed( 1 ) + ' (' + d.totale + ' ' + ( ( cfg.i18n && cfg.i18n.basato ) || '' ) + ')' ) );
			box.appendChild( head );
		}
		d.recensioni.forEach( function ( r ) {
			var c = el( 'div', 'advtr-rev' );
			var top = el( 'div', 'advtr-rev-top' );
			top.appendChild( el( 'span', 'advtr-rev-autore', r.autore ) );
			top.appendChild( el( 'span', 'advtr-rev-stelle', stelle( r.rating ) ) );
			c.appendChild( top );
			if ( r.quando ) {
				c.appendChild( el( 'div', 'advtr-rev-quando', r.quando ) );
			}
			if ( r.testo ) {
				c.appendChild( el( 'p', 'advtr-rev-testo', r.testo ) );
			}
			box.appendChild( c );
		} );
	}

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
