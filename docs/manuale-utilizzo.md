# Manuale d'uso — AdverTrieste

Guida operativa alle funzionalità realizzate: mappa, area riservata, statistiche, offerte & coupon, eventi con workflow di revisione, scadenze automatiche. Come costruire le pagine con gli shortcode, chi vede cosa, e come far entrare i vari profili.

> Versione navigabile (light/dark): pubblicata come Artifact — vedi il link condiviso in chat. Questo file è la copia versionata nel repo.

---

## 1. I profili e cosa possono fare

Quattro livelli di accesso:

- **Pubblico** — chiunque, senza login. Vede mappa, schede, offerte attive, eventi pubblicati.
- **Cliente** (`cliente_locale`) — l'attività commerciale. Vede l'area riservata, le proprie statistiche, valida i coupon.
- **Organizzatore** (`organizzatore_evento`) — chi propone eventi. Invia i propri eventi in revisione.
- **Admin** (`administrator`) — controllo totale: crea contenuti, approva eventi, gestisce tutto.

### Chi ha quale permesso (capability)

| Capability | A cosa serve | Pubblico | Cliente | Organizz. | Admin |
|---|---|:--:|:--:|:--:|:--:|
| `advtr_view_qr_map` | Vedere la mappa riservata dei punti QR | — | ✓ | — | ✓ |
| `advtr_edit_own_locale` | Gestire la propria scheda locale in bacheca | — | ✓ | — | ✓ |
| `advtr_submit_evento` | Inviare un evento in revisione | — | — | ✓ | ✓ |
| `advtr_approve_evento` | Approvare e pubblicare un evento | — | — | — | ✓ |

> **Concetto chiave — il "proprietario" di un contenuto è il suo Autore.** Le statistiche di un locale e la validazione dei coupon di un'offerta sono accessibili a chi risulta **Autore** di quel post (oltre che all'admin). Quando crei una scheda per un cliente, impostalo come Autore nel box "Autore" della pagina di modifica.

---

## 2. Creare le pagine con gli shortcode

Ogni funzionalità front-end si attiva inserendo uno **shortcode** nel contenuto di una Pagina WordPress. Non serve toccare il tema.

1. **Bacheca → Pagine → Aggiungi nuova.** Dai un titolo (es. "Mappa", "Area clienti", "Eventi").
2. **Inserisci lo shortcode nel corpo.** Con l'editor a blocchi aggiungi un blocco **Shortcode** e incolla il codice tra parentesi quadre. Con l'editor classico basta scriverlo nel testo.
3. **Pubblica** la pagina. Aggiungila al menu del sito se serve (Aspetto → Menu).

### Riferimento shortcode

| Shortcode | Cosa fa | Parametri | Chi lo usa |
|---|---|---|---|
| `[advtr_map]` | Mappa pubblica con marker, filtri categoria, ricerca | `lat`, `lng`, `zoom`, `height` | Pubblico |
| `[advtr_onboarding]` | Ingresso guidato "Cosa stai cercando?" con schede d'intenzione | `map` (URL mappa), `titolo` | Pubblico |
| `[advtr_area_riservata]` | Area clienti: login gate + mappa riservata dei punti QR | — | Cliente, Admin |
| `[advtr_statistiche]` | Dashboard statistiche di una scheda (contatori + grafico) | `id` (opzionale) | Proprietario, Admin |
| `[advtr_offerte]` | Elenco offerte attive con countdown e codice | `locale` (opzionale) | Pubblico |
| `[advtr_valida_coupon]` | Form esercente per validare un coupon sul posto | — | Esercente, Admin |
| `[advtr_grandi_eventi]` | Banner grandi eventi con countdown e locali aderenti | — | Pubblico |
| `[advtr_eventi]` | Elenco degli eventi approvati con stato e countdown | — | Pubblico |

**Esempio con parametri** — mappa centrata su Trieste, zoom 13, alta 600px:

```
[advtr_map lat="45.6495" lng="13.7768" zoom="13" height="600"]
```

---

## 3. Gestire i contenuti in bacheca

Il plugin aggiunge nuove voci nel menu di WordPress.

