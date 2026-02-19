-- Track merged counterparties for redirect/traceability
CREATE TABLE IF NOT EXISTS counterparty_merges (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_counterparty_id UUID NOT NULL,
    target_counterparty_id UUID NOT NULL REFERENCES counterparties(id),
    merged_by TEXT NOT NULL,
    merged_by_email TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_counterparty_merges_source ON counterparty_merges(source_counterparty_id);
