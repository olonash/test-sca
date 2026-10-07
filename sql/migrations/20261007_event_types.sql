USE scal_e_cdp;

CREATE TABLE IF NOT EXISTS event_types (
    id SMALLINT NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    label VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO event_types (code, label)
SELECT DISTINCT event_name, event_name
FROM events
ON DUPLICATE KEY UPDATE label = VALUES(label);

ALTER TABLE events ADD COLUMN event_type_id SMALLINT NULL AFTER customer_id;

UPDATE events e
INNER JOIN event_types et ON et.code = e.event_name
SET e.event_type_id = et.id;

ALTER TABLE events
    MODIFY COLUMN event_type_id SMALLINT NOT NULL,
    DROP INDEX idx_events_customer_event,
    DROP INDEX idx_events_name_time,
    DROP COLUMN event_name,
    ADD KEY idx_events_customer_type_time (customer_id, event_type_id, event_timestamp DESC),
    ADD KEY idx_events_type_time (event_type_id, event_timestamp DESC),
    ADD CONSTRAINT fk_events_event_type FOREIGN KEY (event_type_id) REFERENCES event_types(id);