| Voce di menu | Cos'è | Visibilità pubblica | Campi principali |
|---|---|---|---|
| **Locali** | Attività commerciale sulla mappa | Sì — a zoom alto | coordinate, zoom min, validità, in evidenza, servizi, logo, galleria, categoria |
| **Punti d'interesse** | Museo, castello, monumento | Sì — a zoom basso | coordinate, zoom min, tipo, descrizione, categoria |
| **Offerte** | Promozione a tempo con coupon | Sì — se attiva | locale collegato, date, tipo coupon, codice |
| **Eventi** | Grande evento o evento di terzi | Solo versione approvata | tipo, date, locali collegati, workflow |
| **Punti QR** | Espositore/QR fisico | **Riservato (mai pubblico)** | coordinate, stato |
| **Categorie** | Tassonomia condivisa | — | mangiare · bere · visitare · shopping · servizi (già create) |

**Creare un locale — flusso tipico:** Locali → Aggiungi nuovo → titolo e descrizione (editor) → nel box **"Dati locale"** imposta latitudine/longitudine, zoom minimo, validità, logo e galleria → assegna una o più **Categorie** → nel box **"Autore"** scegli l'utente cliente proprietario → **Pubblica**. Comparirà subito sulla mappa.

> **Sicurezza — Punti QR.** I Punti QR non sono mai visibili pubblicamente: le coordinate escono solo verso utenti autenticati con il permesso `advtr_view_qr_map`, tramite l'area riservata. Non inserirli in pagine pubbliche.

---

## 4. Dare accesso ai profili

I ruoli custom vengono creati automaticamente all'attivazione del plugin.

### Creare un utente cliente o organizzatore

1. **Bacheca → Utenti → Aggiungi nuovo.** Inserisci email e username.
2. **Ruolo:** scegli **"Cliente (locale)"** oppure **"Organizzatore evento"** dal menu a tendina.
3. **Collega i contenuti impostandolo come Autore.** Apri la sua scheda locale (o la sua offerta/evento) e nel box "Autore" seleziona questo utente. È ciò che gli dà accesso alle proprie statistiche e alla validazione dei propri coupon.
4. **L'utente accede da `/wp-login.php`.** Le pagine con `[advtr_area_riservata]` reindirizzano automaticamente al login chi non è autenticato, e riportano alla pagina dopo l'accesso.

### Chi entra come, e cosa vede

| Come entra / cosa vede | Pubblico | Cliente | Organizz. | Admin |
|---|:--:|:--:|:--:|:--:|
| Accesso | senza login | wp-login | wp-login | wp-login |
| Bacheca WordPress | — | solo le proprie schede | solo i propri eventi | completa |
| Mappa & schede pubbliche | ✓ | ✓ | ✓ | ✓ |
| Area riservata / mappa QR | — | ✓ | — | ✓ |
| Statistiche (proprie schede) | — | ✓ | — | tutte |
| Validare coupon (proprie offerte) | — | ✓ | — | tutte |
| Inviare eventi in revisione | — | — | ✓ | ✓ |
| Approvare / pubblicare eventi | — | — | — | ✓ |

> **Self-service.** Cliente e organizzatore gestiscono i **propri** contenuti direttamente dalla bacheca: un cliente vede e modifica solo le proprie schede locale; un organizzatore crea e modifica solo i propri eventi (e li invia in revisione). L'admin mantiene l'accesso completo e approva gli eventi. È il ruolo (impostato in Utenti) + l'Autore del contenuto a determinare cosa ciascuno può gestire.

---

## 5. Mappa pubblica

Mappa Leaflet + OpenStreetMap con due livelli di zoom: da lontano compaiono i **punti d'interesse**, avvicinandosi compaiono le **attività commerciali**.

- **Pagina:** inserisci `[advtr_map]`.
- **Marker:** blu per i locali, verde per i POI, dorato per i locali "in evidenza". Le schede nuove mostrano un badge "Novità" finché non superano la soglia di visite.
- **Filtri:** barra categorie sopra la mappa.
- **Visibilità:** determinata dal campo **Zoom minimo** della scheda. I Punti QR non compaiono mai.
- **Dati serviti da** `GET advertrieste/v1/map/markers` (pubblico, filtra per area visibile, zoom e categoria).

---

## 6. Scheda attività (pagina del locale)

Ogni **locale pubblicato** ha una pagina dedicata all'indirizzo `/locale/{slug}/`. Non serve alcuno shortcode: viene generata automaticamente. Ci si arriva dal link **"Apri scheda"** nel popup della mappa, oppure con l'URL diretto.

**Cosa mostra:**

