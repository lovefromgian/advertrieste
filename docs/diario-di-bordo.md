# Diario di bordo — AdverTrieste

Le decisioni prese e **perché**. Non un changelog: quello lo fa git. Qui sta il
ragionamento che dal codice non si ricostruisce, e gli inciampi che è costato
scoprire — perché il modo più caro di imparare una cosa è impararla due volte.

Ultimo aggiornamento: **7 agosto 2026**.

---

## 1. Due console, un guscio solo

Il cliente non entra nella bacheca di WordPress. Vede i media di tutto il sito,
può fare danni, e soprattutto non è una cosa da presentare.

Da lì è nata l'**area clienti in front-end**, e poi la **console
amministratore**, costruite sullo stesso guscio (`includes/console/`): stessa
sidebar, stessi componenti di tabella, stesse regole per gli asset. Due
interfacce diverse che condividono la struttura, invece di due impianti
paralleli che divergono al primo ritocco.

**Confine deliberato.** In console si prendono le decisioni ricorrenti —
approvare, pubblicare, sospendere, rinnovare, accendere l'evidenza — con un
gesto solo. La scrittura di contenuti lunghi resta agli editor esistenti:
rifare qui un editor completo significherebbe riscrivere ciò che WordPress già
fa, senza guadagno per chi lo usa.

**L'organizzatore eventi resta in bacheca.** Il suo flusso è più raro e passa
comunque da approvazione: non valeva una terza interfaccia.

## 2. Come si tolgono gli asset del tema

Le pagine di console prendono il controllo del template e ripuliscono CSS e JS
del tema, altrimenti l'aspetto dipende da quale tema è installato.

La regola è: **passa solo ciò che viene da questo plugin**, cioè `src` che
inizia per `ADVTR_URL`. Un elenco di handle da tenere non regge — è già
successo di dimenticarcene uno e di far sparire la validazione dei coupon.

Un'eccezione la merita il caso dell'handle senza `src`: serve per gli stili
inline, ma passa solo se l'handle inizia per `advtr`. Betheme iniettava un
`html{background-color:#17102a}` da un handle senza sorgente, e la console
usciva viola.

## 3. Cancellare: cestino, non raptus

In console si cestina; si ripristina o si elimina per sempre solo da dentro il
cestino. Sempre **due decisioni distinte**.

Con la console che sostituisce la bacheca, un cestino invisibile equivarrebbe a
una cancellazione definitiva mascherata. E un clic sbagliato non deve
distruggere una scheda con anni di statistiche.

- Il ripristino riporta in **bozza**, mai online: rimettere in pubblico
  qualcosa di cestinato senza deciderlo sarebbe una sorpresa sgradita.
- Eliminare un cliente **riassegna** schede, offerte ed eventi
  all'amministratore che agisce. Una scheda pagata non deve sparire perché si
  chiude un accesso.
- Amministratori e sé stessi restano intoccabili.

## 4. Abbonamenti: attivare non è rinnovare

Il **rinnovo** somma giorni partendo dalla scadenza attuale. L'**attivazione**
fissa la finestra da capo, e serve alla prima volta o quando si riparte con un
contratto nuovo — quindi sostituisce la validità in corso e chiede conferma.

**L'attivazione non pubblica la scheda.** Se non è mai stata online, metterla in
vetrina è una decisione separata: una scheda a metà non deve finire sulla mappa
perché è stata pagata. Fa eccezione la scheda che il cron aveva sospeso alla
scadenza — era già pubblica, e il pagamento la rimette dov'era. Due casi, due
messaggi diversi.

Le durate sono una **lista chiusa** (30/90/180/365). Prima il rinnovo accettava
qualunque numero positivo.

## 5. Password dei clienti

La via consigliata resta il **link via email**: così la password la conosce solo
il suo proprietario.

Ma se la posta non parte — sviluppo in locale, casella sbagliata, cliente al
telefono — senza un'alternativa l'account è inutilizzabile. Quindi si può anche
impostarla a mano, con un campo **in chiaro**: se devi dettare una password,
digitarla alla cieca porta solo a sceglierne una banale. C'è un pulsante
«Genera» e un minimo di 8 caratteri. Dopo il salvataggio non è più leggibile da
nessuna parte, console compresa.

