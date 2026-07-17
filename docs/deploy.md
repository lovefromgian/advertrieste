# Deploy & ambienti — AdverTrieste

> **[DA COMPILARE]** — Scheletro. Riempire con le decisioni reali su hosting, ambienti e rilascio.

## Ambienti
- **Locale**: [DA DEFINIRE — wp-env / Local / DDEV / Docker]
- **Staging**: [DA DEFINIRE — URL, accesso]
- **Produzione**: [DA DEFINIRE — hosting, PHP, WordPress version]

## Requisiti server
- PHP: [8.x]
- WordPress: [versione]
- WooCommerce (+ Subscriptions): [versioni]
- Estensioni PHP necessarie: [es. gd/imagick per immagini, curl]
- Spazio/upload: dimensionare per galleria foto/video dei clienti [stima]

## Variabili/segreti (mai nel repo)
- `ADVTR_GOOGLE_PLACES_KEY` — chiave Google Places API
- Credenziali gateway pagamento WooCommerce
- [altri secret]

## Procedura di rilascio
1. [DA DEFINIRE — es. build asset, git pull, attivazione plugin, migrazioni]
2. Verifica post-deploy: [checklist minima — mappa carica, area riservata protetta, cron attivi]

## Backup
- [DA DEFINIRE — frequenza, cosa includere (DB + uploads), retention]
