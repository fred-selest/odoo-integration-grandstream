-- Copyright (C) 2024 Your Company
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

-- Foreign keys for llx_grandstreamucm_calllog

ALTER TABLE llx_grandstreamucm_calllog ADD CONSTRAINT fk_grandstreamucm_calllog_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
ALTER TABLE llx_grandstreamucm_calllog ADD CONSTRAINT fk_grandstreamucm_calllog_fk_socpeople FOREIGN KEY (fk_socpeople) REFERENCES llx_socpeople (rowid);
ALTER TABLE llx_grandstreamucm_calllog ADD CONSTRAINT fk_grandstreamucm_calllog_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user (rowid);
ALTER TABLE llx_grandstreamucm_calllog ADD CONSTRAINT fk_grandstreamucm_calllog_fk_user_modif FOREIGN KEY (fk_user_modif) REFERENCES llx_user (rowid);
