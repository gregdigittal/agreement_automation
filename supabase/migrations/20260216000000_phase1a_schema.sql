-- CCRS Phase 1a schema: regions, entities, projects, counterparties, contracts, audit_log
-- Run in Supabase SQL Editor or via supabase db push

-- Regions (top-level org)
CREATE TABLE IF NOT EXISTS regions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  code TEXT UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Entities (companies / legal entities) belong to a region
CREATE TABLE IF NOT EXISTS entities (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  region_id UUID NOT NULL REFERENCES regions(id) ON DELETE RESTRICT,
  name TEXT NOT NULL,
  code TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(region_id, code)
);

-- Projects belong to an entity
CREATE TABLE IF NOT EXISTS projects (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES entities(id) ON DELETE RESTRICT,
  name TEXT NOT NULL,
  code TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(entity_id, code)
);

-- Counterparties (external parties to contracts)
CREATE TABLE IF NOT EXISTS counterparties (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  legal_name TEXT NOT NULL,
  registration_number TEXT,
  address TEXT,
  jurisdiction TEXT,
  status TEXT NOT NULL DEFAULT 'Active' CHECK (status IN ('Active', 'Suspended', 'Blacklisted')),
  status_reason TEXT,
  status_changed_at TIMESTAMPTZ,
  status_changed_by TEXT,
  preferred_language TEXT DEFAULT 'en',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Counterparty contacts/signatories
CREATE TABLE IF NOT EXISTS counterparty_contacts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  counterparty_id UUID NOT NULL REFERENCES counterparties(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  email TEXT,
  role TEXT,
  is_signer BOOLEAN DEFAULT false,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Contract classification and metadata (documents in Supabase Storage)
CREATE TABLE IF NOT EXISTS contracts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  region_id UUID NOT NULL REFERENCES regions(id) ON DELETE RESTRICT,
  entity_id UUID NOT NULL REFERENCES entities(id) ON DELETE RESTRICT,
  project_id UUID NOT NULL REFERENCES projects(id) ON DELETE RESTRICT,
  counterparty_id UUID NOT NULL REFERENCES counterparties(id) ON DELETE RESTRICT,
  contract_type TEXT NOT NULL, -- e.g. 'Commercial', 'Merchant'
  title TEXT,
  workflow_state TEXT NOT NULL DEFAULT 'draft',
  signing_status TEXT,
  storage_path TEXT, -- path in Supabase Storage (bucket/object key)
  file_name TEXT,
  file_version INTEGER DEFAULT 1,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  created_by TEXT,
  updated_by TEXT
);

CREATE INDEX IF NOT EXISTS idx_contracts_region_entity_project ON contracts(region_id, entity_id, project_id);
CREATE INDEX IF NOT EXISTS idx_contracts_counterparty ON contracts(counterparty_id);
CREATE INDEX IF NOT EXISTS idx_contracts_workflow_state ON contracts(workflow_state);
CREATE INDEX IF NOT EXISTS idx_contracts_created_at ON contracts(created_at DESC);

-- Full-text search on contracts (title + metadata)
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS search_vector tsvector;
CREATE INDEX IF NOT EXISTS idx_contracts_search ON contracts USING GIN(search_vector);

CREATE OR REPLACE FUNCTION contracts_search_trigger() RETURNS trigger AS $$
BEGIN
  NEW.search_vector :=
    setweight(to_tsvector('english', coalesce(NEW.title, '')), 'A') ||
    setweight(to_tsvector('english', coalesce(NEW.contract_type, '')), 'B');
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS contracts_search_update ON contracts;
CREATE TRIGGER contracts_search_update
  BEFORE INSERT OR UPDATE ON contracts
  FOR EACH ROW EXECUTE PROCEDURE contracts_search_trigger();

-- Audit log (Phase 1a: Epic 14)
CREATE TABLE IF NOT EXISTS audit_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  at TIMESTAMPTZ NOT NULL DEFAULT now(),
  actor_id TEXT,
  actor_email TEXT,
  action TEXT NOT NULL,
  resource_type TEXT NOT NULL,
  resource_id TEXT,
  details JSONB,
  ip_address TEXT
);

CREATE INDEX IF NOT EXISTS idx_audit_log_at ON audit_log(at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_log_resource ON audit_log(resource_type, resource_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_actor ON audit_log(actor_id);

-- Signing authority (Phase 1a: Epic 7 – basic table; thresholds can be added later)
CREATE TABLE IF NOT EXISTS signing_authority (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_id UUID NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
  project_id UUID REFERENCES projects(id) ON DELETE CASCADE,
  user_id TEXT NOT NULL,
  user_email TEXT,
  role_or_name TEXT NOT NULL,
  contract_type_pattern TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_signing_authority_entity ON signing_authority(entity_id);

-- Storage bucket for contract files (private; API uses service_role to read/write)
INSERT INTO storage.buckets (id, name, public)
VALUES ('contracts', 'contracts', false)
ON CONFLICT (id) DO NOTHING;
