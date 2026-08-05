CREATE TABLE alpha_access_grants (
    grant_id VARCHAR(191) NOT NULL,
    player_id VARCHAR(191) NOT NULL,
    environment VARCHAR(32) NOT NULL,
    status VARCHAR(32) NOT NULL,
    value_type VARCHAR(16) NOT NULL DEFAULT 'virtual',
    cash_mode TINYINT(1) NOT NULL DEFAULT 0,
    policy_version VARCHAR(64) NOT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (grant_id),
    UNIQUE KEY uq_alpha_player_environment (player_id, environment),
    CONSTRAINT chk_alpha_environment CHECK (environment IN ('local', 'sandbox', 'staging')),
    CONSTRAINT chk_alpha_status CHECK (status IN ('enabled', 'revoked')),
    CONSTRAINT chk_alpha_value_type CHECK (value_type = 'virtual'),
    CONSTRAINT chk_alpha_cash_mode CHECK (cash_mode = 0)
);
