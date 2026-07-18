# AdverTrieste

Plugin WordPress custom: directory su mappa di attività e luoghi del territorio di Trieste — schede clienti, statistiche, offerte/coupon, eventi e area riservata.

> Fonte di verità: `CLAUDE.md` (convenzioni), `docs/specifiche-funzionali.md` (requisiti e stato), `docs/architettura.md` (dati, REST, cron, sicurezza).

## Stato

**Scaffold iniziale** (branch `feature/scaffold`). Nessuna funzionalità implementata: solo impalcatura, bootstrap, autoloader e scheletri dei Custom Post Type.

## Requisiti

- PHP ≥ 8.3
- WordPress ≥ 6.0
- (in seguito) WooCommerce + Subscriptions per pagamenti/abbonamenti

## Struttura

```
advertrieste/
├── advertrieste.php          # bootstrap: header, costanti, autoloader, avvio
├── includes/
│   ├── class-autoloader.php  # autoloader AdverTrieste\ -> includes/ (file class-*.php)
│   ├── class-activator.php   # attivazione/disattivazione (seeding termini, rewrite)
│   ├── class-plugin.php      # singleton di avvio: registra CPT, tassonomie, meta, REST, mappa
│   ├── cpt/                  # Custom Post Type e tassonomie (uno per file)
│   │   ├── class-locale.php     # attività commerciale (pubblico)
│   │   ├── class-poi.php        # punto d'interesse (pubblico)
│   │   ├── class-evento.php     # evento (pubblico se pubblicato)
│   │   ├── class-puntoqr.php    # espositore/QR — RISERVATO, non pubblico
│   │   ├── class-offerta.php    # offerta/promozione a tempo (pubblico)
│   │   └── class-categoria.php  # tassonomia `categoria` (locale+poi) + seeding
│   ├── coupon/               # coupon/riscatti
│   │   └── class-coupon.php     # tabella advtr_coupon, riscatti, scadenza offerte
│   ├── cron/                 # job pianificati (WP-Cron)
│   │   └── class-cron.php       # advtr_expire_coupons (giornaliero)
│   ├── access/               # ruoli, capability e controlli di accesso
│   │   ├── class-roles.php      # ruoli cliente_locale/organizzatore_evento + capability
│   │   └── class-access.php     # helper: can_view_qr_map(), is_cliente()
│   ├── meta/                 # meta box e campi
│   │   ├── class-localemeta.php # meta del CPT `locale` (register + box + save)
│   │   └── class-puntoqrmeta.php # meta del CPT `punto_qr` (coordinate + stato)
│   ├── stats/                # statistiche per scheda
│   │   └── class-stats.php      # tabella advtr_stats, record eventi, query, soglia
│   ├── rest/                 # endpoint REST (namespace advertrieste/v1)
│   │   ├── class-markers.php    # GET /map/markers (bbox+zoom+categoria, mai punto_qr)
│   │   ├── class-qrmap.php      # GET /qr-map — RISERVATO (auth + advtr_view_qr_map)
│   │   ├── class-track.php      # POST /locale/{id}/track (nonce + rate-limit)
│   │   ├── class-stats.php      # GET /stats/{id} — owner/admin
│   │   └── class-offerte.php    # GET /offerte + POST /offerta/{id}/redeem
│   └── frontend/             # front-end pubblico e riservato
│       ├── class-map.php            # shortcode [advtr_map] + enqueue Leaflet
│       ├── class-reservedarea.php   # shortcode [advtr_area_riservata] + mappa QR
│       ├── class-statsdashboard.php # shortcode [advtr_statistiche] (tiles + grafico)
│       └── class-offerte.php        # shortcode [advtr_offerte] + [advtr_valida_coupon]
├── assets/
│   ├── src/admin/locale-meta.js  # media picker (logo + galleria) del meta box
│   ├── src/map/map.js            # mappa Leaflet: fetch marker + filtri + popup + track
│   ├── src/map/map.css           # stili mappa, marker, badge
│   ├── src/qr-map/qr-map.js      # mappa QR riservata (fetch autenticato con nonce)
│   ├── src/stats/stats.js        # dashboard: stat tiles + grafico a barre SVG
│   ├── src/stats/stats.css       # stili dashboard
│   └── vendor/leaflet/           # Leaflet 1.9.4 (bundle locale, no CDN)
├── templates/                # template front-end del plugin
│   ├── admin/locale-meta-box.php # markup del meta box "Dati locale"
│   ├── map.php                   # contenitore mappa dello shortcode
│   ├── area-riservata.php        # dashboard area riservata + mappa QR
│   └── statistiche.php           # dashboard statistiche
├── docs/                     # specifiche, architettura, deploy
├── composer.json             # dev tooling (PHPCS + WPCS)
└── phpcs.xml                 # regole WordPress Coding Standards
```

