# Changelog

## 1.4

Contratto 1.3 di Webcam 2.0 (API Dolibarr 2.2): servizi che si vendono.

- **Listino e opzioni** sulla webcam, modificabili dalla scheda (blocco *Listino e servizi*): livello `plan_level` (`base`, `standard`, `pro`), `plan_note`, periodo del noleggio (`rental_start`, `rental_end`), pacchetto sponsor (`sponsor_enabled`, `sponsor_slots`, `sponsor_start`, `sponsor_end`), certificato delle giornate di maltempo (`opt_weather_cert`), filmato finale (`film_option`, `film_format`, `film_duration`; `film_status` e `film_url` scritti dal portal), rapporto mensile per email (`report_enabled`, `report_email`), controllati al salvataggio. Script `sql/update_1.3-1.4.sql`, che funziona partendo da qualsiasi versione precedente.
- **Matrice livello → funzioni** del contratto in sola lettura sotto la scheda, con le funzioni attive per la webcam (sponsor solo nel periodo).
- **Archivi dopo il noleggio** (M13): tabella `llx_webcams_archive` e riquadro nella scheda per aggiungere ed eliminare le righe; quando cambia il cliente la scheda propone la riga per il cliente precedente con la fine del noleggio a oggi.
- **Registro delle impronte** (O2): tabella `llx_webcams_register`, immutabile (nessun aggiornamento, nessuna colonna `tms`); scheda *Registro* in sola lettura con classificazione, hash, controllo della catena e ora del deposito. Una webcam con registro non si può eliminare.
- API: `GET {id}/portal` con `plan`, `options` (destinatari del rapporto controllati, predefiniti applicati) e `archives`; `PUT {id}/portal` con `film_status` (`queued`, `ready`; un `delivered` salvato non si sovrascrive, `ignored`) e `film_url`, evento "Filmato finale pronto"; `POST {id}/documents` con `doc_type`, `period` (in `ecm_files.keywords` = `webcams:<tipo>:<periodo>`), `portal_doc_id` e `send_email`; nuovi `POST` e `GET {id}/register` (`created`, `duplicate`, `conflict` con evento `AC_WEBCAMS_REGISTER`).
- **Email al cliente** con i modelli di Dolibarr: tipo di modello *Webcam* (`webcams_webcam`) in *Impostazioni → Email → Modelli email*, due modelli creati all'attivazione ("Rapporto mensile webcam", "Certificato giornate di maltempo"), sostituzioni `__WEBCAM_REF__`, `__WEBCAM_LABEL__`, `__WEBCAM_PERIOD__`, `__WEBCAM_LINK__`, invio con `CMailFile` e PDF allegato, evento `AC_WEBCAMS_SENTBYMAIL` con `ref_ext` = `portal:<portal_doc_id>` (idempotenza, `resend` per rimandare), evento per l'operatore se l'email non parte (`no_recipient`, `template_missing`, `mail_error`). Impostazioni `WEBCAMS_EMAIL_TEMPLATE_REPORT`, `WEBCAMS_EMAIL_TEMPLATE_WEATHER`, `WEBCAMS_EMAIL_FROM`. Anche *Invia email* della scheda propone questi modelli.
- Scheda *Documenti*: elenco dei rapporti e certificati del portal con tipo e periodo, filtro per tipo.
- Pulsante **Filmato consegnato** sulla scheda quando il portal ha segnato il filmato pronto (evento `AC_WEBCAMS_FILM`).
- Nessun permesso nuovo: il portal usa **Webcam → Sincronizzare dal portal** (51730014).

## 1.3

Contratto 1.1 di Webcam 2.0 (API Dolibarr 2.1): assistenza automatica del portal.

