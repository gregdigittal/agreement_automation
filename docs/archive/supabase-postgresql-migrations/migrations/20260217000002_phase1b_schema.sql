-- =============================================================================
-- Phase 1b: Workflows, Signing, Amendments
-- =============================================================================

-- WikiContracts: template/precedent library (Epic 4/10)
CREATE TABLE IF NOT EXISTS wiki_contracts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  category TEXT,
  region_id UUID REFERENCES regions(id) ON DELETE SET NULL,
  version INTEGER NOT NULL DEFAULT 1,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'review', 'published', 'deprecated')),
  storage_path TEXT,
  file_name TEXT,
  description TEXT,
  created_by TEXT,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_wiki_contracts_status ON wiki_contracts(status);
CREATE INDEX IF NOT EXISTS idx_wiki_contracts_region ON wiki_contracts(region_id);

-- Workflow templates: versioned workflow definitions (Epic 4)
CREATE TABLE IF NOT EXISTS workflow_templates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  contract_type TEXT NOT NULL CHECK (contract_type IN ('Commercial', 'Merchant')),
  region_id UUID REFERENCES regions(id) ON DELETE SET NULL,
  entity_id UUID REFERENCES entities(id) ON DELETE SET NULL,
  project_id UUID REFERENCES projects(id) ON DELETE SET NULL,
  version INTEGER NOT NULL DEFAULT 1,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'deprecated')),
  stages JSONB NOT NULL DEFAULT '[]',
  validation_errors JSONB,
  created_by TEXT,
  published_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(name, version)
);

CREATE INDEX IF NOT EXISTS idx_workflow_templates_status ON workflow_templates(status);
CREATE INDEX IF NOT EXISTS idx_workflow_templates_contract_type ON workflow_templates(contract_type);

-- Workflow instances: active workflow bound to a contract (Epic 4)
CREATE TABLE IF NOT EXISTS workflow_instances (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  template_id UUID NOT NULL REFERENCES workflow_templates(id) ON DELETE RESTRICT,
  template_version INTEGER NOT NULL,
  current_stage TEXT NOT NULL,
  state TEXT NOT NULL DEFAULT 'active' CHECK (state IN ('active', 'completed', 'cancelled')),
  started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  completed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_workflow_instances_contract ON workflow_instances(contract_id) WHERE state = 'active';
CREATE INDEX IF NOT EXISTS idx_workflow_instances_state ON workflow_instances(state);

-- Workflow stage actions: approvals, rejections, rework (Epic 4)
CREATE TABLE IF NOT EXISTS workflow_stage_actions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  instance_id UUID NOT NULL REFERENCES workflow_instances(id) ON DELETE CASCADE,
  stage_name TEXT NOT NULL,
  action TEXT NOT NULL CHECK (action IN ('approve', 'reject', 'rework', 'skip')),
  actor_id TEXT,
  actor_email TEXT,
  comment TEXT,
  artifacts JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_stage_actions_instance ON workflow_stage_actions(instance_id);

-- Boldsign envelopes: signing tracking (Epic 9)
CREATE TABLE IF NOT EXISTS boldsign_envelopes (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  boldsign_document_id TEXT UNIQUE,
  status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'viewed', 'partially_signed', 'completed', 'declined', 'expired', 'voided')),
  signing_order TEXT NOT NULL DEFAULT 'sequential' CHECK (signing_order IN ('parallel', 'sequential')),
  signers JSONB NOT NULL DEFAULT '[]',
  webhook_payload JSONB,
  sent_at TIMESTAMPTZ,
  completed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_boldsign_contract ON boldsign_envelopes(contract_id);
CREATE INDEX IF NOT EXISTS idx_boldsign_document ON boldsign_envelopes(boldsign_document_id);

-- Contract links: amendments, renewals, side letters (Epic 15)
CREATE TABLE IF NOT EXISTS contract_links (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  parent_contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  child_contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  link_type TEXT NOT NULL CHECK (link_type IN ('amendment', 'renewal', 'side_letter', 'addendum')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE(parent_contract_id, child_contract_id)
);

CREATE INDEX IF NOT EXISTS idx_contract_links_parent ON contract_links(parent_contract_id);
CREATE INDEX IF NOT EXISTS idx_contract_links_child ON contract_links(child_contract_id);

-- Contract key dates: extracted or manual key dates (Epic 2 extended)
CREATE TABLE IF NOT EXISTS contract_key_dates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  date_type TEXT NOT NULL,
  date_value DATE NOT NULL,
  description TEXT,
  reminder_days INTEGER[],
  is_verified BOOLEAN DEFAULT false,
  verified_by TEXT,
  verified_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_key_dates_contract ON contract_key_dates(contract_id);
CREATE INDEX IF NOT EXISTS idx_key_dates_value ON contract_key_dates(date_value);

-- Merchant agreement structured inputs (Epic 10)
CREATE TABLE IF NOT EXISTS merchant_agreement_inputs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  template_id UUID REFERENCES wiki_contracts(id) ON DELETE SET NULL,
  vendor_name TEXT NOT NULL,
  merchant_fee TEXT,
  region_terms JSONB,
  generated_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Add SharePoint fields to contracts
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS sharepoint_url TEXT;
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS sharepoint_version TEXT;

-- Add parent_contract_id for quick amendment/renewal lookups
ALTER TABLE contracts ADD COLUMN IF NOT EXISTS parent_contract_id UUID REFERENCES contracts(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_contracts_parent ON contracts(parent_contract_id) WHERE parent_contract_id IS NOT NULL;

-- Apply updated_at triggers to new tables
DO $$
DECLARE
  t TEXT;
BEGIN
  FOR t IN SELECT unnest(ARRAY[
    'wiki_contracts', 'workflow_templates', 'workflow_instances',
    'boldsign_envelopes', 'contract_key_dates'
  ])
  LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS set_updated_at ON %I', t);
    EXECUTE format(
      'CREATE TRIGGER set_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION update_updated_at_column()',
      t
    );
  END LOOP;
END;
$$;
