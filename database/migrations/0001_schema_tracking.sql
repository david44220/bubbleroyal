-- Core migration tracking. The runner applies numbered migrations in order.
CREATE TABLE IF NOT EXISTS br_schema_migrations (
    version VARCHAR(128) NOT NULL,
    applied_at DATETIME(6) NOT NULL,
    PRIMARY KEY (version)
) ENGINE=InnoDB;
