-- Copyright (C) 2026 ViewLapse
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_webcams_availability ADD UNIQUE INDEX uk_webcams_availability (fk_webcam, period);
ALTER TABLE llx_webcams_availability ADD INDEX idx_webcams_availability_soc (fk_soc, period);
