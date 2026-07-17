# Specifiche funzionali — AdverTrieste

Fonte di verità sui **requisiti**: cosa deve fare ogni modulo e il suo stato. Non contiene stime di costo/tempo né dettagli implementativi (quelli stanno in `architettura.md`).

## Legenda stato
- ✅ **CONFERMATA** — realizzabile e pronta da sviluppare, nessuna dipendenza esterna aperta.
- ◐ **CONDIZIONATA** — fattibile, ma con un vincolo da rispettare/verificare (costi terzi, policy, privacy).
- ⏸ **IN SOSPESO** — non avviare finché non si risolve una condizione esterna (permesso/verifica).

---

## Area 1 — Esperienza pubblica (turista)

### 1.1 Ingresso guidato "Cosa stai cercando?" — ✅
Schermata d'ingresso con domanda guida e schede di intenzione (Mangiare, Bere, Visitare, Shopping, Servizi), in alternativa alla ricerca libera per nome/via/zona. La scelta filtra le categorie pertinenti e prepara la mappa.

### 1.2 Mappa interattiva a due livelli di zoom — ✅
Mappa Leaflet + OpenStreetMap con marker cliccabili, anteprima, ricerca per via/zona, filtri per categoria in tempo reale.
- Zoom basso → compaiono i **POI** (musei, castelli, monumenti).
- Zoom alto → compaiono le **attività commerciali**.
- Meccanismo: ogni marker ha una soglia di zoom minima; filtro lato REST + gestione client.

### 1.3 Schede attività complete — ✅
Logo, descrizione, elenco servizi, galleria foto/video, contatti, orari. Responsive.

### 1.4 Percorso dalla posizione utente — ✅
Su consenso, percorso dalla posizione attuale al locale, oppure apertura indicazioni nell'app mappe nativa.
- Apertura app nativa (Google/Apple Maps): gratuita, immediata (preferita).
- Tracciato in-mappa: usa servizio di routing (OSRM/Leaflet Routing) — verificarne i limiti. Opzionale.

### 1.5 Recensioni Google — ◐
Voto e recensioni da Google sulla scheda + pulsante "scrivi recensione" (link alla pagina Google del locale).
- Visualizzazione via **Google Places API** (SKU Pro per le review) con cache di pochi giorni.
- **Vincolo**: soglia gratuita mensile; oltre → costo a consumo. Non persistere review/foto oltre i limiti Google. Prevedere tetto di spesa/avvisi budget e interruttore per disattivare. Prezzi/soglie Google variano: riverificare.
- Il pulsante "scrivi recensione" è sempre gratuito (semplice link).

### 1.6 Contatore visite schede — ✅
Ogni scheda traccia le visualizzazioni.
- **Regola**: sotto una soglia minima mostra badge "Novità"/"Nuova apertura"; il numero reale appare solo dopo la soglia. Nessun numero gonfiato/inventato.

---

## Area 2 — Strumenti per le attività

### 2.1 Area riservata clienti — ✅
Ogni attività gestisce in autonomia la propria scheda: contenuti, foto/video, offerte/coupon, consultazione statistiche.

### 2.2 Statistiche dedicate per scheda — ✅
Dashboard: visualizzazioni, click sulla mappa, coupon riscattati, click contatti, sezioni più viste, andamento nel tempo.

### 2.3 Offerte a tempo & Coupon — ✅
Promozioni con scadenza e countdown; coupon con codice o QR validabile dall'esercente sul posto.

### 2.4 Badge "In Evidenza" — ✅
Pacchetto premium: marker dorato, priorità nei risultati, maggiore visibilità sulla mappa, con date di validità dedicate.

