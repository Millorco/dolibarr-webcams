-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Da 1.1 a 1.2 (contratto API Dolibarr v2 di Webcam 2.0): contatori della
-- reflex, riassunto Victron, dati di pubblicazione del portal.
-- Viene eseguito alla riattivazione del modulo; se le colonne ci sono già
-- l'errore viene ignorato.
--
-- Le prime due righe ripetono update_1.0-1.1.sql: chi aggiorna direttamente
-- dalla 1.0 ha così le colonne a cui si riferiscono gli AFTER qui sotto,
-- qualunque sia l'ordine in cui Dolibarr legge i file update_*.sql.

ALTER TABLE llx_webcams_webcam ADD COLUMN temperature_ext double(5,1) AFTER humidity;
ALTER TABLE llx_webcams_webcam ADD COLUMN humidity_ext double(5,1) AFTER temperature_ext;

-- contatori della reflex
ALTER TABLE llx_webcams_webcam ADD COLUMN camera_serial varchar(64) AFTER camera;
ALTER TABLE llx_webcams_webcam ADD COLUMN shutter_count integer AFTER camera_serial;
ALTER TABLE llx_webcams_webcam ADD COLUMN shots_total integer AFTER shutter_count;
ALTER TABLE llx_webcams_webcam ADD COLUMN camera_hours double(8,1) AFTER shots_total;
ALTER TABLE llx_webcams_webcam ADD COLUMN camera_power_cycles integer AFTER camera_hours;
ALTER TABLE llx_webcams_webcam ADD COLUMN counters_date datetime AFTER camera_power_cycles;

-- Victron, riassunto
ALTER TABLE llx_webcams_webcam ADD COLUMN battery_v double(5,2) AFTER humidity_ext;
ALTER TABLE llx_webcams_webcam ADD COLUMN battery_v_min24 double(5,2) AFTER battery_v;
ALTER TABLE llx_webcams_webcam ADD COLUMN battery_soc double(5,1) AFTER battery_v_min24;
ALTER TABLE llx_webcams_webcam ADD COLUMN charge_state varchar(20) AFTER battery_soc;
ALTER TABLE llx_webcams_webcam ADD COLUMN solar_wh_yesterday integer AFTER charge_state;
ALTER TABLE llx_webcams_webcam ADD COLUMN victron_error smallint AFTER solar_wh_yesterday;
ALTER TABLE llx_webcams_webcam ADD COLUMN victron_date datetime AFTER victron_error;

-- portal e pubblicazione
ALTER TABLE llx_webcams_webcam ADD COLUMN portal_slug varchar(64) AFTER public_url;
ALTER TABLE llx_webcams_webcam ADD COLUMN url_current varchar(255) AFTER portal_slug;
ALTER TABLE llx_webcams_webcam ADD COLUMN windy_published smallint DEFAULT 0 AFTER url_current;
ALTER TABLE llx_webcams_webcam ADD COLUMN windy_id varchar(64) AFTER windy_published;
ALTER TABLE llx_webcams_webcam ADD COLUMN last_telemetry datetime AFTER last_capture;
