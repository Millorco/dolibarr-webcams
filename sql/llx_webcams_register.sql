-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Registro giornaliero delle impronte (O2, dalla 1.4), depositato da
-- webcam-portal con POST webcams/{id}/register. Dolibarr è il secondo
-- testimone: le righe non si modificano più (nessuna colonna tms, nessun
-- UPDATE né dalle API né dalla scheda). date_creation è l'ora del deposito.

CREATE TABLE llx_webcams_register(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_webcam integer NOT NULL,
	fk_soc integer,
	day date NOT NULL,
	timezone varchar(64) NOT NULL,
	hash_scheme varchar(16) NOT NULL,
	day_hash char(64) NOT NULL,
	prev_day_hash char(64),
	photos integer NOT NULL,
	photos_unsynced integer DEFAULT 0 NOT NULL,
	classification varchar(16),
	reasons varchar(32),
	wet_hours smallint,
	frost_hours smallint,
	fog_hours smallint,
	date_creation datetime NOT NULL
) ENGINE=innodb;
