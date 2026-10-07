CREATE DATABASE IF NOT EXISTS scal_e_cdp;
USE scal_e_cdp;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_types (
    id SMALLINT NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    label VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id BIGINT UNSIGNED NOT NULL,
    event_type_id SMALLINT NOT NULL,
    event_timestamp DATETIME(6) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_customer_time (customer_id, event_timestamp DESC),
    KEY idx_events_customer_type_time (customer_id, event_type_id, event_timestamp DESC),
    KEY idx_events_type_time (event_type_id, event_timestamp DESC),
    CONSTRAINT fk_events_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_event_type FOREIGN KEY (event_type_id) REFERENCES event_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_properties (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,
    property_name VARCHAR(64) NOT NULL,
    property_type ENUM('string', 'int', 'float', 'bool', 'json') NOT NULL,
    value_string VARCHAR(255) DEFAULT NULL,
    value_int BIGINT DEFAULT NULL,
    value_decimal DECIMAL(18,4) DEFAULT NULL,
    value_bool TINYINT(1) DEFAULT NULL,
    value_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event_properties_event_name (event_id, property_name),
    KEY idx_event_properties_lookup_string (property_name, value_string),
    KEY idx_event_properties_lookup_int (property_name, value_int),
    KEY idx_event_properties_lookup_decimal (property_name, value_decimal),
    CONSTRAINT fk_event_properties_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A noter : les requêtes de segmentation utilisent des filtres sur des colonnes indexées spécifiques
-- pour éviter les scans complets sur les tables volumineuses. Cette structure est plus performante
-- qu'une colonne JSON brute dans le cas d'un moteur de recherche institutionnel sur des propriétés.
