/**
 * Area clienti: segnaposto trascinabile e conferma di eliminazione.
 *
 * Nessuna dipendenza oltre a Leaflet (già usato dalla mappa pubblica). Tutto
 * ciò che fa è progressivo: senza JavaScript i form restano compilabili a mano,
 * comprese le coordinate.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	/**
	 * Segnaposto trascinabile che scrive latitudine e longitudine nel form.
	 */
	function initPicker() {
		var box = document.querySelector( '[data-advtr-picker]' );
		if ( ! box || typeof L === 'undefined' ) {
			return;
		}

		var campoLat = document.querySelector( '[data-advtr-lat]' );
		var campoLng = document.querySelector( '[data-advtr-lng]' );

		var lat = parseFloat( box.getAttribute( 'data-lat' ) );
		var lng = parseFloat( box.getAttribute( 'data-lng' ) );
		if ( isNaN( lat ) || isNaN( lng ) ) {
			return;
		}

		var mappa = L.map( box ).setView( [ lat, lng ], 16 );
		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		} ).addTo( mappa );

		var segna = L.marker( [ lat, lng ], { draggable: true } ).addTo( mappa );

		function aggiorna( posizione ) {
			if ( campoLat ) {
				campoLat.value = posizione.lat.toFixed( 6 );
			}
			if ( campoLng ) {
				campoLng.value = posizione.lng.toFixed( 6 );
			}
		}

		segna.on( 'dragend', function () {
			aggiorna( segna.getLatLng() );
		} );

		// Anche un clic sulla mappa riposiziona il segnaposto.
		mappa.on( 'click', function ( e ) {
			segna.setLatLng( e.latlng );
			aggiorna( e.latlng );
		} );

		// Se l'utente scrive le coordinate a mano, la mappa segue.
		[ campoLat, campoLng ].forEach( function ( campo ) {
			if ( ! campo ) {
				return;
			}
			campo.addEventListener( 'change', function () {
				var nLat = parseFloat( campoLat.value );
				var nLng = parseFloat( campoLng.value );
				if ( isNaN( nLat ) || isNaN( nLng ) ) {
					return;
				}
				segna.setLatLng( [ nLat, nLng ] );
				mappa.setView( [ nLat, nLng ] );
			} );
		} );
	}

	/**
	 * Conferma in due tempi sui pulsanti di eliminazione.
	 *
	 * Volutamente NON usa window.confirm: un dialog modale blocca la pagina e
	 * si comporta male dentro i temi. Il primo clic cambia l'etichetta, il
	 * secondo invia davvero.
	 */
	function initConferme() {
		document.querySelectorAll( '[data-advtr-conferma]' ).forEach( function ( form ) {
			var btn = form.querySelector( 'button[type="submit"]' );
			if ( ! btn ) {
				return;
			}
			var etichetta = btn.textContent;
			var armato = false;
			var timer;

			form.addEventListener( 'submit', function ( e ) {
				if ( armato ) {
					return;
				}
				e.preventDefault();
				armato = true;
				btn.textContent = form.getAttribute( 'data-advtr-conferma' );
				btn.classList.add( 'conferma' );

				// Si disarma da solo: nessuna trappola per il clic successivo.
				timer = window.setTimeout( function () {
					armato = false;
					btn.textContent = etichetta;
					btn.classList.remove( 'conferma' );
				}, 4000 );
			} );

			btn.addEventListener( 'blur', function () {
				window.clearTimeout( timer );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initPicker();
			initConferme();
		} );
	} else {
		initPicker();
		initConferme();
	}
} )();
