-- Override request table for blocked counterparty contract creation
CREATE TABLE IF NOT EXISTS override_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    counterparty_id UUID NOT NULL REFERENCES counterparties(id),
    contract_title TEXT NOT NULL,
    requested_by TEXT NOT NULL,
    requested_by_email TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    decided_by TEXT,
    decided_by_email TEXT,
    decision_comment TEXT,
    decided_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_override_requests_status ON override_requests(status) WHERE status = 'pending';
CREATE INDEX IF NOT EXISTS idx_override_requests_counterparty ON override_requests(counterparty_id);
