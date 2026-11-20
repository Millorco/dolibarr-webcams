-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_webcams_archive ADD UNIQUE INDEX uk_webcams_archive (fk_webcam, fk_soc, rental_end);
ALTER TABLE llx_webcams_archive ADD INDEX idx_webcams_archive_until (archive_until);
