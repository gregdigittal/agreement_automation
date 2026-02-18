-- =============================================================================
-- Rectification: Missing bucket, indexes, constraints, audit protection
-- =============================================================================

-- 12.1 Create missing wiki-contracts storage bucket
INSERT INTO storage.buckets (id, name, public)
VALUES ('wiki-contracts', 'wiki-contracts', false)
ON CONFLICT (id) DO NOTHING;

-- 12.2 Add missing CHECK constraint on contracts.contract_type
ALTER TABLE contracts ADD CONSTRAINT chk_contracts_contract_type
  CHECK (contract_type IN ('Commercial', 'Merchant'));

-- 12.3 Fix channel mismatch: add 'calendar' to notifications
-- (reminders allows 'calendar' but notifications does not)
ALTER TABLE notifications DROP CONSTRAINT IF EXISTS notifications_channel_check;
ALTER TABLE notifications ADD CONSTRAINT notifications_channel_check
  CHECK (channel IN ('email', 'teams', 'calendar'));

-- 12.4 Missing indexes for commonly queried columns
CREATE INDEX IF NOT EXISTS idx_counterparties_status ON counterparties(status);
CREATE INDEX IF NOT EXISTS idx_counterparties_reg_number ON counterparties(registration_number) WHERE registration_number IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_entities_region_id ON entities(region_id);
CREATE INDEX IF NOT EXISTS idx_projects_entity_id ON projects(entity_id);
CREATE INDEX IF NOT EXISTS idx_workflow_instances_template ON workflow_instances(template_id);
CREATE INDEX IF NOT EXISTS idx_escalation_events_composite ON escalation_events(workflow_instance_id, rule_id) WHERE resolved_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_obligations_type ON obligations_register(obligation_type);

-- 12.5 Protect audit_log from modification (compliance)
CREATE OR REPLACE FUNCTION prevent_audit_modification()
RETURNS TRIGGER AS $$
BEGIN
  RAISE EXCEPTION 'Audit log records cannot be modified or deleted';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS prevent_audit_update ON audit_log;
CREATE TRIGGER prevent_audit_update
  BEFORE UPDATE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();

DROP TRIGGER IF EXISTS prevent_audit_delete ON audit_log;
CREATE TRIGGER prevent_audit_delete
  BEFORE DELETE ON audit_log
  FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();

-- 12.6 Prevent deletion of contracts in executed or archived state
CREATE OR REPLACE FUNCTION prevent_final_contract_delete()
RETURNS TRIGGER AS $$
BEGIN
  IF OLD.workflow_state IN ('executed', 'archived') THEN
    RAISE EXCEPTION 'Cannot delete contracts in executed or archived state';
  END IF;
  RETURN OLD;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS prevent_contract_delete ON contracts;
CREATE TRIGGER prevent_contract_delete
  BEFORE DELETE ON contracts
  FOR EACH ROW EXECUTE FUNCTION prevent_final_contract_delete();
