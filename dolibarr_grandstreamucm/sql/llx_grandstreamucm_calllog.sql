-- Copyright (C) 2024 Your Company
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

CREATE TABLE llx_grandstreamucm_calllog (
    rowid               integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    entity              integer DEFAULT 1 NOT NULL,
    ref                 varchar(128) NOT NULL,
    call_id             varchar(128) NOT NULL,
    call_date           datetime NOT NULL,
    caller_number       varchar(64),
    caller_name         varchar(255),
    called_number       varchar(64),
    called_name         varchar(255),
    direction           varchar(32) NOT NULL,
    call_type           varchar(32) NOT NULL,
    duration            integer DEFAULT 0,
    talk_duration       integer DEFAULT 0,
    fk_soc              integer,
    fk_socpeople        integer,
    extension           varchar(32),
    trunk               varchar(64),
    disposition         varchar(64),
    recording_file      varchar(255),
    has_recording       smallint DEFAULT 0,
    note_private        text,
    note_public         text,
    date_creation       datetime NOT NULL,
    tms                 timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat       integer NOT NULL,
    fk_user_modif       integer,
    import_key          varchar(14),
    status              integer DEFAULT 1 NOT NULL
) ENGINE=innodb;

-- Indexes
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_call_id (call_id);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_call_date (call_date);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_caller_number (caller_number);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_called_number (called_number);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_fk_soc (fk_soc);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_fk_socpeople (fk_socpeople);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_direction (direction);
ALTER TABLE llx_grandstreamucm_calllog ADD INDEX idx_grandstreamucm_calllog_call_type (call_type);
ALTER TABLE llx_grandstreamucm_calllog ADD UNIQUE INDEX uk_grandstreamucm_calllog_call_id (call_id, entity);
