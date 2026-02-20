-- Phase 1e: Hardening schema additions
-- Add read_at column to notifications (if not already done by earlier migration)
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS read_at TIMESTAMPTZ;

-- Add missing tables referenced in code
CREATE TABLE IF NOT EXISTS override_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  counterparty_id UUID NOT NULL REFERENCES counterparties(id) ON DELETE CASCADE,
  contract_title TEXT NOT NULL,
  requested_by_email TEXT NOT NULL,
  reason TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  decided_by TEXT,
  decided_at TIMESTAMPTZ,
  comment TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS counterparty_merges (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  source_counterparty_id UUID NOT NULL,
  target_counterparty_id UUID NOT NULL REFERENCES counterparties(id),
  merged_by TEXT NOT NULL,
  merged_by_email TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  email TEXT UNIQUE,
  name TEXT,
  roles TEXT[] DEFAULT '{}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_override_requests_status ON override_requests(status) WHERE status = 'pending';
CREATE INDEX IF NOT EXISTS idx_notifications_read_at ON notifications(read_at) WHERE read_at IS NULL;
