# Architettura tecnica — AdverTrieste

Documento di dettaglio implementativo. Accompagna `CLAUDE.md` (convenzioni) e `specifiche-funzionali.md` (requisiti).
Le voci marcate **[DA DEFINIRE]** vanno completate con decisioni reali prima o durante lo sviluppo.

## 1. Panoramica architetturale
Plugin WordPress custom (`advertrieste`) che registra Custom Post Type, meta, endpoint REST, job cron e logica di accesso. Front-end pubblico via template del plugin + Leaflet. Area riservata clienti su WordPress (ruoli custom). Pagamenti via WooCommerce.

```
[Browser pubblico] --REST--> [WP + plugin] <--admin/area riservata-- [Clienti/Admin]
        |                          |
     Leaflet                   WooCommerce (pagamenti/abbonamenti)
        |                          |
   Places API (cache)          WP-Cron (scadenze, email, refresh review)
```

## 2. Modello dati

### 2.1 Custom Post Type
| CPT | Scopo | Pubblico |
|-----|-------|----------|
| `locale` | Attività commerciale | Sì (a zoom alto) |
| `poi` | Punto d'interesse (museo, castello) | Sì (a zoom basso) |
| `evento` | Grande evento **o** evento organizzatore terzo | Sì (se `pubblicato`) |
| `punto_qr` | Espositore/QR fisico | **NO — solo area riservata** |

### 2.2 Tassonomie
- `categoria` (condivisa): termini per intenzione — `mangiare`, `bere`, `visitare`, `shopping`, `servizi`. **[confermare set definitivo]**
- Eventuale `tipo-poi` per sottotipi di POI. **[DA DEFINIRE]**

### 2.3 Meta principali (per CPT)
**`locale`**: `logo_id`, `descrizione`, `servizi[]`, `galleria_ids[]`, `lat`, `lng`, `zoom_min`, `data_inizio`, `data_fine`, `in_evidenza` (bool), `evidenza_inizio`, `evidenza_fine`, `evidenza_priorita`, `place_id` (Google), `visite_reali` (int), `visite_soglia_raggiunta` (bool).
**`poi`**: `descrizione`, `galleria_ids[]`, `lat`, `lng`, `zoom_min`, `tipo`.
**`evento`**: `tipo_evento` (`grande` | `organizzatore`), `stato_workflow` (`bozza`|`in_revisione`|`pubblicato`), `data_inizio`, `data_fine`, `organizzatore_user_id`, `locali_collegati_ids[]`, `versione_pubblica` (snapshot approvato), `versione_lavorazione` (bozza in revisione), `abbonamento_id` (se a pagamento).
**`punto_qr`**: `lat`, `lng`, `etichetta`, `owner_user_id` (se pertinente), `stato`. **Coordinate mai esposte al pubblico.**

### 2.4 Tabelle custom
- `{prefix}advtr_stats` — tracking eventi: `id`, `post_id`, `tipo` (`view`|`map_click`|`coupon`|`contact`), `timestamp`, `meta`. Indicizzata su (`post_id`,`tipo`,`timestamp`).
- `{prefix}advtr_coupon` — coupon emessi/riscattati: `id`, `offerta_id`, `codice`, `stato` (`attivo`|`riscattato`|`scaduto`), `emesso_il`, `riscattato_il`. **[confermare se serve tabella o meta]**

### 2.5 Ruoli & capability
- Ruolo `cliente_locale` (gestisce le proprie schede `locale`).
- Ruolo `organizzatore_evento` (gestisce i propri `evento`, sottoposti a revisione).
- Capability custom: `advtr_edit_own_locale`, `advtr_submit_evento`, `advtr_view_qr_map`, `advtr_approve_evento`. **[rifinire mappa capability]**

## 3. API REST (namespace `advertrieste/v1`)
> Ogni endpoint su dati privati richiede `permission_callback` reale. Mai `__return_true` su dati sensibili.

