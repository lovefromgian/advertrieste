# Deploy & ambienti — AdverTrieste

## Ambienti

- **Locale**: MAMP (Apache + MySQL). WordPress in `/Applications/MAMP/htdocs`, plugin in `wp-content/plugins/advertrieste/`. Sito su `http://localhost`.
- **Staging**: [DA DEFINIRE — URL, accesso]
- **Produzione**: [DA DEFINIRE — hosting, dominio]

## Requisiti server

- **PHP** ≥ 8.3 (target del plugin). In MAMP: `/Applications/MAMP/bin/php/php8.3.28/bin/php`.
- **WordPress** ≥ 6.0.
- **Estensioni PHP**: `curl` (recensioni Google), `gd`/`imagick` (immagini/galleria).
- **WooCommerce (+ Subscriptions)**: necessari solo per i pagamenti (§2.6). Il bridge è inerte se assenti.
- Spazio upload dimensionato per logo/galleria dei clienti.

## Variabili/segreti (mai nel repo)

Da definire in `wp-config.php` (o variabili d'ambiente):

- `ADVTR_GOOGLE_PLACES_KEY` — chiave Google Places API per le recensioni (§1.5). Senza, la funzione resta inerte. Impostare il **tetto di spesa** su Google Cloud.
- Credenziali del gateway di pagamento WooCommerce.

Interruttore recensioni: opzione `advtr_reviews_disabled` (`wp option update advtr_reviews_disabled 1` per spegnere).

## Attivazione & schema

All'attivazione il plugin (vedi `includes/class-activator.php`):

- registra CPT e tassonomie e rigenera i **rewrite** (`flush_rewrite_rules`);
- semina i termini di `categoria`;
- installa **ruoli e capability** (`cliente_locale`, `organizzatore_evento`, e le capability dei CPT su admin);
- crea le tabelle **`{prefix}advtr_stats`** e **`{prefix}advtr_coupon`** (dbDelta);
- pianifica i cron **`advtr_check_scadenze`** e **`advtr_expire_coupons`** (giornalieri).

Attivazione via WP-CLI:

```bash
wp plugin activate advertrieste
```

> **Importante:** dopo un aggiornamento che cambia capability o rewrite, **ri-attivare** il plugin (o richiamare l'attivazione) per riallineare capability e rewrite.

## WP-Cron in produzione

WP-Cron si attiva col traffico. Su siti a basso traffico, disabilitare WP-Cron pseudo-automatico e usare un cron di sistema reale:

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
*/15 * * * * curl -s https://IL-TUO-DOMINIO/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

## Procedura di rilascio

1. Merge su `main` (branch consolidato).
2. In produzione: `git pull` nella cartella del plugin (o deploy dell'artefatto).
3. `composer install --no-dev` (se si usano le dipendenze runtime; attualmente le dipendenze sono solo dev — PHPCS).
4. `wp plugin activate advertrieste` (o riattivazione se già attivo) per allineare schema/capability/rewrite.
5. Verifica post-deploy (vedi sotto).

## Verifica post-deploy (checklist minima)

- La pagina della mappa carica i marker (`[advtr_map]`).
- L'area riservata è protetta: `GET /wp-json/advertrieste/v1/qr-map` risponde **401** senza login.
- I cron risultano pianificati: `wp cron event list | grep advtr`.
- (Facoltativo) La suite di integrazione passa: `wp eval-file wp-content/plugins/advertrieste/tests/integration/run.php`.

## Backup

- **DB**: includere le tabelle core WP + `{prefix}advtr_stats` e `{prefix}advtr_coupon`.
- **Uploads**: logo/galleria dei clienti.
- Frequenza/retention: [DA DEFINIRE secondo hosting].
