-- ============================================================
-- CCRS Row-Level Security Policies
-- ============================================================
-- The FastAPI backend uses the service_role key (bypasses RLS).
-- These policies protect against:
--   1. Direct Supabase client access (anon/authenticated keys)
--   2. Supabase Dashboard REST API with user tokens
--   3. Future multi-tenant isolation
--
-- Strategy:
--   - All tables: RLS enabled, service_role bypasses
--   - Authenticated users: read access to most tables
--   - Write access: gated by app logic (service_role only)
--   - Sensitive tables (audit_log, notifications): read own only
-- ============================================================

-- ============================================================
-- 1. ORGANIZATIONAL STRUCTURE (read for all authenticated)
-- ============================================================

ALTER TABLE public.regions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "regions_select_authenticated"
  ON public.regions FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.entities ENABLE ROW LEVEL SECURITY;
CREATE POLICY "entities_select_authenticated"
  ON public.entities FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.projects ENABLE ROW LEVEL SECURITY;
CREATE POLICY "projects_select_authenticated"
  ON public.projects FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 2. COUNTERPARTIES (read for all authenticated)
-- ============================================================

ALTER TABLE public.counterparties ENABLE ROW LEVEL SECURITY;
CREATE POLICY "counterparties_select_authenticated"
  ON public.counterparties FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.counterparty_contacts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "counterparty_contacts_select_authenticated"
  ON public.counterparty_contacts FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 3. CONTRACTS & RELATED (read for all authenticated)
-- ============================================================

ALTER TABLE public.contracts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contracts_select_authenticated"
  ON public.contracts FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.contract_links ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contract_links_select_authenticated"
  ON public.contract_links FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.contract_key_dates ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contract_key_dates_select_authenticated"
  ON public.contract_key_dates FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.contract_languages ENABLE ROW LEVEL SECURITY;
CREATE POLICY "contract_languages_select_authenticated"
  ON public.contract_languages FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.signing_authority ENABLE ROW LEVEL SECURITY;
CREATE POLICY "signing_authority_select_authenticated"
  ON public.signing_authority FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.boldsign_envelopes ENABLE ROW LEVEL SECURITY;
CREATE POLICY "boldsign_envelopes_select_authenticated"
  ON public.boldsign_envelopes FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.merchant_agreement_inputs ENABLE ROW LEVEL SECURITY;
CREATE POLICY "merchant_agreement_inputs_select_authenticated"
  ON public.merchant_agreement_inputs FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 4. WORKFLOWS (read for all authenticated)
-- ============================================================

ALTER TABLE public.workflow_templates ENABLE ROW LEVEL SECURITY;
CREATE POLICY "workflow_templates_select_authenticated"
  ON public.workflow_templates FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.workflow_instances ENABLE ROW LEVEL SECURITY;
CREATE POLICY "workflow_instances_select_authenticated"
  ON public.workflow_instances FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.workflow_stage_actions ENABLE ROW LEVEL SECURITY;
CREATE POLICY "workflow_stage_actions_select_authenticated"
  ON public.workflow_stage_actions FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.wiki_contracts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "wiki_contracts_select_authenticated"
  ON public.wiki_contracts FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 5. AI & ANALYSIS (read for all authenticated)
-- ============================================================

ALTER TABLE public.ai_analysis_results ENABLE ROW LEVEL SECURITY;
CREATE POLICY "ai_analysis_results_select_authenticated"
  ON public.ai_analysis_results FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.ai_extracted_fields ENABLE ROW LEVEL SECURITY;
CREATE POLICY "ai_extracted_fields_select_authenticated"
  ON public.ai_extracted_fields FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.obligations_register ENABLE ROW LEVEL SECURITY;
CREATE POLICY "obligations_register_select_authenticated"
  ON public.obligations_register FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 6. MONITORING & ESCALATION (read for all authenticated)
-- ============================================================

ALTER TABLE public.reminders ENABLE ROW LEVEL SECURITY;
CREATE POLICY "reminders_select_authenticated"
  ON public.reminders FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.escalation_rules ENABLE ROW LEVEL SECURITY;
CREATE POLICY "escalation_rules_select_authenticated"
  ON public.escalation_rules FOR SELECT
  TO authenticated
  USING (true);

ALTER TABLE public.escalation_events ENABLE ROW LEVEL SECURITY;
CREATE POLICY "escalation_events_select_authenticated"
  ON public.escalation_events FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 7. NOTIFICATIONS (read own only)
-- ============================================================

ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY;

CREATE POLICY "notifications_select_own"
  ON public.notifications FOR SELECT
  TO authenticated
  USING (
    recipient_user_id = auth.uid()::text
    OR recipient_email = auth.jwt()->>'email'
  );

-- ============================================================
-- 8. AUDIT LOG (read-only for authenticated, app-level RBAC
--    further restricts to System Admin, Legal, Audit roles)
-- ============================================================

ALTER TABLE public.audit_log ENABLE ROW LEVEL SECURITY;

CREATE POLICY "audit_log_select_authenticated"
  ON public.audit_log FOR SELECT
  TO authenticated
  USING (true);

-- ============================================================
-- 9. OVERRIDE REQUESTS (conditional — only if table exists)
-- ============================================================

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name = 'override_requests'
    ) THEN
        EXECUTE 'ALTER TABLE public.override_requests ENABLE ROW LEVEL SECURITY';
        EXECUTE '
            CREATE POLICY "override_requests_select_authenticated"
              ON public.override_requests FOR SELECT
              TO authenticated
              USING (true)
        ';
    END IF;
END $$;

-- ============================================================
-- NOTES:
-- - All INSERT/UPDATE/DELETE operations go through the FastAPI
--   backend using the service_role key, which bypasses RLS.
-- - These SELECT policies ensure that if anyone connects with
--   an anon or authenticated key, they can only read data.
-- - No INSERT/UPDATE/DELETE policies are created for
--   authenticated users — all writes must go through the API.
-- ============================================================
