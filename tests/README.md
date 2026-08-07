# Test — AdverTrieste

Due gruppi di suite, entrambe eseguite con WP-CLI su un WordPress vero con il
plugin **attivo**. Non c'è PHPUnit: vedi la nota in fondo.

| Gruppo | File | Cosa copre |
|---|---|---|
| Integrazione | `tests/integration/run.php` | I percorsi critici di `docs/architettura.md` §8 |
| Console e area clienti | `tests/console/*.php` | Quello che vedono e fanno amministratore e cliente |

Ogni suite crea i propri dati e li rimuove. Tutto ciò che creano si chiama
`PROVA …` o ha un'email `@example.test`: se una suite muore a metà, la
successiva ripulisce al posto suo (`advtr_test_ripulisci()`).

---

## Come si lanciano

Con WP-CLI nel PATH, dalla cartella del plugin:

```bash
composer test:integration     # percorsi critici
composer test:console         # console e area clienti
composer test                 # entrambe
```

In MAMP, dove WP-CLI non è nel PATH, servono la PHP dello stack e il phar:

```bash
WP="/Applications/MAMP/bin/php/php8.3.28/bin/php /percorso/wp-cli.phar" \
WP_PATH=/Applications/MAMP/htdocs \
  tests/console/run.sh
```

Le suite escono con codice **1** se almeno un'asserzione fallisce, così il
lanciatore (e un domani la CI) se ne accorge.

---

## Suite di integrazione

`tests/integration/run.php` — access control della mappa QR, esclusione dei
punti QR dai marker pubblici, workflow di revisione eventi a doppia versione,
scadenze e sospensione schede, coupon, soglia del contatore visite, mappatura
delle capability.

## Suite di console e area clienti

`tests/console/run.sh` le lancia tutte, **una per processo**.

Il processo separato non è pignoleria. WordPress tiene traccia degli script e
dei fogli di stile già stampati: rendere due sezioni nella stessa richiesta fa
risultare "mancanti" asset che erano soltanto già stati emessi. Ci siamo
cascati, e i test dicevano il falso.

| Suite | Cosa verifica |
|---|---|
| `guscio-cliente.php` | Sezioni dell'area clienti, dashboard, menu |
| `guscio-admin.php` | Sezioni della console amministratore, indicatori, elenchi |
| `area-clienti.php` | L'area clienti vista da un cliente reale |
| `creazione.php` | Creazione di contenuti e account dalla console |
| `dettaglio-locale.php` | La scheda di un locale: campi commerciali inclusi |
| `dettagli.php` | Dettaglio di POI, offerte, eventi, punti QR, clienti |
| `eliminazione.php` | Cestino, ripristino, eliminazione definitiva |
| `abbonamenti.php` | Attivazione e rinnovo, durate ammesse |
| `password.php` | Nome utente e gestione password dei clienti |
| `comandi-visibili.php` | I comandi non solo ci sono: si vedono |
| `azioni-incrociate.php` | I due gestori di azioni non si pestano i piedi |
| `geocoding.php` | Nominatim: chiamata reale, cache, limiti, permessi |
| `geocoding-cliente.php` | La ricerca per indirizzo dentro l'area clienti |

### Due regole imparate sul campo

**Le scritture si provano via HTTP, non chiamando i metodi.** Un bug che ha
attraversato diverse revisioni — «Il link che hai seguito è scaduto» su ogni
azione della console — era invisibile ai test che saltavano gli hook, perché
nasceva da due gestori agganciati allo stesso `template_redirect`.
`advtr_test_posta()` manda una vera richiesta con cookie di sessione e legge il
codice di avviso dal redirect.

**Verificare la presenza nel markup non basta.** Il pulsante «Salva modifiche»
dell'area clienti è stato invisibile per giorni: c'era, era cliccabile, ma le
variabili di colore non raggiungevano più il suo contenitore e usciva bianco su
bianco. `comandi-visibili.php` controlla anche che le variabili usate siano
dichiarate per il contenitore che la pagina adopera davvero.

### Strumento di servizio

`tests/console/rendi.php` rende una sezione e dice se è uscita pulita, utile
dopo aver toccato un template:

```bash
wp eval-file tests/console/rendi.php admin locali
wp eval-file tests/console/rendi.php admin locali cestino
wp eval-file tests/console/rendi.php admin clienti 26
wp eval-file tests/console/rendi.php cliente statistiche
wp eval-file tests/console/rendi.php admin locali dump   # stampa l'HTML
```

Le opzioni sono parole semplici: WP-CLI si prenderebbe quelle con `--`.

---

## Prerequisiti

Le suite di console si appoggiano ai dati demo: utenti `demo_cliente`,
`demo_cliente2`, `demo_cliente3` e le pagine con gli shortcode. Se mancano,
lanciare prima:

```bash
wp eval-file tools/seed-demo.php
```

Le suite creano sessioni firmando il token direttamente, senza password: serve
a provare i percorsi autenticati, non a simulare un accesso umano.

---

## Nota su PHPUnit

Una suite PHPUnit richiederebbe lo scaffolding `wp-phpunit` con un database di
test dedicato, non predisposto in questo ambiente MAMP. Quelle qui sopra
coprono gli stessi percorsi in modo eseguibile subito. Migrare a PHPUnit resta
un passo sensato quando ci sarà una CI.
