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
		var inquadrato = false;

		// Riferimenti della schermata "Esplora" (schermata 06). Se mancano,
		// siamo nel contenitore semplice e la lista non esiste: tutto il resto
		// continua a funzionare.
		var box = el.closest( '[data-advtr-esplora]' );
		var elenco = box ? box.querySelector( '[data-advtr-risultati]' ) : null;
		var conta = box ? box.querySelector( '[data-advtr-conta]' ) : null;
		var campoQ = box ? box.querySelector( '#ae-q' ) : null;
		var soloOfferte = false;
		var marcatori = {};

		// Categoria e ricerca arrivano dall'ingresso guidato
		// (?categoria=slug&q=testo).
		var categoriaAttiva = '';
		var ricerca = '';
		try {
			var params = new URLSearchParams( window.location.search );
			var fromUrl = params.get( 'categoria' );
			if ( fromUrl && cfg.categorie && cfg.categorie.some( function ( c ) { return c.slug === fromUrl; } ) ) {
				categoriaAttiva = fromUrl;
			}
			ricerca = ( params.get( 'q' ) || '' ).trim();
		} catch ( e ) {}

		function markerIcon( m ) {
			var cls = 'advtr-marker tipo-' + m.type;
			var size = 18;
			if ( m.in_evidenza ) {
				cls += ' in-evidenza';
				size = 22;
			}
			if ( m.in_evento ) {
				cls += ' in-evento';
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
			if ( m.novita ) {
				var badge = document.createElement( 'span' );
				badge.className = 'advtr-badge-novita';
				badge.textContent = cfg.i18n.novita;
				wrap.appendChild( badge );
			}
			if ( m.in_evento && cfg.i18n.inEvento ) {
				var ev = document.createElement( 'span' );
				ev.className = 'advtr-badge-evento';
				ev.textContent = cfg.i18n.inEvento;
				wrap.appendChild( ev );
			}
			if ( m.permalink ) {
				var a = document.createElement( 'a' );
				a.className = 'advtr-popup-link';
				a.href = m.permalink;
				a.textContent = cfg.i18n.apri;
				wrap.appendChild( a );
			}
			if ( typeof m.lat === 'number' && typeof m.lng === 'number' && cfg.i18n.indicazioni ) {
				var dir = document.createElement( 'a' );
				dir.className = 'advtr-popup-link advtr-popup-dir';
				dir.href = 'https://www.google.com/maps/dir/?api=1&destination=' + m.lat + ',' + m.lng;
				dir.target = '_blank';
				dir.rel = 'noopener';
				dir.textContent = cfg.i18n.indicazioni;
				wrap.appendChild( dir );
			}
			return wrap;
		}

		function trackMapClick( m ) {
			if ( m.type !== 'locale' || ! cfg.trackBase ) {
				return;
			}
			// Il nonce arriva solo agli utenti autenticati. Per gli anonimi si
			// invia SENZA: un nonce congelato in una pagina in cache scadrebbe e
			// il core risponderebbe 403, azzerando i conteggi senza dirlo.
			var headers = { 'Content-Type': 'application/json' };
			if ( cfg.nonce ) {
				headers['X-WP-Nonce'] = cfg.nonce;
			}
			window.fetch( cfg.trackBase + m.id + '/track', {
				method: 'POST',
				headers: headers,
				credentials: 'same-origin',
				body: JSON.stringify( { tipo: 'map_click' } )
			} ).catch( function () {} );
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
			if ( ricerca ) {
				params.set( 'q', ricerca );
			}

			window.fetch( cfg.endpoint + '?' + params.toString(), {
				headers: { Accept: 'application/json' }
			} ).then( function ( r ) {
				return r.ok ? r.json() : [];
			} ).then( function ( markers ) {
				layer.clearLayers();
				marcatori = {};
				var trovati = [];
				var visibili = [];
				( markers || [] ).forEach( function ( m ) {
					if ( typeof m.lat !== 'number' || typeof m.lng !== 'number' ) {
						return;
					}
					if ( soloOfferte && ! m.offerta ) {
						return;
					}
					var marker = L.marker( [ m.lat, m.lng ], { icon: markerIcon( m ) } );
					marker.bindPopup( popupHtml( m ) );
					marker.on( 'popupopen', function () {
						trackMapClick( m );
					} );
					marker.addTo( layer );
					marcatori[ m.id ] = marker;
					trovati.push( [ m.lat, m.lng ] );
					visibili.push( m );
				} );

				disegnaElenco( visibili );

				// Solo alla prima risposta di una ricerca: dopo, l'utente comanda.
				if ( ricerca && ! inquadrato && trovati.length ) {
					inquadrato = true;
					map.fitBounds( trovati, { padding: [ 40, 40 ], maxZoom: 17 } );
				}
			} ).catch( function () {
				layer.clearLayers();
			} );
		}

		/**
		 * Disegna l'elenco dei risultati accanto alla mappa.
		 *
		 * Costruito con createElement e textContent, mai con innerHTML: i titoli
		 * arrivano dal database e finirebbero interpretati come markup.
		 *
		 * @param {Array} elementi Marker visibili.
		 */
		function disegnaElenco( elementi ) {
			if ( ! elenco ) {
				return;
			}
			elenco.textContent = '';

			if ( conta ) {
				conta.textContent = elementi.length === 1
					? cfg.i18n.contaUno
					: cfg.i18n.conta.replace( '%d', elementi.length );
			}

			if ( ! elementi.length ) {
				var vuoto = document.createElement( 'p' );
				vuoto.className = 'ae-vuoto';
				vuoto.textContent = cfg.i18n.nessuno;
				elenco.appendChild( vuoto );
				return;
			}

			elementi.forEach( function ( m ) {
				var card = document.createElement( 'a' );
				card.className = 'ae-card' + ( m.in_evidenza ? ' evidenza' : '' );
				card.href = m.permalink || '#';
				card.setAttribute( 'data-id', m.id );

				var mini = document.createElement( 'span' );
				mini.className = 'ae-card-img tipo-' + m.type;
				if ( m.logo ) {
					mini.style.backgroundImage = 'url("' + encodeURI( m.logo ) + '")';
				}
				card.appendChild( mini );

				var testo = document.createElement( 'span' );
				testo.className = 'ae-card-testo';

				var nome = document.createElement( 'span' );
				nome.className = 'ae-card-nome';
				nome.textContent = m.title;
				testo.appendChild( nome );

				var cat = document.createElement( 'span' );
				cat.className = 'ae-card-cat';
				cat.textContent = ( m.categorie && m.categorie.length )
					? m.categorie.join( ' · ' )
					: ( m.type === 'poi' ? cfg.i18n.poi : '' );
				testo.appendChild( cat );

				if ( m.indirizzo ) {
					var ind = document.createElement( 'span' );
					ind.className = 'ae-card-ind';
					ind.textContent = m.indirizzo;
					testo.appendChild( ind );
				}

				var badge = document.createElement( 'span' );
				badge.className = 'ae-card-badge';
				if ( m.in_evidenza ) {
					badge.appendChild( pill( '★ ' + cfg.i18n.inEvidenza, 'oro' ) );
				}
				if ( m.offerta ) {
					badge.appendChild( pill( cfg.i18n.coupon, 'corallo' ) );
				}
				if ( m.novita ) {
					badge.appendChild( pill( cfg.i18n.novita, 'verde' ) );
				}
				if ( badge.childNodes.length ) {
					testo.appendChild( badge );
				}

				card.appendChild( testo );

				// Lista → mappa: il marker corrispondente si apre e si centra.
				card.addEventListener( 'click', function ( e ) {
					var mk = marcatori[ m.id ];
					if ( ! mk ) {
						return;
					}
					e.preventDefault();
					map.setView( mk.getLatLng(), Math.max( map.getZoom(), 16 ) );
					mk.openPopup();
				} );
				card.addEventListener( 'mouseenter', function () {
					var mk = marcatori[ m.id ];
					if ( mk && mk._icon ) {
						mk._icon.classList.add( 'evidenziato' );
					}
				} );
				card.addEventListener( 'mouseleave', function () {
					var mk = marcatori[ m.id ];
					if ( mk && mk._icon ) {
						mk._icon.classList.remove( 'evidenziato' );
					}
				} );

				elenco.appendChild( card );
			} );
		}

		/**
		 * Etichetta colorata.
		 *
		 * @param {string} testo  Testo.
		 * @param {string} colore Variante.
		 * @return {HTMLElement} Elemento pronto.
		 */
		function pill( testo, colore ) {
			var s = document.createElement( 'span' );
			s.className = 'ae-pill ' + colore;
			s.textContent = testo;
			return s;
		}

		function buildChip() {
			if ( ! box ) {
				return;
			}
			var chip = box.querySelectorAll( '.ae-chip-btn' );
			chip.forEach( function ( b ) {
				if ( b.getAttribute( 'data-cat' ) === categoriaAttiva && ! b.hasAttribute( 'data-solo-offerte' ) ) {
					b.classList.add( 'attivo' );
				} else if ( ! b.hasAttribute( 'data-solo-offerte' ) ) {
					b.classList.remove( 'attivo' );
				}

				b.addEventListener( 'click', function () {
					if ( b.hasAttribute( 'data-solo-offerte' ) ) {
						soloOfferte = ! soloOfferte;
						b.classList.toggle( 'attivo', soloOfferte );
						loadMarkers();
						return;
					}
					categoriaAttiva = b.getAttribute( 'data-cat' ) || '';
					chip.forEach( function ( x ) {
						if ( ! x.hasAttribute( 'data-solo-offerte' ) ) {
							x.classList.remove( 'attivo' );
						}
					} );
					b.classList.add( 'attivo' );
					loadMarkers();
				} );
			} );

			// La ricerca filtra senza ricaricare la pagina.
			if ( campoQ ) {
				campoQ.addEventListener( 'input', debounce( function () {
					ricerca = campoQ.value.trim();
					inquadrato = false;
					loadMarkers();
				}, 350 ) );
			}
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
		buildChip();
		map.on( 'moveend', debounce( loadMarkers, 250 ) );
		loadMarkers();
	}

	document.querySelectorAll( '[data-advtr-map]' ).forEach( initMap );
} )();
