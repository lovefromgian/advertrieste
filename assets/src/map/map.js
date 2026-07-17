/**
 * Mappa pubblica AdverTrieste (Leaflet + OpenStreetMap).
 *
 * Legge la configurazione da window.advtrMap, inizializza la mappa, disegna i
 * filtri per categoria e carica i marker dall'endpoint REST a ogni spostamento
 * o cambio di zoom. Il server applica lo zoom a due livelli (poi da lontano,
 * locale da vicino) e non restituisce mai i punti QR riservati.
 *
 * @package AdverTrieste
 */
( function () {
	'use strict';

	var cfg = window.advtrMap;
	if ( ! cfg || typeof L === 'undefined' ) {
		return;
	}

	function debounce( fn, wait ) {
		var t;
		return function () {
			var ctx = this;
			var args = arguments;
			window.clearTimeout( t );
			t = window.setTimeout( function () {
				fn.apply( ctx, args );
			}, wait );
		};
	}

	function initMap( el ) {
		var map = L.map( el ).setView( cfg.center, cfg.zoom );

		L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		} ).addTo( map );

		var layer = L.layerGroup().addTo( map );
		var categoriaAttiva = '';

		function markerIcon( m ) {
			var cls = 'advtr-marker tipo-' + m.type;
			var size = 18;
			if ( m.in_evidenza ) {
				cls += ' in-evidenza';
				size = 22;
			}
			return L.divIcon( {
				className: '',
				html: '<span class="' + cls + '"></span>',
				iconSize: [ size, size ],
				iconAnchor: [ size / 2, size / 2 ]
			} );
		}

		function popupHtml( m ) {
			var wrap = document.createElement( 'div' );
			wrap.className = 'advtr-popup';
			var title = document.createElement( 'div' );
			title.className = 'advtr-popup-title';
			title.textContent = m.title;
			wrap.appendChild( title );
			if ( m.permalink ) {
				var a = document.createElement( 'a' );
				a.className = 'advtr-popup-link';
				a.href = m.permalink;
				a.textContent = cfg.i18n.apri;
				wrap.appendChild( a );
			}
			return wrap;
		}

		function loadMarkers() {
			var b = map.getBounds();
			var params = new URLSearchParams( {
				min_lat: b.getSouth(),
				min_lng: b.getWest(),
				max_lat: b.getNorth(),
				max_lng: b.getEast(),
				zoom: map.getZoom()
			} );
			if ( categoriaAttiva ) {
				params.set( 'categoria', categoriaAttiva );
			}

			window.fetch( cfg.endpoint + '?' + params.toString(), {
				headers: { Accept: 'application/json' }
			} ).then( function ( r ) {
				return r.ok ? r.json() : [];
			} ).then( function ( markers ) {
				layer.clearLayers();
				( markers || [] ).forEach( function ( m ) {
					if ( typeof m.lat !== 'number' || typeof m.lng !== 'number' ) {
						return;
					}
					var marker = L.marker( [ m.lat, m.lng ], { icon: markerIcon( m ) } );
					marker.bindPopup( popupHtml( m ) );
					marker.addTo( layer );
				} );
			} ).catch( function () {
				layer.clearLayers();
			} );
		}

		function buildFiltri() {
			var box = document.querySelector( '[data-advtr-filtri="' + el.id + '"]' );
			if ( ! box || ! cfg.categorie ) {
				return;
			}

			function makeBtn( slug, label ) {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'advtr-filtro-btn' + ( slug === categoriaAttiva ? ' attivo' : '' );
				btn.textContent = label;
				btn.addEventListener( 'click', function () {
					categoriaAttiva = slug;
					box.querySelectorAll( '.advtr-filtro-btn' ).forEach( function ( b ) {
						b.classList.remove( 'attivo' );
					} );
					btn.classList.add( 'attivo' );
					loadMarkers();
				} );
				return btn;
			}

			box.appendChild( makeBtn( '', cfg.i18n.tutte ) );
			cfg.categorie.forEach( function ( c ) {
				box.appendChild( makeBtn( c.slug, c.name ) );
			} );
		}

		buildFiltri();
		map.on( 'moveend', debounce( loadMarkers, 250 ) );
		loadMarkers();
	}

	document.querySelectorAll( '[data-advtr-map]' ).forEach( initMap );
} )();
