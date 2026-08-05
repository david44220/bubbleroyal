-- Bubble Royale phase 15: admin audit events and kill switches.

CREATE TABLE IF NOT EXISTS br_admin_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    audit_id CHAR(64) NOT NULL,
    event_id VARCHAR(191) NOT NULL,
    actor_id VARCHAR(191) NOT NULL,
    action VARCHAR(128) NOT NULL,
    resource VARCHAR(191) NOT NULL,
    metadata JSON NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_admin_audit_id (audit_id),
    UNIQUE KEY uq_br_admin_audit_event_id (event_id),
    KEY idx_br_admin_audit_action (action, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_kill_switches (
    flag_key VARCHAR(128) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(1000) NULL,
    updated_by VARCHAR(191) NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (flag_key)
) ENGINE=InnoDB;

-- Audit events must be append-only. Production roles must deny UPDATE and
-- DELETE on br_admin_audit_events.
