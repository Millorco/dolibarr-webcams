# Modulo Webcam per Dolibarr

Modulo per [Dolibarr ERP/CRM](https://www.dolibarr.org) (versione 20 o più recente) che aggiunge l'oggetto **Webcam**, collegato ai clienti (soggetti terzi), e lo tiene aggiornato con i dati che mandano i router con [luci-app-webcam](https://github.com/Millorco/luci-app-webcam).

- **Menu Webcam → resoconto**: quante webcam sono in servizio, quali non rispondono, quali hanno app o firmware della scheda da aggiornare (confronto con `latest.json`), quali sono senza cliente; elenco con cliente, ultimo contatto, ultimo scatto, versioni, fotocamera e IP nella VPN (link a LuCI).
- **Scheda webcam**: a sinistra i dati che inserisci tu (nome, cliente, luogo, data di installazione, SIM, pagina pubblica), a destra i dati tecnici del router (modello, OpenWrt, versioni, fotocamera, IP, uptime, memoria e spazio, coda foto, upload, intervallo, manutenzione, anti-appannamento, healthchecks, coordinate, temperatura e umidità interne ed esterne, ultimo scatto). Schede *Note*, *Documenti* ed *Eventi*: negli eventi c'è la cronologia scritta in automatico (prima registrazione, cambi di versione, fotocamera, IP VPN, server di upload, riavvii).
- **Scheda Webcam nel cliente**: in ogni soggetto terzo, l'elenco delle sue webcam e il pulsante per aggiungerne una.
- **Elenco webcam** con filtri, colonne a scelta ed export di Dolibarr.
- **API** `POST /api/index.php/webcams/report`: riceve il JSON di `inventory_report`, riconosce la webcam dal MAC, la crea la prima volta e poi aggiorna solo i dati tecnici. I dati inseriti a mano non vengono mai toccati.

Contratti, preventivi, fatture (anche ricorrenti ed elettroniche con i moduli italiani) e il resto del CRM sono quelli di Dolibarr.

## Installazione

1. Scarica `module_webcams-<versione>.zip` dall'ultima [release](https://github.com/Millorco/dolibarr-webcams/releases) e caricalo da *Impostazioni → Moduli → Distribuisci/installa un modulo esterno*. In alternativa clona il repository direttamente nella cartella dei moduli:
   ```sh
   git clone https://github.com/Millorco/dolibarr-webcams htdocs/custom/webcams
   ```
2. *Impostazioni → Moduli*: attiva **Webcam** (nella famiglia CRM). Attiva anche **Terze parti**, **API/Web services (REST)** e **Agenda** (per la cronologia).
3. *Utenti e gruppi → Nuovo utente*: crea un utente per i router (es. login `webcam-router`), non amministratore, con una password lunga qualsiasi. Nella scheda *Permessi* dagli solo **Webcam → Leggere** e **Creare/modificare**. Poi *Modifica*, genera la **Chiave per API** e salva.
4. Dai i permessi Webcam anche agli utenti che devono vedere il menu (l'amministratore compreso).

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

## Opzioni

In *Impostazioni → Altre impostazioni*:

| Costante | Predefinito | Cosa fa |
|---|---|---|
| `WEBCAMS_OFFLINE_HOURS` | `13` | Ore senza invii dopo le quali una webcam "non risponde" |
| `WEBCAMS_LATEST_URL` | `latest.json` di luci-app-webcam | Ultima versione dell'app e del firmware |

## Nuova versione

Aggiorna `$this->version` in `core/modules/modWebcams.class.php`, il file `VERSION` e `ChangeLog.md`, poi pubblica una release su GitHub con tag uguale alla versione: lo zip viene creato e allegato da solo. Per crearlo a mano c'è `./build.sh`. Dolibarr legge `VERSION` per segnalare nella pagina dei moduli che c'è un aggiornamento.

Quando una versione aggiunge colonne (per esempio la 1.1, con temperatura e umidità esterne), dopo aver caricato lo zip disattiva e riattiva il modulo: Dolibarr esegue `sql/update_*.sql` e aggiunge le colonne senza toccare i dati.

## Licenza

GPL-3.0 o successiva, come il modello di modulo di Dolibarr da cui è generato.