Cambiare la password **chiude le sessioni aperte**: se la si cambia perché
qualcuno non deve più avere accesso, lasciarlo collegato vanifica il cambio.

## 6. Tracciamento senza nonce

`POST /locale/{id}/track` non usa il nonce per gli anonimi. Un nonce dura 12–24
ore, la cache di pagina serve HTML più vecchio, e il risultato era un 403 su
ogni visita tracciata — verificato con richieste reali.

Al suo posto: controllo di **origine** (Origin/Referer contro `home_url()`) e
rate-limit che include il tipo di evento nella chiave.

## 7. Scelte tecniche minori, ma con un perché

- **Grafici in SVG puro**, nessuna libreria: sono barre e una spezzata.
- **Nominatim** per il geocoding, con User-Agent dichiarato, un secondo fra le
  chiamate, cache e nessun uso massivo. Sono le loro condizioni d'uso.
- **Giorni di calendario**, non ore, per le scadenze: contando le ore, una
  scadenza fra dodici giorni veniva annunciata come tredici. Stessa funzione per
  la console e per il cron, così non possono divergere.
- **Le azioni che cambiano stato sono POST**, mai link: un GET che pubblica o
  sospende viene eseguito da qualunque prefetch del browser.

---

## Inciampi che è costato scoprire

Ognuno di questi è finito in un test, che è l'unico modo per non ripagarli.

**`wp_localize_script` scarta i dati in silenzio** se l'handle non è ancora
registrato. La mappa dei punti QR era vuota e non c'era alcun errore da nessuna
parte. Ora `Console::registra_asset_plugin()` registra prima di comporre.

**Due gestori sullo stesso hook.** `ClientArea::gestisci_azioni()` e
`AdminConsole::gestisci_azioni()` leggono lo stesso campo `advtr_azione` con
nonce diversi. Senza una guardia di pagina, il primo intercettava i POST della
console e li faceva morire con «Il link che hai seguito è scaduto» — su *ogni*
azione, non solo su «Aggiungi». I test non lo videro perché chiamavano i metodi
invece di passare dagli hook.

**Variabili CSS dichiarate su un contenitore che non c'è più.** Le sezioni
dell'area clienti sono state spostate nel guscio della console, dove
`.advtr-cliente` non esiste: ogni `var(--advtr-…)` restava irrisolta e «Salva
modifiche» diventava testo bianco su fondo bianco. C'era ed era pure
cliccabile. Verificare la presenza nel markup non bastava.

**`absint()` reinterpreta invece di rifiutare.** Una durata `-30` diventava
`30`. Con una lista chiusa di valori ammessi, quello che non è in lista è un
errore, non un numero da raddrizzare.

**`wp_set_password()` non chiude le sessioni.** Va fatto a parte.

**Una regex golosa in `posts_search`** si mangiava la condizione sulla password
e la ricerca per via non restituiva niente — in silenzio.

**Rendere più sezioni nello stesso processo** fa risultare "mancanti" asset già
stampati. Un processo per suite.

---

## Fatti, in ordine

Il dettaglio commit per commit è in `git log`; qui solo le tappe.

1. Scaffold del plugin, CPT, tassonomia, capability.
2. Mappa pubblica Leaflet, endpoint `/map/markers`, ingresso guidato.
3. Scheda attività, statistiche e tracking, offerte e coupon.
4. Eventi con workflow a doppia versione; scadenze ed email via cron.
5. Area riservata e mappa QR protetta lato server.
6. Area clienti in front-end; bacheca preclusa ai clienti; media isolati.
7. Pagine pubbliche rifatte sullo stile del documento di progetto, responsive.
8. Console amministratore: approvazioni, clienti, abbonamenti, contenuti, rete.
9. Creazione, poi eliminazione con cestino, in tutte le sezioni.
10. Attivazione abbonamenti; nome utente e password dei clienti.
11. Logo al posto del segnaposto testuale.
12. Suite di test portate nel repo (`tests/console/`).
