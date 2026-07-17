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
│   ├── class-plugin.php      # singleton di avvio: registra CPT, tassonomie, meta
│   ├── cpt/                  # Custom Post Type e tassonomie (uno per file)
│   │   ├── class-locale.php     # attività commerciale (pubblico)
│   │   ├── class-poi.php        # punto d'interesse (pubblico)
│   │   ├── class-evento.php     # evento (pubblico se pubblicato)
│   │   ├── class-puntoqr.php    # espositore/QR — RISERVATO, non pubblico
│   │   └── class-categoria.php  # tassonomia `categoria` (locale+poi) + seeding
│   └── meta/                 # meta box e campi
│       └── class-localemeta.php # meta del CPT `locale` (register + box + save)
├── assets/src/               # sorgenti JS/CSS (mappa, dashboard, onboarding)
│   └── admin/locale-meta.js  # media picker (logo + galleria) del meta box
├── templates/                # template front-end del plugin
│   └── admin/locale-meta-box.php # markup del meta box "Dati locale"
├── docs/                     # specifiche, architettura, deploy
├── composer.json             # dev tooling (PHPCS + WPCS)
└── phpcs.xml                 # regole WordPress Coding Standards
```

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
