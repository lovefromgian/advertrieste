/**
 * Meta box del punto QR: ricerca indirizzo e segnaposto trascinabile.
 *
 * La ricerca è un aiuto per avvicinarsi, non la verità: il geocoder restituisce
 * il centroide dell'indirizzo, che per una piazza dista decine di metri dal
 * punto in cui sta fisicamente l'espositore. Le coordinate salvate restano
 * quelle del segnaposto, che l'amministratore posiziona a mano.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrPuntoQr;
	if ( ! cfg || typeof L === 'undefined' ) {
		return;
	}

	var box = document.getElementById( 'advtr-qr-picker' );
	var campoLat = document.getElementById( 'advtr_lat' );
	var campoLng = document.getElementById( 'advtr_lng' );
	var campoInd = document.getElementById( 'advtr_indirizzo' );
	var bottone = document.getElementById( 'advtr-geocode-btn' );
	var esito = document.getElementById( 'advtr-geocode-esito' );

	if ( ! box || ! campoLat || ! campoLng ) {
		return;
	}

	var lat = parseFloat( campoLat.value );
	var lng = parseFloat( campoLng.value );
	var impostato = ! isNaN( lat ) && ! isNaN( lng );
	if ( ! impostato ) {
		lat = cfg.centro[ 0 ];
		lng = cfg.centro[ 1 ];
	}

	var mappa = L.map( box ).setView( [ lat, lng ], impostato ? 17 : 13 );
	L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; OpenStreetMap'
	} ).addTo( mappa );

	var segna = L.marker( [ lat, lng ], { draggable: true } ).addTo( mappa );

	function scrivi( posizione ) {
		campoLat.value = posizione.lat.toFixed( 6 );
		campoLng.value = posizione.lng.toFixed( 6 );
	}

	if ( impostato ) {
		scrivi( segna.getLatLng() );
	}

	segna.on( 'dragend', function () {
		scrivi( segna.getLatLng() );
		mostra( '' );
	} );

	mappa.on( 'click', function ( e ) {
		segna.setLatLng( e.latlng );
		scrivi( e.latlng );
		mostra( '' );
	} );

	// Coordinate scritte a mano: la mappa segue.
	[ campoLat, campoLng ].forEach( function ( campo ) {
		campo.addEventListener( 'change', function () {
			var a = parseFloat( campoLat.value );
			var b = parseFloat( campoLng.value );
			if ( isNaN( a ) || isNaN( b ) ) {
				return;
			}
			segna.setLatLng( [ a, b ] );
			mappa.setView( [ a, b ], 17 );
		} );
	} );

	/**
	 * Scrive un messaggio accanto al pulsante.
	 *
	 * @param {string} testo   Messaggio.
	 * @param {string} colore  Colore facoltativo.
	 */
	function mostra( testo, colore ) {
		if ( ! esito ) {
			return;
		}
		esito.textContent = testo;
		esito.style.color = colore || '';
	}

	if ( ! bottone || ! campoInd ) {
		return;
	}

	bottone.addEventListener( 'click', function () {
		var indirizzo = campoInd.value.trim();
		if ( ! indirizzo ) {
			mostra( cfg.i18n.erroreGenerico, '#b32d2e' );
			return;
		}

		bottone.disabled = true;
		mostra( cfg.i18n.ricerca );

		window.fetch( cfg.endpoint + '?indirizzo=' + encodeURIComponent( indirizzo ), {
			headers: {
				Accept: 'application/json',
				'X-WP-Nonce': cfg.nonce
			}
		} ).then( function ( r ) {
			return r.json().then( function ( dati ) {
				return { ok: r.ok, dati: dati };
			} );
		} ).then( function ( res ) {
			bottone.disabled = false;

			if ( ! res.ok || ! res.dati || typeof res.dati.lat !== 'number' ) {
				mostra( ( res.dati && res.dati.message ) || cfg.i18n.erroreGenerico, '#b32d2e' );
				return;
			}

			var posizione = L.latLng( res.dati.lat, res.dati.lng );
			segna.setLatLng( posizione );
			mappa.setView( posizione, 18 );
			scrivi( posizione );
			mostra( cfg.i18n.trovato, '#00844a' );
		} ).catch( function () {
			bottone.disabled = false;
			mostra( cfg.i18n.erroreGenerico, '#b32d2e' );
		} );
	} );
} )();