| Metodo | Endpoint | Auth | Scopo |
|--------|----------|------|-------|
| GET | `/map/markers` | pubblica | Marker per bbox + zoom + categoria (filtra POI/commerciali per `zoom_min`). **Non include `punto_qr`.** |
| GET | `/locale/{id}` | pubblica | Dettaglio scheda (no dati privati). |
| GET | `/locale/{id}/reviews` | pubblica | Recensioni Google (da cache transient). |
| POST | `/locale/{id}/track` | pubblica | Registra view/click/coupon/contact (rate-limit + nonce). |
| GET | `/eventi` | pubblica | Eventi `pubblicato` (versione pubblica). |
| GET | `/grandi-eventi` | pubblica | Grandi eventi + locali collegati. |
| GET | `/qr-map` | **auth (cliente/admin)** | Punti QR. **YOU MUST** verificare capability `advtr_view_qr_map`. |
| POST | `/evento/{id}/submit` | auth (organizzatore) | Passa a `in_revisione`. |
| POST | `/evento/{id}/approve` | auth (admin) | Promuove `versione_lavorazione` → pubblica. |
| GET | `/stats/{post_id}` | auth (owner/admin) | Dati dashboard della scheda. |

## 4. Front-end
- **Mappa** (`assets/src/map/`): Leaflet; carica marker via `/map/markers` su `moveend`/`zoomend`; icone distinte per categoria + marker dorato "in evidenza"; cluster opzionale.
- **Onboarding** (`assets/src/onboarding/`): schermata "Cosa stai cercando?" → imposta filtro categoria e centra la mappa.
- **Dashboard cliente** (`assets/src/dashboard/`): grafici da `/stats/{id}`. Libreria grafici **[DA DEFINIRE: Chart.js consigliato]**.
- **Mappa QR** (`assets/src/qr-map/`): montata solo in area riservata; dati da `/qr-map` (autenticato).

## 5. Job cron (WP-Cron)
- `advtr_check_scadenze` (giornaliero): calcola scadenze, invia email di avviso (30/15/7 gg **[confermare]**), sospende schede scadute, spegne `in_evidenza` scaduti.
- `advtr_refresh_reviews` (ogni N giorni per scheda): aggiorna cache recensioni entro i limiti Places API; salta se feature disattivata o soglia superata.
- `advtr_expire_coupons` (giornaliero): marca coupon/offerte scaduti.

## 6. Integrazioni esterne
- **Google Places API**: chiave in `wp-config.php`/env (mai in repo). Cache via transient (durata **[es. 3 giorni]**). Tetto di spesa + avvisi budget lato Google Cloud. Interruttore feature in opzioni plugin.
- **WooCommerce (+ Subscriptions)**: prodotti = tipi di abbonamento (locale, in-evidenza, spazio-evento). Hook su rinnovo/scadenza abbonamento → aggiorna stato scheda/evento. **[definire mappatura prodotti]**
- **Routing** (opzionale): OSRM pubblico o self-host per tracciato in-mappa; altrimenti link ad app nativa.

## 7. Sicurezza (checklist)
- [ ] `/qr-map` e dati `punto_qr` inaccessibili senza auth+capability (test automatico dedicato).
- [ ] Ogni scrittura protetta da nonce; ogni input sanitizzato; ogni output escapato.
- [ ] Query custom con `$wpdb->prepare`.
- [ ] Chiavi API fuori dal repo.
- [ ] Consenso GDPR per geolocalizzazione e per chiamate a servizi terzi.
- [ ] Workflow eventi: impossibile pubblicare bypassando `in_revisione`.

## 8. Test (priorità)
Coprire con test i punti a rischio: access control mappa QR, workflow revisione eventi (incl. modifica di evento già pubblicato), calcolo scadenze/sospensione, emissione/validazione coupon, soglia contatore visite.

## 9. Aperti / da decidere
- [ ] Ambiente locale e comandi (→ aggiornare `CLAUDE.md`).
- [ ] Tema di base e integrazione template.
- [ ] Mappa QR: visibilità (tutti/solo propri/admin) e import posizioni.
- [ ] Esito permesso PromoTurismoFVG (sblocca o meno 4.4).
- [ ] Libreria grafici dashboard; durata cache review; soglie email scadenza.
