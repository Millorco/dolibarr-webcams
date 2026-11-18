# Modulo Webcam per Dolibarr

Modulo per [Dolibarr ERP/CRM](https://www.dolibarr.org) (versione 20 o più recente) che aggiunge l'oggetto **Webcam**, collegato ai clienti (soggetti terzi), e lo tiene aggiornato con i dati che mandano i router con [luci-app-webcam](https://github.com/Millorco/luci-app-webcam).

- **Menu Webcam → resoconto**: quante webcam sono in servizio, quali non rispondono, quali hanno app o firmware della scheda da aggiornare (confronto con `latest.json`), quali sono senza cliente, quali reflex sono vicine alla fine della vita dell'otturatore e (se il portal è configurato) quali non hanno contatto col portal; elenco con cliente, ultimo contatto, ultimo scatto, versioni, fotocamera, vita dell'otturatore, ultimo contatto col portal e IP nella VPN (link a LuCI).
- **Scheda webcam**: a sinistra i dati che inserisci tu (nome, cliente, luogo, data di installazione, SIM, pagina pubblica), a destra i dati tecnici del router (modello, OpenWrt, versioni, fotocamera, IP, uptime, memoria e spazio, coda foto, upload, intervallo, manutenzione, anti-appannamento, healthchecks, coordinate, temperatura e umidità interne ed esterne, ultimo scatto), i contatori della reflex con la percentuale di vita dell'otturatore (avviso arancione dalla soglia, rosso a fine vita), il riassunto Victron (tensione, minimo 24 h, fase di carica, produzione di ieri, errori), i link a `current.jpg` e alla pagina pubblica del portal, il flag *Pubblicata su Windy*. Pulsanti **Terminale remoto** e **LuCI remota** se è impostato l'URL di rttys. Schede *Note*, *Documenti* ed *Eventi*: negli eventi c'è la cronologia scritta in automatico (prima registrazione, cambi di versione, fotocamera e numero di serie, IP VPN, server di upload, riavvii, otturatore a fine vita, errori Victron, Windy, documenti ed eventi importanti del portal). Nel riquadro *Oggetti collegati* i ticket e gli interventi aperti dal portal.
- **Scheda Webcam nel cliente**: in ogni soggetto terzo, l'elenco delle sue webcam e il pulsante per aggiungerne una.
- **Elenco webcam** con filtri, colonne a scelta ed export di Dolibarr.
- **API** `POST /api/index.php/webcams/report`: riceve il JSON di `inventory_report`, riconosce la webcam dal MAC, la crea la prima volta e poi aggiorna solo i dati tecnici. I dati inseriti a mano non vengono mai toccati.
- **API per webcam-portal** (dalla 1.2): contatori, Victron, dati di pubblicazione, eventi, ticket, interventi e PDF (vedi *API per webcam-portal* sotto).

Contratti, preventivi, fatture (anche ricorrenti ed elettroniche con i moduli italiani) e il resto del CRM sono quelli di Dolibarr.

## Installazione

1. Scarica `module_webcams-<versione>.zip` dall'ultima [release](https://github.com/Millorco/dolibarr-webcams/releases) e caricalo da *Impostazioni → Moduli → Distribuisci/installa un modulo esterno*. In alternativa clona il repository direttamente nella cartella dei moduli:
   ```sh
   git clone https://github.com/Millorco/dolibarr-webcams htdocs/custom/webcams
   ```
2. *Impostazioni → Moduli*: attiva **Webcam** (nella famiglia CRM). Attiva anche **Terze parti**, **API/Web services (REST)** e **Agenda** (per la cronologia).
3. *Utenti e gruppi → Nuovo utente*: crea un utente per i router (es. login `webcam-router`), non amministratore, con una password lunga qualsiasi. Nella scheda *Permessi* dagli solo **Webcam → Leggere** e **Creare/modificare**. Poi *Modifica*, genera la **Chiave per API** e salva.
4. Se usi webcam-portal crea anche l'utente `webcam-portal` (vedi sotto).
5. Dai i permessi Webcam anche agli utenti che devono vedere il menu (l'amministratore compreso).

## Router

In LuCI, **Webcam → System → Monitoring**:

- **Inventory URL**: l'indirizzo con cui apri Dolibarr, seguito da `/api/index.php/webcams/report`. Per esempio `https://crm.example.com/api/index.php/webcams/report`, oppure `https://example.com/dolibarr/htdocs/api/index.php/webcams/report` se Dolibarr è in una sottocartella.
- **Inventory token**: la chiave API dell'utente `webcam-router` (non la password).

Lo script manda il token come `Authorization: Bearer`, che Dolibarr accetta come la sua intestazione `DOLAPIKEY`. Prova da SSH:

```sh
inventory_report && echo OK || logread -e inventory | tail -n 3
```

La webcam compare nel menu Webcam come `WC-<MAC>`: la colleghi al cliente modificandola. Il router invia da solo ogni 6 ore e a ogni avvio.

### Se non funziona

| Risposta | Cosa vuol dire |
|---|---|
| `HTTP 301` / `302` | L'URL non è quello giusto (manca `/api/index.php/webcams/report`, `http` invece di `https`, `www`…). Sul router `curl -s -o /dev/null -X POST -w '%{redirect_url}\n' "$(uci get webcam.@general[0].inventory_url)"` mostra dove rimanda. |
| `HTTP 401` | Chiave API sbagliata. |
| `HTTP 403` | L'utente dei router non ha i permessi Webcam. |
| `HTTP 404` | URL sbagliato, modulo API REST spento, o Dolibarr non ha ancora registrato le API del modulo: cancella `api/temp/routes.php` nella cartella dei documenti, o disattiva e riattiva il modulo Webcam. |

Aperto nel browser, `…/webcams/report` risponde sempre 404 perché accetta solo POST: per provare dal browser usa `…/api/index.php/webcams`, che deve rispondere 401.

L'IP nella VPN viene dall'interfaccia WireGuard `wglog` di luci-app-webcam o, se non c'è, dalla prima interfaccia WireGuard del router (serve luci-app-webcam dopo la 1.0-rc3).

## API per webcam-portal

Dalla 1.2 il modulo implementa il contratto [API Dolibarr v2](https://github.com/Millorco/webcam-portal/blob/main/docs/contratto/api-dolibarr-v2.md) di Webcam 2.0. Gli endpoint li chiama il portal (non il router), con la chiave API dell'utente `webcam-portal` nell'intestazione `DOLAPIKEY` (o `Authorization: Bearer`). Tutti sotto `/api/index.php/webcams/`; `{id}` è il `rowid` della webcam.

| Endpoint | Cosa fa | Permessi in più |
|---|---|---|
| `GET bymac/{mac}` | La webcam dal MAC, come `GET webcams/{id}`. Nel percorso il MAC va **senza i due punti** (`bymac/9483c412abcd` o `bymac/94-83-c4-12-ab-cd`), perché Dolibarr rifiuta `:` nel percorso dell'URL; con i due punti si usa `bymac?mac=94:83:c4:12:ab:cd` | solo Webcam → Leggere |
| `PUT {id}/portal` | `portal_slug`, `url_current`, `windy_published`, `windy_id`; il cambio di Windy va in cronologia | |
| `POST {id}/counters` | Contatori della reflex e riassunto Victron. Un contatore più basso di quello salvato con lo stesso numero di serie viene rifiutato (`ignored`); un numero di serie diverso è un cambio di fotocamera (evento in cronologia, i contatori non inviati si azzerano) | |
| `POST {id}/events` | Evento in agenda (`AC_WEBCAMS_PORTAL`), idempotente con `portal_event_id` (`ref_ext` = `portal:<id>`) | Agenda → Creare eventi; 501 se l'Agenda è spenta |
| `POST {id}/tickets` | Ticket per il cliente della webcam, collegato alla webcam; idempotente con `portal_event_id` (salvato in `extraparams` del ticket: la tabella dei ticket non ha `ref_ext`). Tipo, gravità e categoria inesistenti diventano i predefiniti del modulo Ticket | Ticket → Creare/modificare; 501 se il modulo Ticket è spento |
| `POST {id}/interventions` | Intervento in bozza per il cliente (400 se la webcam non ha cliente), con righe e durate, collegato alla webcam; idempotente con `portal_event_id` | Interventi → Creare/modificare; 501 se il modulo Interventi è spento |
| `POST {id}/documents` | PDF in base64 (max 10 MB, `sha256` facoltativo) nei documenti della webcam; 409 se esiste e `overwrite` = 0, 422 se lo `sha256` non corrisponde | |

Errori: `401` chiave sbagliata, `403` permesso mancante, `404` webcam inesistente, `400` dati non validi.

### Utente `webcam-portal`

*Utenti e gruppi → Nuovo utente*, login `webcam-portal`, non amministratore, password lunga qualsiasi. Permessi:

- **Webcam → Leggere** e **Sincronizzare dal portal** (non serve *Creare/modificare*: il portal tocca solo i propri campi);
- **Terze parti → Leggere** e **Estendi l'accesso a tutti i terzi** (senza quest'ultimo Dolibarr nega l'accesso alle webcam di clienti di cui l'utente non è commerciale, e a quelle senza cliente);
- **Agenda → Leggere/Creare gli eventi propri**;
- **Ticket → Leggere e Creare/modificare**, se usi il modulo Ticket;
- **Interventi → Leggere e Creare/modificare**, se usi il modulo Interventi.

Poi genera la **Chiave per API** e inseriscila nella configurazione del portal.

Prova:

```sh
curl -s -H "DOLAPIKEY: <chiave>" https://crm.example.com/api/index.php/webcams/bymac/9483c412abcd
curl -s -X POST -H "DOLAPIKEY: <chiave>" -H 'Content-Type: application/json' \
  -d '{"camera":{"serial":"123456789012","shots_total":48213},"power":{"battery_v":13.2,"charge_state":"bulk"}}' \
  https://crm.example.com/api/index.php/webcams/12/counters
```

### Router (luci-app-webcam 1.1)

`report` resta identico; in più accetta (facoltativi) `camera_serial`, `shutter_count`, `shots_total`, `camera_hours`, `camera_power_cycles`, `battery_v`, `charge_state`, con le stesse regole di `counters`. Un router con la 1.0-rc8 continua a funzionare.

## Accesso remoto (rttys)

Con l'URL di rttys impostato (es. `https://remote.viewlapse.com`) la scheda webcam ha i pulsanti:

- **Terminale remoto**: `<rttys>/api/connect/<rif>` (apre il terminale se il router è collegato, altrimenti la pagina di rttys "offline");
- **LuCI remota**: `<rttys>/api/web/<rif>/http/127.0.0.1%3A80%2F`.

**Convenzione**: l'id del dispositivo in rtty (`rtty -I`) è il **riferimento della webcam**, cioè `WC-` seguito dal MAC in maiuscolo senza i due punti (es. `WC-9483C412ABCD`), lo stesso che il modulo assegna alla prima registrazione. Serve essere collegati a rttys.

## Opzioni

In *Impostazioni → Moduli → Webcam → Impostazioni*:

| Costante | Predefinito | Cosa fa |
|---|---|---|
| `WEBCAMS_OFFLINE_HOURS` | `13` | Ore senza invii dopo le quali una webcam "non risponde" |
| `WEBCAMS_LATEST_URL` | `latest.json` di luci-app-webcam | Ultima versione dell'app e del firmware |
| `WEBCAMS_PORTAL_URL` | vuoto | URL base del portal (es. `https://portal.viewlapse.com`): link a `current.jpg`, `current.json` e alla pagina pubblica `/cam/<slug>/`; attiva il controllo "senza contatto col portal" |
| `WEBCAMS_PORTAL_OFFLINE_HOURS` | `2` | Ore senza dati dal portal (`last_telemetry`) dopo le quali la webcam è "senza contatto col portal" (il portal manda i contatori al massimo una volta l'ora) |
| `WEBCAMS_SHUTTER_LIFE` | `100000` | Vita prevista dell'otturatore in scatti (Canon EOS 2000D/1500D) |
| `WEBCAMS_SHUTTER_WARN_PCT` | `90` | Percentuale della vita da cui la reflex è "vicina alla fine della vita" |
| `WEBCAMS_RTTYS_URL` | vuoto | URL base di rttys per i pulsanti di accesso remoto |

Per la vita dell'otturatore conta il contatore letto dalla fotocamera (`shutter_count`) se c'è, altrimenti quello del router (`shots_total`).

## Nuova versione

Aggiorna `$this->version` in `core/modules/modWebcams.class.php`, il file `VERSION` e `ChangeLog.md`, poi pubblica una release su GitHub con tag uguale alla versione: lo zip viene creato e allegato da solo. Per crearlo a mano c'è `./build.sh`. Dolibarr legge `VERSION` per segnalare nella pagina dei moduli che c'è un aggiornamento.

Quando una versione aggiunge colonne (per esempio la 1.1, con temperatura e umidità esterne), dopo aver caricato lo zip disattiva e riattiva il modulo: Dolibarr esegue `sql/update_*.sql` e aggiunge le colonne senza toccare i dati.

### Aggiornare alla 1.2 (dalla 1.1 o dalla 1.0)

1. Carica lo zip della 1.2 (o `git pull` nella cartella `custom/webcams`).
2. *Impostazioni → Moduli*: **disattiva e riattiva Webcam**. Dolibarr esegue `update_1.0-1.1.sql` e `update_1.1-1.2.sql`: si parte indifferentemente dalla 1.0 o dalla 1.1, i dati e i permessi degli utenti restano. In alternativa, la pagina *Impostazioni* del modulo segnala se il database non è aggiornato e ha il pulsante **Aggiorna il database**.
3. Se le API non trovano gli endpoint nuovi (404), cancella `api/temp/routes.php` nella cartella dei documenti; su un server con OPcache senza controllo dei file riavvia PHP.
4. Crea l'utente `webcam-portal` e imposta URL del portal e di rttys nelle impostazioni del modulo.

## Licenza

GPL-3.0 o successiva, come il modello di modulo di Dolibarr da cui è generato.
