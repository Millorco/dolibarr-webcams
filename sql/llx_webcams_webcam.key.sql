-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_webcams_webcam ADD INDEX idx_webcams_webcam_rowid (rowid);
ALTER TABLE llx_webcams_webcam ADD UNIQUE INDEX uk_webcams_webcam_ref (ref, entity);
ALTER TABLE llx_webcams_webcam ADD UNIQUE INDEX uk_webcams_webcam_mac (mac, entity);
ALTER TABLE llx_webcams_webcam ADD INDEX idx_webcams_webcam_fk_soc (fk_soc);
ALTER TABLE llx_webcams_webcam ADD INDEX idx_webcams_webcam_status (status);
ALTER TABLE llx_webcams_webcam ADD CONSTRAINT fk_webcams_webcam_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
ALTER TABLE llx_webcams_webcam ADD CONSTRAINT fk_webcams_webcam_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
