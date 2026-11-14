-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Da 1.0 a 1.1: temperatura e umidità del sensore esterno al case.
-- Viene eseguito alla riattivazione del modulo; se le colonne ci sono già
-- l'errore viene ignorato.

ALTER TABLE llx_webcams_webcam ADD COLUMN temperature_ext double(5,1) AFTER humidity;
ALTER TABLE llx_webcams_webcam ADD COLUMN humidity_ext double(5,1) AFTER temperature_ext;
