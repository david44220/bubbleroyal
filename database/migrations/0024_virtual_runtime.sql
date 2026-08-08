-- Durable virtual competition state. All values remain virtual and integer-based.

CREATE TABLE IF NOT EXISTS br_virtual_progression_states (
    player_id CHAR(32) NOT NULL,
    state_json JSON NOT NULL,
    version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (player_id),
    CONSTRAINT fk_br_virtual_progression_player FOREIGN KEY (player_id) REFERENCES br_users (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_virtual_progression_events (
    event_id VARCHAR(128) NOT NULL,
    player_id CHAR(32) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (event_id),
    KEY idx_br_virtual_progression_event_player (player_id, created_at),
    CONSTRAINT fk_br_virtual_progression_event_player FOREIGN KEY (player_id) REFERENCES br_users (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_virtual_tournaments (
    tournament_id VARCHAR(128) NOT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(1000) NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    starts_at DATETIME(6) NOT NULL,
    ends_at DATETIME(6) NOT NULL,
    entry_type VARCHAR(32) NOT NULL,
    ticket_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mode VARCHAR(32) NOT NULL,
    value_type VARCHAR(32) NOT NULL DEFAULT 'virtual',
    cash_mode TINYINT(1) NOT NULL DEFAULT 0,
    rules_version VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (tournament_id),
    CONSTRAINT chk_br_virtual_tournament_cash CHECK (cash_mode = 0),
    CONSTRAINT chk_br_virtual_tournament_value CHECK (value_type = 'virtual'),
    CONSTRAINT chk_br_virtual_tournament_entry CHECK (ticket_cost = 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_virtual_tournament_entries (
    entry_id CHAR(64) NOT NULL,
    tournament_id VARCHAR(128) NOT NULL,
    player_id CHAR(32) NOT NULL,
    verified_session_id CHAR(32) NOT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 1,
    mode VARCHAR(32) NOT NULL,
    value_type VARCHAR(32) NOT NULL DEFAULT 'virtual',
    entry_type VARCHAR(32) NOT NULL DEFAULT 'free_virtual',
    ticket_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
    cash_mode TINYINT(1) NOT NULL DEFAULT 0,
    score BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL,
    best_combo INT UNSIGNED NOT NULL DEFAULT 0,
    shots_used INT UNSIGNED NOT NULL DEFAULT 0,
    replay_review VARCHAR(32) NOT NULL DEFAULT 'pending',
    verified_at DATETIME(6) NOT NULL,
    reviewed_by CHAR(32) NULL,
    reviewed_at DATETIME(6) NULL,
    review_id CHAR(64) NULL,
    PRIMARY KEY (entry_id),
    UNIQUE KEY uq_br_virtual_entry_player (tournament_id, player_id),
    UNIQUE KEY uq_br_virtual_entry_session (verified_session_id),
    KEY idx_br_virtual_entry_tournament_review (tournament_id, replay_review),
    CONSTRAINT fk_br_virtual_entry_tournament FOREIGN KEY (tournament_id) REFERENCES br_virtual_tournaments (tournament_id),
    CONSTRAINT fk_br_virtual_entry_player FOREIGN KEY (player_id) REFERENCES br_users (id),
    CONSTRAINT chk_br_virtual_entry_cash CHECK (cash_mode = 0),
    CONSTRAINT chk_br_virtual_entry_value CHECK (value_type = 'virtual'),
    CONSTRAINT chk_br_virtual_entry_cost CHECK (ticket_cost = 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_virtual_publications (
    publication_id CHAR(64) NOT NULL,
    tournament_id VARCHAR(128) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    publication_json JSON NOT NULL,
    published_at DATETIME(6) NOT NULL,
    PRIMARY KEY (publication_id),
    UNIQUE KEY uq_br_virtual_publication_tournament (tournament_id),
    CONSTRAINT fk_br_virtual_publication_tournament FOREIGN KEY (tournament_id) REFERENCES br_virtual_tournaments (tournament_id)
) ENGINE=InnoDB;
