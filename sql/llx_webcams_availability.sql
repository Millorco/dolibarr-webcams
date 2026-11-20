-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Registro della disponibilità mensile (dalla 1.3), scritto da webcam-portal
-- con POST webcams/{id}/availability. Una riga per webcam e mese.

CREATE TABLE llx_webcams_availability(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	fk_webcam integer NOT NULL,
	fk_soc integer,
	period varchar(7) NOT NULL,
	availability_pct double(5,2),
	expected_hours integer NOT NULL,
	excluded_hours integer DEFAULT 0 NOT NULL,
	available_hours integer DEFAULT 0 NOT NULL,
	incidents integer DEFAULT 0 NOT NULL,
	downtime_hours double(7,1) DEFAULT 0 NOT NULL,
	response_breaches integer DEFAULT 0 NOT NULL,
	sla_enabled smallint DEFAULT 0 NOT NULL,
	target_pct double(5,2),
	sla_met smallint,
	final smallint DEFAULT 0 NOT NULL,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
