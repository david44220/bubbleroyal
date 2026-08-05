CREATE TABLE cash_readiness_attestations (
    readiness_id VARCHAR(191) NOT NULL,
    environment VARCHAR(32) NOT NULL,
    control_key VARCHAR(64) NOT NULL,
    status VARCHAR(32) NOT NULL,
    evidence_reference VARCHAR(191) NOT NULL,
    reviewer_id VARCHAR(191) NOT NULL,
    policy_version VARCHAR(64) NOT NULL,
    reviewed_at BIGINT NOT NULL,
    PRIMARY KEY (readiness_id),
    UNIQUE KEY uq_cash_readiness_control (environment, control_key, policy_version),
    CONSTRAINT chk_cash_readiness_environment CHECK (environment IN ('pilot', 'staging')),
    CONSTRAINT chk_cash_readiness_status CHECK (status IN ('pending', 'approved', 'rejected'))
);
