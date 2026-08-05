-- Bubble Royale phase 16: sandbox payment intents, webhook events and reconciliation.
-- This schema has no bridge to virtual wallets or cash settlement.

CREATE TABLE IF NOT EXISTS br_sandbox_payment_intents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_intent_id VARCHAR(191) NOT NULL,
    idempotency_key VARCHAR(191) NOT NULL,
    player_id VARCHAR(128) NOT NULL,
    currency CHAR(3) NOT NULL,
    sandbox_minor_units BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL,
    provider VARCHAR(64) NOT NULL DEFAULT 'sandbox',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_sandbox_provider_intent (provider_intent_id),
    UNIQUE KEY uq_br_sandbox_idempotency (idempotency_key),
    KEY idx_br_sandbox_player_status (player_id, status),
    CONSTRAINT chk_br_sandbox_minor_units_positive CHECK (sandbox_minor_units > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_sandbox_payment_webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(191) NOT NULL,
    provider_intent_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    payload JSON NOT NULL,
    signature_verified TINYINT(1) NOT NULL,
    received_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_sandbox_webhook_event (event_id),
    KEY idx_br_sandbox_webhook_intent (provider_intent_id)
) ENGINE=InnoDB;
