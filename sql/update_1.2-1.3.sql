-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Da 1.2 a 1.3 (contratto 1.1 di Webcam 2.0, API Dolibarr 2.1): soglie del
-- contratto di noleggio (impegno di disponibilità) e ultima disponibilità
-- mensile definitiva. La tabella llx_webcams_availability la crea
-- llx_webcams_availability.sql, eseguito prima di questo file.
-- Viene eseguito alla riattivazione del modulo; se le colonne ci sono già
-- l'errore viene ignorato. Le colonne non hanno AFTER: il file funziona anche
-- partendo dalla 1.0 o dalla 1.1 (le colonne di quelle versioni le aggiungono
-- update_1.0-1.1.sql e update_1.1-1.2.sql, eseguiti prima).

-- impegno di disponibilità del contratto di noleggio
ALTER TABLE llx_webcams_webcam ADD COLUMN sla_enabled smallint DEFAULT 0 NOT NULL;
ALTER TABLE llx_webcams_webcam ADD COLUMN sla_offline_hours integer;
ALTER TABLE llx_webcams_webcam ADD COLUMN sla_response_days integer;
ALTER TABLE llx_webcams_webcam ADD COLUMN sla_target_pct double(5,2);
ALTER TABLE llx_webcams_webcam ADD COLUMN sla_hours varchar(11);
ALTER TABLE llx_webcams_webcam ADD COLUMN sla_weekdays varchar(7);

-- ultima disponibilità mensile definitiva (per elenco e resoconto)
ALTER TABLE llx_webcams_webcam ADD COLUMN availability_pct double(5,2);
ALTER TABLE llx_webcams_webcam ADD COLUMN availability_period varchar(7);
