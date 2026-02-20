-- =============================================================================
-- Phase 1c: AI Intelligence, Monitoring, Escalation, Reporting, Multi-Language
-- =============================================================================

-- AI analysis results per contract (Epic 3)
CREATE TABLE IF NOT EXISTS ai_analysis_results (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  analysis_type TEXT NOT NULL CHECK (analysis_type IN (
    'summary', 'extraction', 'risk', 'deviation', 'obligations'
  )),
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN (
    'pending', 'processing', 'completed', 'failed'
  )),
  result JSONB,
  evidence JSONB,
  confidence_score FLOAT,
  model_used TEXT,
  token_usage_input INTEGER,
  token_usage_output INTEGER,
  cost_usd DECIMAL(10, 6),
  processing_time_ms INTEGER,
  agent_budget_usd DECIMAL(10, 4),
  error_message TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_ai_analysis_contract ON ai_analysis_results(contract_id);
CREATE INDEX IF NOT EXISTS idx_ai_analysis_type ON ai_analysis_results(analysis_type);
CREATE INDEX IF NOT EXISTS idx_ai_analysis_status ON ai_analysis_results(status);

-- AI extracted fields per contract (Epic 3)
CREATE TABLE IF NOT EXISTS ai_extracted_fields (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  analysis_id UUID NOT NULL REFERENCES ai_analysis_results(id) ON DELETE CASCADE,
  field_name TEXT NOT NULL,
  field_value TEXT,
  evidence_clause TEXT,
  evidence_page INTEGER,
  confidence FLOAT,
  is_verified BOOLEAN DEFAULT false,
  verified_by TEXT,
  verified_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_extracted_fields_contract ON ai_extracted_fields(contract_id);
CREATE INDEX IF NOT EXISTS idx_extracted_fields_analysis ON ai_extracted_fields(analysis_id);

-- Obligations register (Epic 3)
CREATE TABLE IF NOT EXISTS obligations_register (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  analysis_id UUID REFERENCES ai_analysis_results(id) ON DELETE SET NULL,
  obligation_type TEXT NOT NULL CHECK (obligation_type IN (
    'reporting', 'sla', 'insurance', 'deliverable', 'payment', 'other'
  )),
  description TEXT NOT NULL,
  due_date DATE,
  recurrence TEXT CHECK (recurrence IN (
    'once', 'daily', 'weekly', 'monthly', 'quarterly', 'annually', NULL
  )),
  responsible_party TEXT,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN (
    'active', 'completed', 'waived', 'overdue'
  )),
  evidence_clause TEXT,
  confidence FLOAT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_obligations_contract ON obligations_register(contract_id);
CREATE INDEX IF NOT EXISTS idx_obligations_status ON obligations_register(status);
CREATE INDEX IF NOT EXISTS idx_obligations_due_date ON obligations_register(due_date);

-- Reminders (Epic 11)
CREATE TABLE IF NOT EXISTS reminders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  key_date_id UUID REFERENCES contract_key_dates(id) ON DELETE CASCADE,
  reminder_type TEXT NOT NULL CHECK (reminder_type IN (
    'expiry', 'renewal_notice', 'payment', 'sla', 'obligation', 'custom'
  )),
  lead_days INTEGER NOT NULL,
  channel TEXT NOT NULL DEFAULT 'email' CHECK (channel IN ('email', 'teams', 'calendar')),
  recipient_email TEXT,
  recipient_user_id TEXT,
  last_sent_at TIMESTAMPTZ,
  next_due_at TIMESTAMPTZ,
  is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_reminders_contract ON reminders(contract_id);
CREATE INDEX IF NOT EXISTS idx_reminders_next_due ON reminders(next_due_at) WHERE is_active = true;

-- Escalation rules (Epic 16)
CREATE TABLE IF NOT EXISTS escalation_rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  workflow_template_id UUID NOT NULL REFERENCES workflow_templates(id) ON DELETE CASCADE,
  stage_name TEXT NOT NULL,
  sla_breach_hours INTEGER NOT NULL,
  tier INTEGER NOT NULL DEFAULT 1,
  escalate_to_role TEXT,
  escalate_to_user_id TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_escalation_rules_template ON escalation_rules(workflow_template_id);

-- Escalation events (Epic 16)
CREATE TABLE IF NOT EXISTS escalation_events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  workflow_instance_id UUID NOT NULL REFERENCES workflow_instances(id) ON DELETE CASCADE,
  rule_id UUID NOT NULL REFERENCES escalation_rules(id) ON DELETE CASCADE,
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  stage_name TEXT NOT NULL,
  tier INTEGER NOT NULL,
  escalated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  resolved_at TIMESTAMPTZ,
  resolved_by TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_escalation_events_instance ON escalation_events(workflow_instance_id);
CREATE INDEX IF NOT EXISTS idx_escalation_events_unresolved ON escalation_events(contract_id) WHERE resolved_at IS NULL;

-- Notifications log (Epic 11/16)
CREATE TABLE IF NOT EXISTS notifications (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  recipient_email TEXT,
  recipient_user_id TEXT,
  channel TEXT NOT NULL DEFAULT 'email' CHECK (channel IN ('email', 'teams')),
  subject TEXT NOT NULL,
  body TEXT,
  related_resource_type TEXT,
  related_resource_id TEXT,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
  sent_at TIMESTAMPTZ,
  error_message TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_notifications_status ON notifications(status);
CREATE INDEX IF NOT EXISTS idx_notifications_recipient ON notifications(recipient_email);

-- Contract language versions (Epic 13)
CREATE TABLE IF NOT EXISTS contract_languages (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  contract_id UUID NOT NULL REFERENCES contracts(id) ON DELETE CASCADE,
  language_code TEXT NOT NULL,
  is_primary BOOLEAN NOT NULL DEFAULT false,
  storage_path TEXT,
  file_name TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_contract_languages_contract ON contract_languages(contract_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_contract_languages_unique ON contract_languages(contract_id, language_code);

-- Apply updated_at triggers to new tables
DO $$
DECLARE
  t TEXT;
BEGIN
  FOR t IN SELECT unnest(ARRAY[
    'ai_analysis_results', 'ai_extracted_fields', 'obligations_register',
    'reminders', 'escalation_rules'
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