## Mappa

Inserire la mappa pubblica in una pagina con lo shortcode:

```
[advtr_map zoom="13" height="500"]
```

Attributi: `lat`, `lng` (centro, default Trieste), `zoom` (default 13), `height` (px, default 500). I marker sono caricati dall'endpoint `GET advertrieste/v1/map/markers` in base a bounding box, zoom e categoria; i `poi` compaiono a zoom basso, i `locale` a zoom alto. I `punto_qr` non sono MAI inclusi.

## Area riservata & ruoli

Ruoli custom (installati all'attivazione): `cliente_locale`, `organizzatore_evento`. Capability: `advtr_view_qr_map`, `advtr_edit_own_locale`, `advtr_submit_evento`, `advtr_approve_evento` (tutte assegnate anche all'amministratore).

Shortcode `[advtr_area_riservata]`: gate lato server (non loggato → invito al login; autenticato non-cliente → avviso; cliente con `advtr_view_qr_map` → dashboard + **mappa dei punti QR**). Le coordinate dei `punto_qr` sono servite SOLO dall'endpoint autenticato `GET advertrieste/v1/qr-map` (permission: autenticato + `advtr_view_qr_map`); non compaiono mai nell'endpoint pubblico né nella pagina.

> Visibilità mappa QR: attualmente ogni cliente con capability vede l'intera rete (decisione da confermare — vedi specifiche §2.5).

## Statistiche

Tabella `{prefix}advtr_stats` (creata all'attivazione) con eventi `view`, `map_click`, `coupon`, `contact`.

- **Scrittura**: `POST advertrieste/v1/locale/{id}/track` — pubblica, protetta da nonce REST + rate-limit (60s per IP/scheda/tipo, così non si gonfiano i conteggi). La mappa pubblica traccia `map_click` all'apertura del popup.
- **Lettura**: `GET advertrieste/v1/stats/{id}` — solo proprietario della scheda o admin.
- **Dashboard**: shortcode `[advtr_statistiche]` (nell'area riservata o in una pagina) — stat tiles + grafico a barre (SVG, senza librerie). Attributo `id` opzionale.
- **Soglia "Novità" (§1.6)**: sotto `Stats::SOGLIA_VISITE` (20) la scheda è "Novità" (badge sulla mappa) e non mostra il conteggio reale.

## Offerte & coupon

CPT `offerta` (collegato a un `locale` via `advtr_locale_id`) con finestra temporale, tipo coupon (codice/QR) e codice. Tabella `{prefix}advtr_coupon` per i riscatti.

- **Pubblico**: `GET advertrieste/v1/offerte[?locale={id}]` — solo offerte attive (finestra date + stato). Shortcode `[advtr_offerte]` con **countdown** live.
- **Esercente**: `POST advertrieste/v1/offerta/{id}/redeem` — solo proprietario del locale collegato o admin (nonce + auth). Valida il codice, registra il riscatto e traccia l'evento `coupon` nelle statistiche. Shortcode `[advtr_valida_coupon]` (area riservata).
- **Cron**: `advtr_expire_coupons` (giornaliero) marca scadute le offerte oltre la data di scadenza.

## Convenzioni

- Namespace `AdverTrieste\`, prefisso globale `advtr_`, costanti `ADVTR_*`, text domain `advertrieste`.
- Un componente per file. Classi PascalCase; file `class-<nome-con-trattini>.php` (WPCS).
- REST namespace `advertrieste/v1` (endpoint futuri).

## Autoloader

`AdverTrieste\Cpt\Locale` → `includes/cpt/class-locale.php`. Aggiungere una classe nel namespace giusto è sufficiente: nessuna modifica al bootstrap. Per farla registrare come CPT, aggiungerla anche all'elenco `Plugin::POST_TYPES`.

## Sviluppo

Tooling PHP (dalla cartella del plugin):

```bash
composer install       # installa PHPCS + WordPress Coding Standards
composer lint          # analizza il codice (phpcs)
composer lint:fix      # correzioni automatiche (phpcbf)
```

> In MAMP usare la PHP dello stack, es. `/Applications/MAMP/bin/php/php8.3.28/bin/php`.

## Vincolo di sicurezza da non dimenticare

Il CPT `punto_qr` è **non pubblico** (`public => false`, `show_in_rest => false`). Le coordinate non devono mai raggiungere il front-end pubblico: l'accesso avverrà solo via endpoint REST autenticato con verifica di capability. Vedi `docs/architettura.md` §3 e §7.
