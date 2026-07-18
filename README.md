# AdverTrieste

Plugin WordPress custom: directory su mappa di attività e luoghi del territorio di Trieste — schede clienti, statistiche, offerte/coupon, eventi e area riservata.

> Fonte di verità: `CLAUDE.md` (convenzioni), `docs/specifiche-funzionali.md` (requisiti e stato), `docs/architettura.md` (dati, REST, cron, sicurezza).
>
> **Manuale d'uso** (pagine, profili, accessi, shortcode): `docs/manuale-utilizzo.md`.

## Stato

**In sviluppo attivo.** Implementate e verificate: CPT (locale, poi, evento, punto_qr, offerta) + tassonomia categorie · mappa pubblica a due livelli di zoom · scheda attività completa (single locale) con tracking visite · area riservata + mappa QR protetta · statistiche + tracking · offerte & coupon con validazione esercente · eventi con workflow di revisione a doppia versione · scadenze & email automatiche (cron) · ruoli/capability custom.

Da fare: onboarding "Cosa stai cercando?", percorso/routing, recensioni Google (◐), editing self-service front-end, coordinate POI, pagamenti WooCommerce, suite di test PHPUnit. Vedi `docs/specifiche-funzionali.md` per lo stato per-modulo.

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
│   ├── evento/               # workflow di revisione eventi
│   │   └── class-workflow.php   # bozza→in_revisione→pubblicato, snapshot versione pubblica
│   ├── coupon/               # coupon/riscatti
│   │   └── class-coupon.php     # tabella advtr_coupon, riscatti, scadenza offerte
│   ├── scadenze/             # scadenze schede
│   │   └── class-scadenze.php   # avvisi 30/15/7gg, sospensione, spegni evidenza
│   ├── email/                # invio email
│   │   └── class-mailer.php     # wrapper wp_mail (HTML)
│   ├── cron/                 # job pianificati (WP-Cron)
│   │   └── class-cron.php       # advtr_expire_coupons + advtr_check_scadenze (giornalieri)
│   ├── access/               # ruoli, capability e controlli di accesso
│   │   ├── class-roles.php      # ruoli cliente_locale/organizzatore_evento + capability
│   │   └── class-access.php     # helper: can_view_qr_map(), is_cliente()
│   ├── meta/                 # meta box e campi
│   │   ├── class-localemeta.php # meta del CPT `locale` (register + box + save)
│   │   ├── class-poimeta.php    # meta del CPT `poi` (coordinate, zoom, tipo)
│   │   └── class-puntoqrmeta.php # meta del CPT `punto_qr` (coordinate + stato)
│   ├── stats/                # statistiche per scheda
│   │   └── class-stats.php      # tabella advtr_stats, record eventi, query, soglia
│   ├── rest/                 # endpoint REST (namespace advertrieste/v1)
│   │   ├── class-markers.php    # GET /map/markers (bbox+zoom+categoria, mai punto_qr)
│   │   ├── class-qrmap.php      # GET /qr-map — RISERVATO (auth + advtr_view_qr_map)
│   │   ├── class-track.php      # POST /locale/{id}/track (nonce + rate-limit)
│   │   ├── class-stats.php      # GET /stats/{id} — owner/admin
│   │   ├── class-offerte.php    # GET /offerte + POST /offerta/{id}/redeem
│   │   └── class-eventi.php     # GET /eventi + /grandi-eventi + submit/approve
│   └── frontend/             # front-end pubblico e riservato
│       ├── class-map.php            # shortcode [advtr_map] + enqueue Leaflet
│       ├── class-reservedarea.php   # shortcode [advtr_area_riservata] + mappa QR
│       ├── class-statsdashboard.php # shortcode [advtr_statistiche] (tiles + grafico)
│       ├── class-offerte.php        # shortcode [advtr_offerte] + [advtr_valida_coupon]
│       ├── class-eventi.php         # shortcode [advtr_grandi_eventi] + [advtr_eventi]
│       └── class-scheda.php         # scheda attività (single locale) + tracking visita
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

## Scheda attività (§1.3)

Ogni `locale` pubblicato ha una **pagina singola** ricca (`Frontend\Scheda` sostituisce il template single del tema con `templates/single-locale.php`): logo, descrizione, servizi, galleria, contatti (telefono/email/sito/indirizzo), orari, mini-mappa Leaflet e pulsante "Scrivi recensione" (se è impostato `place_id`).

- Al caricamento registra una **visita** (`view`) via `POST /locale/{id}/track` — chiude il tracking del contatore visite (§1.6). Traccia anche i click sui contatti (`contact`).
- I campi contatti/orari sono nuovi meta del `locale` (`advtr_telefono`, `advtr_email`, `advtr_sito`, `advtr_indirizzo`, `advtr_orari`), gestiti dal meta box "Dati locale".

## Eventi & workflow di revisione (§4)

CPT `evento` **non pubblico** e **fuori dalla REST core**: il post WP è la versione *in lavorazione*; il pubblico vede solo lo snapshot approvato `advtr_versione_pubblica` (servito dagli endpoint/shortcode). Questo garantisce che le modifiche non ancora approvate non finiscano online.

- **Workflow** (`Evento\Workflow`): `bozza → in_revisione → pubblicato`. `submit` passa in revisione; `approve` copia lo stato attuale nella versione pubblica. Salvare un evento già pubblicato lo riporta in bozza (le modifiche vanno ri-approvate).
- **REST**: `GET /eventi` e `GET /grandi-eventi` (pubblici, solo versione approvata; i grandi eventi includono i locali collegati risolti); `POST /evento/{id}/submit` (autore o admin); `POST /evento/{id}/approve` (capability `advtr_approve_evento`).
- **Bacheca**: meta box "Dati evento" (tipo, date, locali collegati) + "Workflow revisione" con pulsanti Invia/Approva.
- **Front-end**: `[advtr_grandi_eventi]` (banner + countdown + locali aderenti) e `[advtr_eventi]` (elenco).

> Grandi eventi: creati e approvati dall'admin. Eventi di organizzatori terzi: workflow di revisione obbligatorio. ⏸ Import da turismofvg.it resta IN SOSPESO.

## Scadenze & email (§3.1)

Cron `advtr_check_scadenze` (giornaliero, `Scadenze\Scadenze`): per ogni `locale` con `advtr_data_fine`:
- **Avvisi email** a admin + cliente alle soglie **30/15/7 gg** (filtrabili con `advtr_scadenza_soglie`), una sola volta per soglia (marca la soglia notificata e quelle superiori).
- **Sospensione automatica** alla scadenza: la scheda passa in bozza (`draft`, sparisce dalla mappa) + email di notifica.
- **Spegnimento "in evidenza"** scaduto.

Email via `Email\Mailer` (wrapper HTML su `wp_mail`). Lo scheduling è idempotente e auto-riparante (`Cron\Cron`).

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
