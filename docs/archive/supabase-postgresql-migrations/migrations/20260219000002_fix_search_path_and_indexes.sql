-- Fix function search_path security warnings
ALTER FUNCTION public.prevent_audit_modification() SET search_path = public;
ALTER FUNCTION public.prevent_final_contract_delete() SET search_path = public;
ALTER FUNCTION public.update_updated_at_column() SET search_path = public;
ALTER FUNCTION public.contracts_search_trigger() SET search_path = public;

-- Add missing foreign key indexes for performance
CREATE INDEX IF NOT EXISTS idx_contracts_entity_id ON contracts(entity_id);
CREATE INDEX IF NOT EXISTS idx_contracts_project_id ON contracts(project_id);
CREATE INDEX IF NOT EXISTS idx_counterparty_contacts_counterparty_id ON counterparty_contacts(counterparty_id);
CREATE INDEX IF NOT EXISTS idx_counterparty_merges_target ON counterparty_merges(target_counterparty_id);
CREATE INDEX IF NOT EXISTS idx_escalation_events_rule_id ON escalation_events(rule_id);
CREATE INDEX IF NOT EXISTS idx_merchant_agreement_inputs_contract_id ON merchant_agreement_inputs(contract_id);
CREATE INDEX IF NOT EXISTS idx_merchant_agreement_inputs_template_id ON merchant_agreement_inputs(template_id);
CREATE INDEX IF NOT EXISTS idx_obligations_register_analysis_id ON obligations_register(analysis_id);
CREATE INDEX IF NOT EXISTS idx_override_requests_counterparty_id ON override_requests(counterparty_id);
CREATE INDEX IF NOT EXISTS idx_reminders_key_date_id ON reminders(key_date_id);
CREATE INDEX IF NOT EXISTS idx_signing_authority_project_id ON signing_authority(project_id);
CREATE INDEX IF NOT EXISTS idx_workflow_templates_entity_id ON workflow_templates(entity_id);
CREATE INDEX IF NOT EXISTS idx_workflow_templates_project_id ON workflow_templates(project_id);
CREATE INDEX IF NOT EXISTS idx_workflow_templates_region_id ON workflow_templates(region_id);