- **Logo, titolo, categorie** e i badge "In evidenza" / "Novità".
- **Descrizione** (l'editor della scheda), **servizi** ed eventuale **galleria** foto.
- **Contatti** (indirizzo, telefono, email, sito) e **orari**.
- **Mini-mappa** della posizione con il pulsante **"Ottieni indicazioni"** (apre l'app mappe con il percorso).
- **Recensioni Google** con voto medio — solo se attive (chiave `ADVTR_GOOGLE_PLACES_KEY` in `wp-config.php` e `Place ID` compilato) — più il pulsante "Scrivi una recensione".

**Automatismi:**

- Registra una **visita** a ogni apertura: alimenta il contatore visite e fa uscire la scheda dalla fase "Novità" oltre la soglia.
- Traccia i **click sui contatti** (telefono/email/sito), che finiscono nelle statistiche.

**Per una scheda ricca:** compila i campi nel box **"Dati locale"** (logo, galleria, servizi, contatti, orari) e — per le recensioni — il **Google Place ID**. Più campi compili, più completa sarà la pagina pubblica.

---

## 7. Area riservata & mappa QR

Una pagina protetta dove i clienti vedono la rete di espositori e QR code.

1. **Crea una pagina** con `[advtr_area_riservata]`.
2. **Chi non è loggato** vede l'invito ad accedere; **chi è loggato ma non è cliente** vede un avviso.
3. **Il cliente (o l'admin)** vede la dashboard con la **mappa dei punti QR**.

> **Protezione lato server.** Le coordinate dei QR arrivano solo dall'endpoint autenticato `GET advertrieste/v1/qr-map`, che risponde **401** a chi non è loggato e **403** a chi non ha il permesso. Nessun dato riservato è incorporato nella pagina.

> **Decisione da confermare.** Oggi ogni cliente con permesso vede **l'intera rete** di punti QR. Alternative: "ciascuno vede solo i propri" o "solo admin".

---

## 8. Statistiche

Ogni scheda locale registra eventi (visualizzazioni, click sulla mappa, coupon riscattati, click sui contatti) e li mostra in una dashboard.

- **Pagina dashboard:** `[advtr_statistiche]` (nell'area clienti). Un cliente vede solo le proprie schede; l'admin può indicare una scheda con `id="123"`.
- **Cosa mostra:** quattro contatori + un grafico a barre dell'andamento visite (30 giorni).
- **Badge "Novità":** sotto le **20 visite** la scheda mostra "Novità" invece del numero reale — niente conteggi gonfiati.
- **Come si popolano:** la mappa registra un *click mappa* all'apertura del popup; le visite alla scheda si registreranno dalla pagina scheda (in arrivo); i coupon vengono contati alla validazione.
- **Endpoint:** scrittura `POST /locale/{id}/track` (protetto, con limite anti-abuso) · lettura `GET /stats/{id}` (solo proprietario/admin).

---

## 9. Offerte & coupon

Promozioni a tempo con countdown, e coupon validabili dall'esercente sul posto.

### Creare un'offerta

1. **Offerte → Aggiungi nuova.** Titolo e descrizione nell'editor.
2. **Box "Dati offerta":** collega il **locale**, imposta **inizio** e **scadenza**, scegli **tipo coupon** (codice o QR) e il **codice** (ciò che il cliente presenta all'esercente).
3. **Pubblica.** Comparirà nella pagina con `[advtr_offerte]` finché è nella finestra di validità, con il countdown.

### Validare un coupon (esercente)

1. **Pagina riservata** con `[advtr_valida_coupon]`.
2. L'esercente **seleziona l'offerta** e **inserisce il codice** mostrato dal cliente.
3. Il sistema verifica codice e validità, **registra il riscatto** e lo conteggia nelle statistiche del locale.

> **Automatico.** Un job giornaliero (`advtr_expire_coupons`) marca come scadute le offerte oltre la data di scadenza: spariscono dall'elenco pubblico da sole.

---

## 10. Eventi & workflow di revisione

Gli eventi hanno un modello a **doppia versione**: il pubblico vede sempre l'ultima versione **approvata**, mentre le modifiche restano in lavorazione finché un admin non le approva.

Stati: **Bozza** (in lavorazione, invisibile) → **In revisione** (inviato, in attesa admin) → **Pubblicato** (approvato, visibile).

### Grande evento (curato dall'admin)

1. **Eventi → Aggiungi nuovo** → tipo **"Grande evento"**, date, **locali collegati** (bar/negozi aderenti), immagine.
2. **Salva**, poi nel box **"Workflow revisione"** premi **"Approva e pubblica"**.
3. Compare in `[advtr_grandi_eventi]` con countdown e locali aderenti.

### Evento di un organizzatore terzo

1. L'organizzatore (o l'admin per suo conto) compila l'evento e preme **"Invia in revisione"**.
2. L'admin controlla e preme **"Approva e pubblica"**: solo ora va online.
3. **Modifiche successive:** quando l'evento pubblicato viene modificato torna in **bozza**; il pubblico continua a vedere la versione approvata precedente finché non si ri-approva.

> **Perché non si vede il post direttamente.** Il tipo "Evento" è volutamente **non pubblico**: il pubblico riceve solo lo snapshot approvato via `GET /eventi` e `/grandi-eventi`. Così una bozza non finisce mai online per errore.

---

## 11. Automazioni (scadenze & email)

Due job giornalieri (WP-Cron) girano da soli.

| Job | Frequenza | Cosa fa |
|---|---|---|
| `advtr_check_scadenze` | giornaliero | Email di avviso a admin + cliente a **30/15/7 giorni** dalla scadenza; **sospende** le schede scadute (spariscono dalla mappa); spegne l'evidenza scaduta. |
| `advtr_expire_coupons` | giornaliero | Marca come scadute le offerte oltre la data di scadenza. |

> **Nota su WP-Cron.** WP-Cron si attiva al traffico del sito. In produzione, su un sito poco visitato, conviene collegare un cron di sistema reale. Le soglie di avviso (30/15/7) sono personalizzabili via filtro `advtr_scadenza_soglie`.

---

## 12. Checklist di primo setup

1. **Categorie** — già create (mangiare, bere, visitare, shopping, servizi). Verifica in *Categorie*.
2. **Primi contenuti** — crea qualche **Locale** con coordinate e categoria, e qualche **Punto d'interesse**.
3. **Pagina Mappa** — nuova pagina con `[advtr_map]`, aggiungila al menu.
4. **Utenti clienti** — crea gli utenti **Cliente (locale)** e impostali come Autore delle rispettive schede.
5. **Area riservata** — pagina con `[advtr_area_riservata]` e, se vuoi, pagine con `[advtr_statistiche]` e `[advtr_valida_coupon]`.
6. **Offerte ed eventi** — crea le prime offerte; per gli eventi ricorda il passaggio di **approvazione**. Pubblica le pagine `[advtr_offerte]`, `[advtr_grandi_eventi]`, `[advtr_eventi]`.

---

## 13. Stato attuale & limiti

- **Pronto:** mappa pubblica + ingresso guidato, schede locali complete (con mini-mappa, indicazioni, recensioni), Punti d'interesse posizionabili, categorie, area riservata + mappa QR protetta, statistiche + tracking, offerte + coupon + validazione, eventi con workflow completo + evidenziazione locali durante i grandi eventi, scadenze + email automatiche, **editing self-service** per clienti e organizzatori (bacheca scoped).
- **Condizionato:** recensioni Google (§1.5) attive solo con chiave `ADVTR_GOOGLE_PLACES_KEY` in `wp-config.php` (+ tetto di spesa lato Google). Pagamenti WooCommerce (§2.6): il bridge di rinnovo validità è pronto ma richiede WooCommerce + Subscriptions installati per funzionare.
- **In sospeso:** import automatico eventi da fonti esterne (turismofvg.it) — subordinato a permesso.
- **Da confermare:** visibilità della mappa QR (tutti i clienti / solo i propri / solo admin).

---

## Riepilogo endpoint REST (namespace `advertrieste/v1`)

| Metodo | Endpoint | Accesso |
|---|---|---|
| GET | `/map/markers` | Pubblico (mai punto_qr) |
| GET | `/qr-map` | Auth + `advtr_view_qr_map` |
| POST | `/locale/{id}/track` | Pubblico (nonce + rate-limit) |
| GET | `/stats/{id}` | Proprietario / admin |
| GET | `/offerte` | Pubblico (solo attive) |
| POST | `/offerta/{id}/redeem` | Proprietario locale / admin |
| GET | `/eventi`, `/grandi-eventi` | Pubblico (solo versione approvata) |
| POST | `/evento/{id}/submit` | Autore / admin |
| POST | `/evento/{id}/approve` | `advtr_approve_evento` |
