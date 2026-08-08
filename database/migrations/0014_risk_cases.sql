-- Bubble Royale phase 14: anti-cheat, risk cases and manual review history.

CREATE TABLE IF NOT EXISTS br_risk_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    case_id CHAR(64) NOT NULL,
    subject_id VARCHAR(191) NOT NULL,
    source VARCHAR(64) NOT NULL,
    event_id VARCHAR(128) NOT NULL,
    status VARCHAR(32) NOT NULL,
    review_status VARCHAR(32) NOT NULL,
    risk_score SMALLINT UNSIGNED NOT NULL,
    severity VARCHAR(16) NOT NULL,
    factors JSON NOT NULL,
    first_seen_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_risk_case_id (case_id),
    UNIQUE KEY uq_br_risk_event_id (event_id),
    KEY idx_br_risk_subject_status (subject_id, status),
    CONSTRAINT chk_br_risk_score_range CHECK (risk_score <= 1000)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS br_risk_case_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    review_id CHAR(64) NOT NULL,
    case_id CHAR(64) NOT NULL,
    decision VARCHAR(32) NOT NULL,
    reviewer_id VARCHAR(191) NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    reviewed_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_br_risk_review_id (review_id),
    KEY idx_br_risk_review_case (case_id, reviewed_at)
) ENGINE=InnoDB;

-- Review history is append-only. Production database roles must deny UPDATE
-- and DELETE on br_risk_case_reviews.
