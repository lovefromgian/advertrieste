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

		initGeocode( mappa, segna, aggiorna );
	}

	/**
	 * Pulsante "Trova dall'indirizzo".
	 *
	 * Riusa il campo Indirizzo già compilato nei contatti invece di chiederlo una
	 * seconda volta: due campi per lo stesso dato divergono al primo cambio di
	 * sede. Il risultato è comunque solo un punto di partenza — il geocoder
	 * restituisce il centro dell'indirizzo, non l'ingresso del locale.
	 *
	 * @param {Object}   mappa   Mappa Leaflet.
	 * @param {Object}   segna   Segnaposto.
	 * @param {Function} scrivi  Callback che aggiorna i campi coordinate.
	 */
	function initGeocode( mappa, segna, scrivi ) {
		var cfg = window.advtrCliente;
		var bottone = document.querySelector( '[data-advtr-geocode]' );
		var esito = document.querySelector( '[data-advtr-geo-esito]' );
		var campoInd = document.querySelector( '[name="advtr_indirizzo"]' );

		if ( ! cfg || ! cfg.geocode || ! bottone || ! campoInd ) {
			return;
		}

		function messaggio( testo, tipo ) {
			if ( ! esito ) {
				return;
			}
			esito.textContent = testo;
			esito.className = 'advtr-geo-esito' + ( tipo ? ' ' + tipo : '' );
		}

		bottone.addEventListener( 'click', function () {
			var indirizzo = campoInd.value.trim();
			if ( ! indirizzo ) {
				messaggio( cfg.i18n.senzaIndirizzo, 'errore' );
				campoInd.focus();
				return;
			}

			bottone.disabled = true;
			messaggio( cfg.i18n.ricerca );

			window.fetch( cfg.geocode + '?indirizzo=' + encodeURIComponent( indirizzo ), {
				headers: {
					Accept: 'application/json',
					'X-WP-Nonce': cfg.nonce
				}
			} ).then( function ( r ) {
				return r.json().then( function ( d ) {
					return { ok: r.ok, dati: d };
				} );
			} ).then( function ( res ) {
				bottone.disabled = false;

				if ( ! res.ok || ! res.dati || typeof res.dati.lat !== 'number' ) {
					messaggio( ( res.dati && res.dati.message ) || cfg.i18n.errore, 'errore' );
					return;
				}

				var posizione = L.latLng( res.dati.lat, res.dati.lng );
				segna.setLatLng( posizione );
				mappa.setView( posizione, 18 );
				scrivi( posizione );
				messaggio( cfg.i18n.trovato, 'ok' );
			} ).catch( function () {
				bottone.disabled = false;
				messaggio( cfg.i18n.errore, 'errore' );
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

	/**
	 * Pulsante "Genera" accanto ai campi password.
	 *
	 * Serve a chi crea account per altri: dover inventare una password ogni
	 * volta porta a riusare sempre la stessa. Il valore resta visibile perché
	 * va dettato al cliente, e non viene mai inviato altrove.
	 */
	function initGeneraPassword() {
		var ALFABETO = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

		document.querySelectorAll( 'input[data-advtr-genera]' ).forEach( function ( campo ) {
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'ac-btn ac-btn-neutro advtr-genera';
			btn.textContent = campo.getAttribute( 'data-advtr-genera' );

			btn.addEventListener( 'click', function () {
				var valori = new Uint32Array( 14 );
				window.crypto.getRandomValues( valori );
				var pass = '';
				for ( var i = 0; i < valori.length; i++ ) {
					pass += ALFABETO.charAt( valori[ i ] % ALFABETO.length );
				}
				campo.value = pass;
				campo.focus();
				campo.select();
			} );

			campo.insertAdjacentElement( 'afterend', btn );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initPicker();
			initConferme();
			initGeneraPassword();
		} );
	} else {
		initPicker();
		initConferme();
		initGeneraPassword();
	}
} )();
