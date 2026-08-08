-- Runtime idempotency receipts. These records prevent a PHP worker race from
-- applying the same state-changing event twice.

CREATE TABLE IF NOT EXISTS br_runtime_event_receipts (
    event_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(128) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (event_id),
    KEY idx_br_runtime_receipt_type (event_type, created_at)
) ENGINE=InnoDB;

-- Existing append-only virtual entries intentionally keep multiple rows per
-- transfer event (debit + credit). The receipt table is the unique event
-- boundary; the ledger remains immutable and cash-disabled.
