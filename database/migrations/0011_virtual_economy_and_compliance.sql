-- Bubble Royale phases 11–13: virtual wallet, append-only virtual ledger and
-- configurable compliance/GeoFeature policy data.
-- MySQL 8.0+. No monetary or cash balance is represented by this migration.

CREATE TABLE IF NOT EXISTS br_virtual_wallet_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_key VARCHAR(191) NOT NULL,
    account_type VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_virtual_wallet_account_key (account_key),
    KEY idx_br_virtual_wallet_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_virtual_ledger_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id CHAR(64) NOT NULL,
    event_id VARCHAR(128) NOT NULL,
    account_key VARCHAR(191) NOT NULL,
    unit_type VARCHAR(64) NOT NULL,
    direction ENUM('credit', 'debit') NOT NULL,
    units BIGINT UNSIGNED NOT NULL,
    reference_type VARCHAR(64) NOT NULL,
    reference_id VARCHAR(191) NOT NULL,
    metadata JSON NOT NULL,
    created_at DATETIME(6) NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_virtual_ledger_entry_id (entry_id),
    KEY idx_br_virtual_ledger_event_id (event_id),
    KEY idx_br_virtual_ledger_account_unit (account_key, unit_type, created_at),
    CONSTRAINT chk_br_virtual_ledger_units_positive CHECK (units > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_virtual_prize_pools (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pool_key VARCHAR(191) NOT NULL,
    tournament_id VARCHAR(128) NOT NULL,
    unit_type VARCHAR(64) NOT NULL DEFAULT 'virtual_prize_unit',
    status VARCHAR(32) NOT NULL DEFAULT 'seeded',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_virtual_pool_key (pool_key),
    UNIQUE KEY uq_br_virtual_pool_tournament (tournament_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_geo_feature_rules (
    feature_key VARCHAR(128) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    environments JSON NOT NULL,
    allowed_countries JSON NOT NULL,
    blocked_countries JSON NOT NULL,
    minimum_age SMALLINT UNSIGNED NULL,
    requires_age_verification TINYINT(1) NOT NULL DEFAULT 1,
    requires_kyc TINYINT(1) NOT NULL DEFAULT 0,
    requires_kyb TINYINT(1) NOT NULL DEFAULT 0,
    maximum_risk_score SMALLINT UNSIGNED NULL,
    requires_responsible_play TINYINT(1) NOT NULL DEFAULT 1,
    policy_version VARCHAR(64) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (feature_key),
    CONSTRAINT chk_br_geo_feature_risk_range CHECK (maximum_risk_score IS NULL OR maximum_risk_score <= 1000)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_player_compliance_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id VARCHAR(128) NOT NULL,
    country_code CHAR(2) NOT NULL,
    region_code VARCHAR(32) NULL,
    age_years SMALLINT UNSIGNED NULL,
    age_verified TINYINT(1) NOT NULL DEFAULT 0,
    kyc_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
    kyb_status VARCHAR(32) NOT NULL DEFAULT 'not_applicable',
    risk_score SMALLINT UNSIGNED NULL,
    responsible_play_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
    cooling_off_until DATETIME(6) NULL,
    minutes_today INT UNSIGNED NOT NULL DEFAULT 0,
    sessions_today INT UNSIGNED NOT NULL DEFAULT 0,
    daily_minutes_limit INT UNSIGNED NULL,
    daily_sessions_limit INT UNSIGNED NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_player_compliance_player (player_id),
    KEY idx_br_player_compliance_country (country_code),
    CONSTRAINT chk_br_player_compliance_risk_range CHECK (risk_score IS NULL OR risk_score <= 1000)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_compliance_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    decision_id CHAR(64) NOT NULL,
    player_id VARCHAR(128) NULL,
    feature_key VARCHAR(128) NOT NULL,
    allowed TINYINT(1) NOT NULL,
    reasons JSON NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_compliance_decision_id (decision_id),
    KEY idx_br_compliance_decision_feature (feature_key, created_at)
) ENGINE=InnoDB;

-- The application/database role must deny UPDATE and DELETE on
-- br_virtual_ledger_entries and br_compliance_decisions. Append-only access is
-- part of the deployment checklist and must not be replaced by a mutable
-- balance column.
