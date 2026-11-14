-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_webcams_webcam(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL,
	entity integer DEFAULT 1 NOT NULL,
	label varchar(255),
	fk_soc integer,
	location varchar(255),
	date_installation date,
	sim varchar(128),
	public_url varchar(255),
	note_public text,
	note_private text,
	last_contact datetime,
	last_capture datetime,
	mac varchar(17),
	hostname varchar(100),
	router_model varchar(150),
	openwrt varchar(100),
	kernel varchar(50),
	app_version varchar(30),
	board_fw varchar(30),
	camera varchar(150),
	vpn_ip varchar(45),
	public_ip varchar(45),
	wan_ip varchar(45),
	uptime integer,
	mem_free_kb integer,
	disk_free_kb integer,
	queue integer,
	upload_protocol varchar(10),
	upload_server varchar(200),
	upload_directory varchar(200),
	shot_interval integer,
	maintenance smallint,
	heating smallint,
	monitoring smallint,
	latitude double(10,6),
	longitude double(10,6),
	temperature double(5,1),
	humidity double(5,1),
	temperature_ext double(5,1),
	humidity_ext double(5,1),
	last_photo varchar(200),
	first_contact datetime,
	report_json text,
	date_creation datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer,
	last_main_doc varchar(255),
	import_key varchar(14),
	model_pdf varchar(255),
	status integer DEFAULT 1 NOT NULL
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
