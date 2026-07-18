# Test — AdverTrieste

## Suite di integrazione (percorsi critici)

`tests/integration/run.php` verifica con assert reali i percorsi critici indicati
in `docs/architettura.md` §8: access control mappa QR, esclusione dei punti QR dai
marker pubblici, workflow di revisione eventi (doppia versione), scadenze e
sospensione schede, coupon, soglia visite, e la mappatura delle capability
(editing self-service).

Richiede WordPress con il plugin **attivo** ed esegue tutto via WP-CLI. Crea e
poi rimuove i propri dati di test.

### Come eseguire

Con WP-CLI disponibile:

```bash
wp eval-file wp-content/plugins/advertrieste/tests/integration/run.php
```

In MAMP (WP-CLI non nel PATH), usare la PHP dello stack e il phar di wp-cli:

```bash
/Applications/MAMP/bin/php/php8.3.28/bin/php wp-cli.phar \
  --path=/Applications/MAMP/htdocs \
  eval-file wp-content/plugins/advertrieste/tests/integration/run.php
```

Esce con codice **0** se tutti gli assert passano, **1** se almeno uno fallisce
(utile in CI).

## Nota su PHPUnit

Una suite PHPUnit "integration" richiede lo scaffolding `wp-phpunit` con un DB di
test dedicato, non predisposto in questo ambiente MAMP. La suite qui sopra copre
gli stessi percorsi critici in modo runnable. Migrare a PHPUnit è un passo
successivo consigliato per la CI.