- Impegno di disponibilità del contratto di noleggio sulla webcam, modificabile dalla scheda: `sla_enabled`, `sla_offline_hours`, `sla_response_days`, `sla_target_pct`, `sla_hours`, `sla_weekdays` (controllati al salvataggio), con i predefiniti nelle impostazioni del modulo (`WEBCAMS_SLA_OFFLINE_HOURS` 3, `WEBCAMS_SLA_RESPONSE_DAYS` 3, `WEBCAMS_SLA_TARGET_PCT` 98). Colonne `availability_pct` e `availability_period` con l'ultima disponibilità definitiva.
- Tabella nuova `llx_webcams_availability` (registro mensile, cancellato insieme alla webcam). Script `sql/update_1.2-1.3.sql`, che funziona anche partendo dalla 1.0 e dalla 1.1.
- API nuove: `GET webcams/{id}/portal` (cliente, pubblicazione, impegno, soglie dell'otturatore, rttys), `PUT webcams/{id}/tickets/{ticket_id}` (messaggio privato, chiusura solo se non letto o letto, gravità solo verso l'alto, idempotente), `POST` e `GET webcams/{id}/availability`.
- `POST webcams/{id}/tickets`: `portal_rule`, `portal_alert_id`, `due_date`; un solo ticket aperto per webcam e regola (`reason: open_for_rule`); messaggio in testo semplice convertito in HTML; in fondo al ticket dati della webcam e link alla scheda, al portal e a rttys; `status` nella risposta.
- Evento in agenda per ogni mese definitivo ("Disponibilità novembre 2026: 99,12% (impegno 98%: rispettato)"), con codice `AC_WEBCAMS_SLA` se l'impegno non è rispettato.
- Scheda webcam: riepilogo dell'impegno, ultima disponibilità mensile, tabella degli ultimi 12 mesi con il confronto con la soglia. Resoconto: webcam con impegno sotto la soglia nel mese (riquadro e filtro, mese scelto con le frecce).
- Vita dell'otturatore: stessa soglia (arrotondata per eccesso) per il livello nella scheda, gli eventi in agenda e `GET {id}/portal`; resta il modulo la sola fonte degli avvisi.
- Elenco webcam compatibile con Dolibarr 20 (template e opzione della colonna delle caselle di Dolibarr 21+).


## 1.2

Contratto API Dolibarr v2 di Webcam 2.0 (portal `webcam-portal`).

- Colonne nuove: contatori della reflex (`camera_serial`, `shutter_count`, `shots_total`, `camera_hours`, `camera_power_cycles`, `counters_date`), riassunto Victron (`battery_v`, `battery_v_min24`, `battery_soc`, `charge_state`, `solar_wh_yesterday`, `victron_error`, `victron_date`), portal (`portal_slug`, `url_current`, `windy_published`, `windy_id`, `last_telemetry`). Script `sql/update_1.1-1.2.sql`, che funziona anche partendo dalla 1.0.
- Permesso nuovo **Webcam → Sincronizzare dal portal** (`portal`, id 51730014) per l'utente `webcam-portal`.
- API nuove: `GET webcams/bymac/{mac}`, `PUT webcams/{id}/portal`, `POST webcams/{id}/counters`, `POST webcams/{id}/events`, `POST webcams/{id}/tickets` (modulo Ticket), `POST webcams/{id}/interventions` (modulo Interventi), `POST webcams/{id}/documents` (PDF nei documenti della webcam). `POST webcams/report` resta compatibile e accetta in più i contatori, `camera_serial`, `battery_v` e `charge_state`.
- Cronologia automatica: cambio di fotocamera (numero di serie), otturatore vicino alla fine della vita e vita raggiunta, errori del regolatore Victron, pubblicazione su Windy, documenti allegati dal portal, eventi importanti mandati dal portal.
- Scheda webcam: vita dell'otturatore con avviso, contatori, Victron, link a `current.jpg` e alla pagina pubblica del portal, flag Windy; pulsanti **Terminale remoto** e **LuCI remota** (rttys).
- Resoconto: reflex vicine alla fine della vita e webcam senza contatto col portal.
- Impostazioni del modulo: URL del portal, ore senza dati dal portal, vita dell'otturatore (100000 scatti predefiniti, EOS 2000D/1500D) e soglia di avviso, URL di rttys, ore senza invii, URL di `latest.json`; avviso e pulsante se il database non è aggiornato.
- Ticket e interventi creati dal portal compaiono negli *Oggetti collegati* della webcam e viceversa.
- Funziona anche con Dolibarr 20 (funzioni `dolBuildUrl()` e `getDolDefaultContextPage()` sostituite se mancano).

## 1.1

Temperatura e umidità del sensore esterno al case (`temperature_ext`, `humidity_ext`, inviati da luci-app-webcam 1.0-rc8 o successiva) nella scheda webcam. Chi aggiorna dalla 1.0 deve disattivare e riattivare il modulo, così vengono aggiunte le due colonne.

## 1.0

Prima versione: oggetto Webcam collegato ai clienti, resoconto, scheda Webcam nei soggetti terzi, API `webcams/report` per i router con cronologia negli eventi.
