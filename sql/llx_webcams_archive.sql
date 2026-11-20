-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Archivio dopo il noleggio (M13, dalla 1.4): per quale cliente e fino a
-- quando l'archivio di un noleggio finito resta visibile nel portal.
-- Compilata dalla scheda della webcam, letta con GET webcams/{id}/portal.

CREATE TABLE llx_webcams_archive(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_webcam integer NOT NULL,
	fk_soc integer NOT NULL,
	rental_start date,
	rental_end date NOT NULL,
	archive_until date NOT NULL,
	note varchar(255),
	fk_user_creat integer,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
