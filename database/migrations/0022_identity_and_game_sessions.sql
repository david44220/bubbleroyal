-- Production virtual-platform identity and one-time game-session boundaries.
-- No cash, withdrawal or payment entitlement is represented here.

CREATE TABLE IF NOT EXISTS br_users (
    id CHAR(32) NOT NULL,
    email VARCHAR(320) NOT NULL,
    email_normalized VARCHAR(320) NOT NULL,
    display_name VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'player',
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    locale VARCHAR(12) NOT NULL DEFAULT 'en',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    last_login_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_users_email (email_normalized),
    KEY idx_br_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_auth_sessions (
    session_id CHAR(32) NOT NULL,
    user_id CHAR(32) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    csrf_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    created_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    PRIMARY KEY (session_id),
    UNIQUE KEY uq_br_auth_session_token (token_hash),
    KEY idx_br_auth_session_user (user_id, revoked_at),
    KEY idx_br_auth_session_expiry (expires_at),
    CONSTRAINT fk_br_auth_session_user FOREIGN KEY (user_id) REFERENCES br_users (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_game_sessions (
    session_id CHAR(32) NOT NULL,
    player_id CHAR(32) NULL,
    token_hash CHAR(64) NOT NULL,
    mode VARCHAR(32) NOT NULL,
    value_type VARCHAR(32) NOT NULL,
    rules_version VARCHAR(64) NOT NULL,
    seed INT UNSIGNED NOT NULL,
    rows_count SMALLINT UNSIGNED NOT NULL,
    cols_count SMALLINT UNSIGNED NOT NULL,
    issued_at DATETIME(6) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_at DATETIME(6) NULL,
    PRIMARY KEY (session_id),
    UNIQUE KEY uq_br_game_session_token (token_hash),
    KEY idx_br_game_session_player (player_id, issued_at),
    KEY idx_br_game_session_expiry (expires_at),
    CONSTRAINT fk_br_game_session_player FOREIGN KEY (player_id) REFERENCES br_users (id)
) ENGINE=InnoDB;
