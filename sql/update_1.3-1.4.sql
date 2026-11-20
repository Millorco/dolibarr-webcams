-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Da 1.3 a 1.4 (contratto 1.3 di Webcam 2.0, API Dolibarr 2.2): livello di
-- listino, opzioni a pagamento (sponsor, certificato maltempo, filmato finale,
-- rapporto mensile per email). Le tabelle llx_webcams_archive e
-- llx_webcams_register le creano i loro file, eseguiti prima di questo.
-- Viene eseguito alla riattivazione del modulo; se le colonne ci sono già
-- l'errore viene ignorato. Le colonne non hanno AFTER: il file funziona anche
-- partendo dalla 1.0, 1.1 o 1.2 (gli update precedenti sono eseguiti prima).

-- livello di listino (M4) e periodo del noleggio
ALTER TABLE llx_webcams_webcam ADD COLUMN plan_level varchar(16) DEFAULT 'base' NOT NULL;
ALTER TABLE llx_webcams_webcam ADD COLUMN plan_note varchar(255);
ALTER TABLE llx_webcams_webcam ADD COLUMN rental_start date;
ALTER TABLE llx_webcams_webcam ADD COLUMN rental_end date;

-- pacchetto sponsor e infopoint (M12)
ALTER TABLE llx_webcams_webcam ADD COLUMN sponsor_enabled smallint DEFAULT 0 NOT NULL;
ALTER TABLE llx_webcams_webcam ADD COLUMN sponsor_slots smallint;
ALTER TABLE llx_webcams_webcam ADD COLUMN sponsor_start date;
ALTER TABLE llx_webcams_webcam ADD COLUMN sponsor_end date;

-- giornate di maltempo certificate (O2)
ALTER TABLE llx_webcams_webcam ADD COLUMN opt_weather_cert smallint DEFAULT 0 NOT NULL;

-- filmato finale (M10)
ALTER TABLE llx_webcams_webcam ADD COLUMN film_option varchar(16);
ALTER TABLE llx_webcams_webcam ADD COLUMN film_format varchar(16);
ALTER TABLE llx_webcams_webcam ADD COLUMN film_duration integer;
ALTER TABLE llx_webcams_webcam ADD COLUMN film_status varchar(16);
ALTER TABLE llx_webcams_webcam ADD COLUMN film_url varchar(255);

-- rapporto mensile (O3)
ALTER TABLE llx_webcams_webcam ADD COLUMN report_enabled smallint DEFAULT 1 NOT NULL;
ALTER TABLE llx_webcams_webcam ADD COLUMN report_email varchar(255);