### 2.5 Mappa dei Punti QR — esclusiva clienti — ✅
Mappa con posizione fisica di espositori e QR code, **visibile solo in Area Riservata, mai lato pubblico**. Leva commerciale per dimostrare la capillarità della rete.
- **CRITICO (sicurezza)**: accesso protetto lato server, coordinate servite solo a utenti autenticati. Non basta nascondere il link.
- **Da confermare**: visibilità (tutti i clienti vedono l'intera rete / solo admin / ciascuno vede i propri) e caricamento posizioni (manuale admin / import CSV-Excel).

### 2.6 Pagamenti online & rinnovi automatici — ✅
WooCommerce (+ Subscriptions) attivo al lancio, per attività commerciali, badge "In Evidenza" e spazi-evento.
- **Decisione**: pagamenti **attivi fin dall'inizio** (non solo predisposti), perché la vendita spazi-evento richiede di incassare.
- Adempimenti: configurazione gateway, transazioni sicure, fatturazione da concordare col commercialista.

---

## Area 3 — Gestione & automazioni

### 3.1 Scadenze & email automatiche — ✅
Date inizio/fine per ogni scheda. Avvisi email a admin e cliente all'avvicinarsi della scadenza (soglie configurabili, es. 30/15/7 gg); sospensione automatica alla scadenza. Gestito via WP-Cron.

### 3.2 Gestione contenuti e categorie — ✅
Pannello admin per attività, POI, categorie, moderazione contenuti clienti. I clienti inseriscono in autonomia; l'admin supervisiona e modera.

---

## Area 4 — Eventi

### 4.1 Sezione "Grandi Eventi" cittadini — ✅
Pochi eventi-bandiera all'anno (Barcolana, Triskell…), curati a mano, trattamento grafico privilegiato.
- Banner in home con countdown → pagina dedicata.
- **Locali aderenti collegati all'evento** (es. bar aperti durante Barcolana), mostrati nella pagina evento ed evidenziabili sulla mappa nel periodo. Abilita pacchetto vendibile "visibilità durante i grandi eventi".
- Contenuti/immagini propri o forniti dagli organizzatori: nessun problema di fonti terze.

### 4.2 Vendita spazi per eventi terzi — ✅
Organizzatori (grandi/piccoli) acquistano visibilità temporanea, con scadenza e rinnovo automatico.
- Nuovo tipo di cliente "organizzatore evento", riusa logica abbonamento/scadenza/avvisi.
- Richiede pagamenti attivi (vedi 2.6).

### 4.3 Autogestione con workflow di approvazione — ✅
L'organizzatore inserisce/modifica/rimuove il proprio evento in autonomia, ma **il contenuto non va online da solo**.
- Stati: `bozza → in_revisione → pubblicato`. Pubblicazione solo dopo OK admin.
- **Anche le modifiche a un evento già online passano in revisione**: il pubblico vede l'ultima versione approvata finché la modifica non è validata → serve versione "pubblica" + "in lavorazione".
- Riguarda contenuti propri dell'organizzatore: nessuna criticità di permessi (indipendente da 4.4).

### 4.4 Modulo eventi generale (import da fonte esterna) — ⏸
Import semi-automatico di eventi culturali da turismofvg.it, con AI che struttura i dati e approvazione umana finale.
- Tecnicamente la fonte è ottima (dati strutturati, ID univoci).
- **Blocco**: il portale non pubblica licenza di riuso libero; le immagini hanno autori protetti. Niente prelievo automatico dato per lecito.
- **Azione in corso**: richiesta permesso/feed a PromoTurismoFVG.
  - Se concesso → flusso semi-automatico (solo dati fattuali, descrizioni riscritte, no foto altrui, approvazione finale).
  - Se negato → inserimento manuale assistito da AI, senza prelievo automatico.
- **NON implementare scraping** finché non risolto.

---

## Note trasversali
- **Dipendenze esterne**: Places API (costo a consumo oltre soglia → tetto di spesa); eventi turismofvg.it (permesso); routing percorsi (limiti servizio).
- **Privacy/sicurezza**: mappa QR protetta lato server; GDPR per account clienti e geolocalizzazione (su consenso); nessun contatore gonfiato.
- **Natura del documento**: descrive requisiti e stato, non è preventivo né contratto. Gli stati riflettono le decisioni maturate finora e possono aggiornarsi.
