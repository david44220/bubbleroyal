-- Bubble Royale phase 17: notifications outbox, support tickets and operations.

CREATE TABLE IF NOT EXISTS br_notification_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id CHAR(64) NOT NULL,
    dedupe_key VARCHAR(191) NOT NULL,
    recipient_id VARCHAR(191) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    template VARCHAR(191) NOT NULL,
    payload JSON NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_notification_id (notification_id),
    UNIQUE KEY uq_br_notification_dedupe (dedupe_key),
    KEY idx_br_notification_status (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_support_tickets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id CHAR(64) NOT NULL,
    player_id VARCHAR(191) NOT NULL,
    category VARCHAR(32) NOT NULL,
    subject VARCHAR(160) NOT NULL,
    status VARCHAR(32) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_support_ticket_id (ticket_id),
    KEY idx_br_support_ticket_player_status (player_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_support_ticket_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id CHAR(64) NOT NULL,
    author_id VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_br_support_message_ticket (ticket_id, created_at)
) ENGINE=InnoDB;
