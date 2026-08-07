# Da fare — AdverTrieste

Cosa resta aperto e cosa aspetta una decisione. I *perché* delle scelte già
prese stanno in `docs/diario-di-bordo.md`; i requisiti in
`docs/specifiche-funzionali.md`.

Ultimo aggiornamento: **7 agosto 2026**.

---

## Decisioni che aspettano te

Nessuna di queste è un problema tecnico: serve una scelta di prodotto.

- [ ] **Mappa QR — chi vede cosa.** Oggi ogni cliente con il permesso vede
      l'intera rete di espositori, ed è una leva commerciale (dimostra la
      capillarità). Alternative: ciascuno vede solo i propri, oppure solo
      l'amministratore. Vedi `specifiche-funzionali.md` §2.5.
- [ ] **Coupon.** Oggi il codice è unico e statico, riscattabile un numero
      illimitato di volte. Se serve un codice per cliente finale, o un tetto ai
      riscatti, è un modello dati diverso.
- [ ] **Storico degli abbonamenti.** L'attivazione non registra chi l'ha fatta
      né quanto è stato pagato. Se servono importo, fattura e operatore, è una
      tabella a sé, non un campo in più.
- [ ] **Durate degli abbonamenti** fisse a 30/90/180/365 giorni. Se serve una
      data di scadenza libera, si aggiunge come alternativa alla durata.
- [ ] **Avviso al cliente quando l'amministratore gli cambia la password.**
      Oggi non parte nulla: gliela comunica l'amministratore. Un'email di
      cortesia (senza la password dentro) è facile da aggiungere.
- [ ] **Banner cookie sulla console.** `cookie-law-info` compare anche nelle
      pagine di gestione, dove non serve. Si toglie con un filtro.
- [ ] **Import eventi da turismofvg.it** — bloccato: manca il permesso di
      riuso. Vedi `specifiche-funzionali.md` §4.4. **Non implementare scraping.**

## Verifiche da fare prima del lancio

- [ ] **Google Places.** Il progetto Cloud ha ancora accesso alla *legacy* API?
      Se no, va portato su Places API (New). Serve comunque il tetto di spesa.
- [ ] **Aggiornamenti.** WordPress core e cinque plugin hanno aggiornamenti in
      attesa. Non applicati: prima un backup del database.
- [ ] **Nome del sito.** È ancora `template2`, e finisce nell'attributo `alt`
      del logo e nel titolo delle pagine. Si cambia in Impostazioni → Generali.
- [ ] **`wp-config.php` non entra nel backup UpdraftPlus** (sta fuori da
      `wp-content`). Va copiato a mano: contiene le credenziali del database e
      conterrà `ADVTR_GOOGLE_PLACES_KEY`.

## Lavoro tecnico rimasto

- [ ] **`uninstall.php` assente.** Disinstallando il plugin restano tabelle,
      opzioni e contenuti. Va deciso cosa cancellare e cosa no: le statistiche
      di anni non si buttano per una disattivazione.
- [ ] **Nessun file di traduzione.** Le stringhe passano tutte da `__()` con
      text domain `advertrieste`, ma manca la cartella `languages/` e il `.pot`.
      Il sito è in italiano, quindi non è urgente.
- [ ] **Paginazione.** Gli elenchi della console caricano fino a 200 elementi
      (300 per i punti QR) senza pagine. Regge la scala attuale; con qualche
      centinaio di clienti no. PHPCS lo segnala già come warning.
- [ ] **Lo `stato` dei punti QR è decorativo.** `/qr-map` non filtra per stato:
      un espositore "inattivo" compare comunque sulla mappa riservata.
- [ ] **Migrazione a PHPUnit** quando ci sarà una CI. Le suite attuali girano
      con WP-CLI e coprono gli stessi percorsi; vedi `tests/README.md`.
- [ ] **Verifica visiva.** Tutto quello che riguarda l'aspetto è stato
      controllato lato server: markup, CSS, HTML servito. Gli strumenti di
      automazione del browser non hanno funzionato in questo ambiente, quindi
      il giudizio estetico sulle pagine non è mai stato confermato a video.

## Note sull'ambiente locale

- **Cache di pagina.** WP-Optimize serve HTML statico anche in locale: dopo una
  modifica ai template pubblici va svuotata, o si guarda una pagina vecchia
  (`WPO_Page_Cache::instance()->purge()`).
- **Dati demo.** `tools/seed-demo.php` crea tre clienti (`demo_cliente`,
  `demo_cliente2`, `demo_cliente3`, password `demo1234`), dieci locali, sei
  punti d'interesse, un'offerta, tre punti QR e le pagine con gli shortcode.
  Sono distribuiti su tre clienti apposta: con tutto in capo a uno,
  l'isolamento sembra rotto.
- **zsh non separa in parole** i parametri non quotati: negli script che
  passano più argomenti a WP-CLI vanno scritti separati, non in una variabile
  sola.
