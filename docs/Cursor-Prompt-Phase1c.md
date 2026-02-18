# Cursor Prompt — CCRS Phase 1c Implementation

**Copy everything below this line into Cursor as the prompt.**

---

## Context

You are working on the CCRS (Contract & Merchant Agreement Repository System) project:
- **`apps/api`** — Python FastAPI backend (Phase 1a + 1b complete — ~60+ endpoints, Supabase, structured logging, RBAC, workflow engine, Boldsign integration, WikiContracts, key dates, merchant agreements)
- **`apps/web`** — Next.js 16 frontend (React 19, TypeScript, shadcn/ui, NextAuth v5, React Flow workflow builder)
- **`supabase/migrations/`** — PostgreSQL schema (3 migrations applied: Phase 1a schema, Phase 1a fixes, Phase 1b schema)

Reference documents:
- Full build plan: `docs/CCRS-Phased-Build-Plan-Remaining.md`
- Requirements: `CCRS Requirements v3 Board Edition 4.docx`
- Phase 1b prompt (for patterns): `docs/Cursor-Prompt-Phase1a-fix-and-1b.md`

**Key existing patterns to follow:**
- Backend modules: `app/{module}/__init__.py`, `router.py`, `service.py`, `schemas.py`
- Auth: `get_current_user` dependency returns `CurrentUser(id, email, roles, ip_address)`
- RBAC: `require_roles("System Admin", "Legal", ...)` dependency
- DB: `get_supabase()` returns `supabase.Client` — use `.table().select/insert/update/delete().execute()`
- Audit: `await audit_log(supabase, action=..., resource_type=..., resource_id=..., details=..., actor=user)`
- Config: `app/config.py` uses `pydantic_settings.BaseSettings` with `.env` file
- Frontend proxy: `apps/web/src/app/api/ccrs/[...path]/route.ts` forwards all `/api/ccrs/*` to FastAPI

## Instructions

Implement all sections below in order. After each section, verify compilation:
- Backend: `cd apps/api && python -m py_compile app/main.py` (or `pytest tests/ -v`)
- Frontend: `cd apps/web && npm run build`

Run tests after each backend section. Run `npm run build` after each frontend section.

---

# PART A: PHASE 1c DATABASE SCHEMA

Create migration `supabase/migrations/20260217000003_phase1c_schema.sql`:

```sql
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
```

---

# PART B: PHASE 1c BACKEND — AI CONTRACT INTELLIGENCE (Epic 3)

## B1. Add dependencies

**Append to** `apps/api/requirements.txt`:

```
anthropic>=0.52.0
```

## B2. Add AI config settings

**File:** `apps/api/app/config.py`

Add these fields to the `Settings` class:

```python
# AI / Anthropic
anthropic_api_key: str | None = None
ai_model: str = "claude-sonnet-4-5-20250929"
ai_agent_model: str = "claude-sonnet-4-5-20250929"
ai_max_budget_usd: float = 5.0
ai_analysis_timeout: int = 120

# Notifications
sendgrid_api_key: str | None = None
notification_from_email: str = "noreply@ccrs.digittal.com"

# Background tasks
scheduler_enabled: bool = True
```

## B3. AI core package

**Create** `apps/api/app/ai/` with `__init__.py`, `config.py`, `messages_client.py`, `agent_client.py`, `mcp_tools.py`, `schemas.py`.

### B3.1 `app/ai/config.py`
```python
from app.config import settings

# Task routing thresholds
SIMPLE_TASKS = {"summary"}
COMPLEX_TASKS = {"extraction", "risk", "deviation", "obligations"}

def get_task_type(analysis_type: str) -> str:
    """Return 'simple' for Messages API, 'complex' for Agent SDK."""
    if analysis_type in SIMPLE_TASKS:
        return "simple"
    return "complex"
```

### B3.2 `app/ai/schemas.py`
```python
from pydantic import BaseModel

class ExtractionField(BaseModel):
    field_name: str
    field_value: str | None = None
    evidence_clause: str | None = None
    evidence_page: int | None = None
    confidence: float = 0.0

class RiskItem(BaseModel):
    category: str
    description: str
    severity: str  # low, medium, high, critical
    evidence_clause: str | None = None
    recommendation: str | None = None

class DeviationItem(BaseModel):
    clause_reference: str
    template_text: str | None = None
    contract_text: str
    deviation_type: str  # missing, modified, added
    risk_level: str  # low, medium, high

class ObligationItem(BaseModel):
    obligation_type: str
    description: str
    due_date: str | None = None
    recurrence: str | None = None
    responsible_party: str | None = None
    evidence_clause: str | None = None
    confidence: float = 0.0

class SummaryResult(BaseModel):
    summary: str
    key_parties: list[str] = []
    contract_type_detected: str | None = None
    effective_date: str | None = None
    expiry_date: str | None = None
    total_value: str | None = None
    governing_law: str | None = None
    language_detected: str | None = None

class ExtractionResult(BaseModel):
    fields: list[ExtractionField] = []

class RiskResult(BaseModel):
    overall_risk_score: float = 0.0
    risks: list[RiskItem] = []

class DeviationResult(BaseModel):
    template_name: str | None = None
    deviations: list[DeviationItem] = []

class ObligationsResult(BaseModel):
    obligations: list[ObligationItem] = []

class AnalysisUsage(BaseModel):
    input_tokens: int = 0
    output_tokens: int = 0
    cost_usd: float = 0.0
    processing_time_ms: int = 0
    model_used: str = ""
```

### B3.3 `app/ai/messages_client.py`

Simple tasks (summary) via the Anthropic Messages API:

```python
import time
import anthropic
import structlog
from app.config import settings
from app.ai.schemas import SummaryResult, AnalysisUsage

logger = structlog.get_logger()

SUMMARY_SYSTEM_PROMPT = """You are a contract analysis assistant for the CCRS system.
Analyze the provided contract text and return a structured JSON summary.
Extract: summary (2-3 sentences), key_parties, contract_type_detected,
effective_date (ISO format or null), expiry_date (ISO format or null),
total_value, governing_law, language_detected.
Return ONLY valid JSON matching this schema — no markdown, no code fences."""


async def analyze_summary(contract_text: str) -> tuple[SummaryResult, AnalysisUsage]:
    """Run a simple summary extraction via Messages API."""
    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)
    start = time.monotonic()

    response = client.messages.create(
        model=settings.ai_model,
        max_tokens=2048,
        system=SUMMARY_SYSTEM_PROMPT,
        messages=[{"role": "user", "content": contract_text[:100_000]}],
    )

    elapsed_ms = int((time.monotonic() - start) * 1000)
    usage = AnalysisUsage(
        input_tokens=response.usage.input_tokens,
        output_tokens=response.usage.output_tokens,
        cost_usd=_estimate_cost(response.usage.input_tokens, response.usage.output_tokens, settings.ai_model),
        processing_time_ms=elapsed_ms,
        model_used=settings.ai_model,
    )

    raw_text = response.content[0].text
    result = SummaryResult.model_validate_json(raw_text)
    return result, usage


def _estimate_cost(input_tokens: int, output_tokens: int, model: str) -> float:
    """Estimate USD cost based on model pricing."""
    # Sonnet 4.5 pricing: $3/M input, $15/M output
    if "sonnet" in model:
        return (input_tokens * 3.0 / 1_000_000) + (output_tokens * 15.0 / 1_000_000)
    # Haiku 4.5 pricing: $0.80/M input, $4/M output
    if "haiku" in model:
        return (input_tokens * 0.8 / 1_000_000) + (output_tokens * 4.0 / 1_000_000)
    # Opus 4.6 pricing: $15/M input, $75/M output
    if "opus" in model:
        return (input_tokens * 15.0 / 1_000_000) + (output_tokens * 75.0 / 1_000_000)
    return 0.0
```

### B3.4 `app/ai/agent_client.py`

Complex tasks (extraction, risk, deviation, obligations) via the Claude Agent SDK:

```python
import time
import json
import anthropic
import structlog
from app.config import settings
from app.ai.schemas import (
    ExtractionResult, RiskResult, DeviationResult, ObligationsResult,
    AnalysisUsage,
)
from app.ai.mcp_tools import get_tools

logger = structlog.get_logger()

EXTRACTION_SYSTEM_PROMPT = """You are a contract field extraction agent for the CCRS system.
Extract all key fields from the contract: parties, dates, values, payment terms,
termination clauses, governing law, dispute resolution, confidentiality terms,
indemnification, liability caps, insurance requirements, IP ownership, warranties.
For each field, provide the extracted value, the evidence clause text, page number if
identifiable, and a confidence score (0.0-1.0).
Return ONLY valid JSON matching the ExtractionResult schema."""

RISK_SYSTEM_PROMPT = """You are a contract risk assessment agent for the CCRS system.
Analyze the contract for risks across categories: financial, legal, operational,
compliance, reputational, counterparty. For each risk, provide category, description,
severity (low/medium/high/critical), evidence clause, and recommendation.
Calculate an overall risk score (0.0-1.0).
Use the query_org_structure and query_authority_matrix tools to check if the contract
aligns with organizational policies.
Return ONLY valid JSON matching the RiskResult schema."""

DEVIATION_SYSTEM_PROMPT = """You are a contract template deviation agent for the CCRS system.
Compare the contract against the organization's standard template (retrieved via
query_wiki_contracts tool). Identify deviations: missing clauses, modified clauses,
and added non-standard clauses. For each deviation, provide clause reference, the
template text, the contract text, deviation type, and risk level.
Return ONLY valid JSON matching the DeviationResult schema."""

OBLIGATIONS_SYSTEM_PROMPT = """You are a contract obligations extraction agent for the CCRS system.
Extract all ongoing obligations: reporting requirements, SLA commitments, insurance
obligations, deliverables, payment schedules, and other recurring duties.
For each obligation, provide type, description, due date, recurrence pattern,
responsible party, evidence clause, and confidence score.
Return ONLY valid JSON matching the ObligationsResult schema."""

SYSTEM_PROMPTS = {
    "extraction": EXTRACTION_SYSTEM_PROMPT,
    "risk": RISK_SYSTEM_PROMPT,
    "deviation": DEVIATION_SYSTEM_PROMPT,
    "obligations": OBLIGATIONS_SYSTEM_PROMPT,
}

RESULT_MODELS = {
    "extraction": ExtractionResult,
    "risk": RiskResult,
    "deviation": DeviationResult,
    "obligations": ObligationsResult,
}


async def analyze_complex(
    analysis_type: str,
    contract_text: str,
    contract_id: str,
    supabase_client,
) -> tuple[dict, AnalysisUsage]:
    """Run a complex analysis via Anthropic Messages API with tool use."""
    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)
    tools = get_tools(supabase_client, contract_id)
    system_prompt = SYSTEM_PROMPTS[analysis_type]
    result_model = RESULT_MODELS[analysis_type]

    start = time.monotonic()
    total_input = 0
    total_output = 0

    messages = [{"role": "user", "content": contract_text[:100_000]}]

    # Agentic loop with tool use
    for _ in range(10):  # max 10 turns
        response = client.messages.create(
            model=settings.ai_agent_model,
            max_tokens=4096,
            system=system_prompt,
            tools=[t["definition"] for t in tools],
            messages=messages,
        )

        total_input += response.usage.input_tokens
        total_output += response.usage.output_tokens

        # Check if we hit budget
        current_cost = _estimate_cost(total_input, total_output, settings.ai_agent_model)
        if current_cost > settings.ai_max_budget_usd:
            logger.warning("ai_budget_exceeded", cost=current_cost, budget=settings.ai_max_budget_usd)
            break

        # If model wants to use tools
        if response.stop_reason == "tool_use":
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    tool_fn = next((t["handler"] for t in tools if t["definition"]["name"] == block.name), None)
                    if tool_fn:
                        result = tool_fn(**block.input)
                        tool_results.append({
                            "type": "tool_result",
                            "tool_use_id": block.id,
                            "content": json.dumps(result) if isinstance(result, (dict, list)) else str(result),
                        })

            messages.append({"role": "assistant", "content": response.content})
            messages.append({"role": "user", "content": tool_results})
            continue

        # Model finished — extract text result
        text_content = next((b.text for b in response.content if hasattr(b, "text")), None)
        if text_content:
            elapsed_ms = int((time.monotonic() - start) * 1000)
            usage = AnalysisUsage(
                input_tokens=total_input,
                output_tokens=total_output,
                cost_usd=_estimate_cost(total_input, total_output, settings.ai_agent_model),
                processing_time_ms=elapsed_ms,
                model_used=settings.ai_agent_model,
            )
            parsed = result_model.model_validate_json(text_content)
            return parsed.model_dump(), usage

        break

    # Fallback if loop completes without result
    elapsed_ms = int((time.monotonic() - start) * 1000)
    usage = AnalysisUsage(
        input_tokens=total_input,
        output_tokens=total_output,
        cost_usd=_estimate_cost(total_input, total_output, settings.ai_agent_model),
        processing_time_ms=elapsed_ms,
        model_used=settings.ai_agent_model,
    )
    return {}, usage


def _estimate_cost(input_tokens: int, output_tokens: int, model: str) -> float:
    if "sonnet" in model:
        return (input_tokens * 3.0 / 1_000_000) + (output_tokens * 15.0 / 1_000_000)
    if "haiku" in model:
        return (input_tokens * 0.8 / 1_000_000) + (output_tokens * 4.0 / 1_000_000)
    if "opus" in model:
        return (input_tokens * 15.0 / 1_000_000) + (output_tokens * 75.0 / 1_000_000)
    return 0.0
```

### B3.5 `app/ai/mcp_tools.py`

MCP-style tools that the AI agent can call to query CCRS data:

```python
import structlog
from supabase import Client

logger = structlog.get_logger()


def get_tools(supabase: Client, contract_id: str) -> list[dict]:
    """Return tool definitions and handlers for the AI agent."""
    return [
        {
            "definition": {
                "name": "query_org_structure",
                "description": "Query the CCRS organizational structure: regions, entities, projects. Use this to understand which entity owns the contract and what region it belongs to.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "region_id": {"type": "string", "description": "Filter by region ID (optional)"},
                        "entity_id": {"type": "string", "description": "Filter by entity ID (optional)"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_org_structure(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_authority_matrix",
                "description": "Query signing authority rules to check who can approve or sign contracts for a given entity/project/amount.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "entity_id": {"type": "string", "description": "Filter by entity ID (optional)"},
                        "project_id": {"type": "string", "description": "Filter by project ID (optional)"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_authority_matrix(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_wiki_contracts",
                "description": "Search the WikiContracts template library for standard templates and precedents. Use this to compare the contract against organizational standards.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "category": {"type": "string", "description": "Template category (optional)"},
                        "region_id": {"type": "string", "description": "Filter by region (optional)"},
                        "status": {"type": "string", "description": "Template status, default 'published'"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_wiki_contracts(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_counterparty",
                "description": "Look up counterparty details including status, jurisdiction, and contacts for the contract's counterparty.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "counterparty_id": {"type": "string", "description": "The counterparty ID to look up"},
                    },
                    "required": ["counterparty_id"],
                },
            },
            "handler": lambda **kwargs: _query_counterparty(supabase, **kwargs),
        },
    ]


def _query_org_structure(supabase: Client, region_id: str | None = None, entity_id: str | None = None) -> dict:
    try:
        if entity_id:
            result = supabase.table("entities").select("*, regions(*)").eq("id", entity_id).execute()
            return {"entities": result.data}
        if region_id:
            result = supabase.table("entities").select("*, regions(*)").eq("region_id", region_id).execute()
            return {"entities": result.data}
        regions = supabase.table("regions").select("*").execute()
        entities = supabase.table("entities").select("*").limit(50).execute()
        return {"regions": regions.data, "entities": entities.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_org_structure", error=str(e))
        return {"error": str(e)}


def _query_authority_matrix(supabase: Client, entity_id: str | None = None, project_id: str | None = None) -> dict:
    try:
        query = supabase.table("signing_authority").select("*")
        if entity_id:
            query = query.eq("entity_id", entity_id)
        if project_id:
            query = query.eq("project_id", project_id)
        result = query.limit(50).execute()
        return {"signing_authority": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_authority_matrix", error=str(e))
        return {"error": str(e)}


def _query_wiki_contracts(supabase: Client, category: str | None = None, region_id: str | None = None, status: str = "published") -> dict:
    try:
        query = supabase.table("wiki_contracts").select("id, name, category, region_id, version, status, description").eq("status", status)
        if category:
            query = query.eq("category", category)
        if region_id:
            query = query.eq("region_id", region_id)
        result = query.limit(25).execute()
        return {"templates": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_wiki_contracts", error=str(e))
        return {"error": str(e)}


def _query_counterparty(supabase: Client, counterparty_id: str) -> dict:
    try:
        result = supabase.table("counterparties").select("*, counterparty_contacts(*)").eq("id", counterparty_id).single().execute()
        return {"counterparty": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_counterparty", error=str(e))
        return {"error": str(e)}
```

## B4. AI Analysis Module

**Create** `apps/api/app/ai_analysis/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

### B4.1 `app/ai_analysis/schemas.py`
```python
from typing import Literal
from uuid import UUID
from pydantic import BaseModel, Field


class TriggerAnalysisInput(BaseModel):
    analysis_type: Literal["summary", "extraction", "risk", "deviation", "obligations"] = Field(..., alias="analysisType")
    model_config = {"populate_by_name": True}


class VerifyFieldInput(BaseModel):
    is_verified: bool = True


class CorrectFieldInput(BaseModel):
    field_value: str
```

### B4.2 `app/ai_analysis/service.py`

```python
import structlog
from supabase import Client
from app.ai.config import get_task_type
from app.ai.messages_client import analyze_summary
from app.ai.agent_client import analyze_complex
from app.auth.models import CurrentUser
from app.audit.service import audit_log

logger = structlog.get_logger()


async def trigger_analysis(
    supabase: Client,
    contract_id: str,
    analysis_type: str,
    actor: CurrentUser,
) -> dict:
    """Trigger AI analysis on a contract. Downloads contract text, routes to appropriate AI client."""
    # Fetch contract to get storage_path
    contract = supabase.table("contracts").select("*").eq("id", contract_id).single().execute()
    if not contract.data:
        raise ValueError("Contract not found")

    storage_path = contract.data.get("storage_path")
    if not storage_path:
        raise ValueError("Contract has no uploaded file")

    # Create analysis record in pending state
    record = supabase.table("ai_analysis_results").insert({
        "contract_id": contract_id,
        "analysis_type": analysis_type,
        "status": "pending",
    }).execute()
    analysis_id = record.data[0]["id"]

    try:
        # Update to processing
        supabase.table("ai_analysis_results").update({"status": "processing"}).eq("id", analysis_id).execute()

        # Download contract text from Supabase Storage
        file_bytes = supabase.storage.from_("contracts").download(storage_path)
        contract_text = _extract_text(file_bytes, contract.data.get("file_name", ""))

        # Route to appropriate AI client
        task_type = get_task_type(analysis_type)

        if task_type == "simple":
            result, usage = await analyze_summary(contract_text)
            result_dict = result.model_dump()
        else:
            result_dict, usage = await analyze_complex(
                analysis_type, contract_text, contract_id, supabase
            )

        # Save results
        supabase.table("ai_analysis_results").update({
            "status": "completed",
            "result": result_dict,
            "model_used": usage.model_used,
            "token_usage_input": usage.input_tokens,
            "token_usage_output": usage.output_tokens,
            "cost_usd": usage.cost_usd,
            "processing_time_ms": usage.processing_time_ms,
            "confidence_score": result_dict.get("overall_risk_score") or result_dict.get("confidence", None),
        }).eq("id", analysis_id).execute()

        # If extraction, save individual fields
        if analysis_type == "extraction" and "fields" in result_dict:
            for field in result_dict["fields"]:
                supabase.table("ai_extracted_fields").insert({
                    "contract_id": contract_id,
                    "analysis_id": analysis_id,
                    **field,
                }).execute()

        # If obligations, save to register
        if analysis_type == "obligations" and "obligations" in result_dict:
            for obl in result_dict["obligations"]:
                supabase.table("obligations_register").insert({
                    "contract_id": contract_id,
                    "analysis_id": analysis_id,
                    **obl,
                }).execute()

        await audit_log(supabase, action="ai_analysis_completed", resource_type="contract",
                       resource_id=contract_id, details={"analysis_type": analysis_type, "cost_usd": usage.cost_usd}, actor=actor)

        return supabase.table("ai_analysis_results").select("*").eq("id", analysis_id).single().execute().data

    except Exception as e:
        logger.error("ai_analysis_failed", analysis_id=analysis_id, error=str(e))
        supabase.table("ai_analysis_results").update({
            "status": "failed",
            "error_message": str(e),
        }).eq("id", analysis_id).execute()
        raise


def _extract_text(file_bytes: bytes, file_name: str) -> str:
    """Extract text from PDF or DOCX bytes."""
    if file_name.lower().endswith(".pdf"):
        try:
            import fitz  # PyMuPDF
            doc = fitz.open(stream=file_bytes, filetype="pdf")
            return "\n".join(page.get_text() for page in doc)
        except ImportError:
            # Fallback: return raw bytes decoded
            return file_bytes.decode("utf-8", errors="ignore")
    if file_name.lower().endswith((".docx", ".doc")):
        try:
            import docx
            from io import BytesIO
            doc = docx.Document(BytesIO(file_bytes))
            return "\n".join(p.text for p in doc.paragraphs)
        except ImportError:
            return file_bytes.decode("utf-8", errors="ignore")
    return file_bytes.decode("utf-8", errors="ignore")


async def get_analyses(supabase: Client, contract_id: str) -> list[dict]:
    result = supabase.table("ai_analysis_results").select("*").eq("contract_id", contract_id).order("created_at", desc=True).execute()
    return result.data


async def get_extracted_fields(supabase: Client, contract_id: str) -> list[dict]:
    result = supabase.table("ai_extracted_fields").select("*").eq("contract_id", contract_id).order("field_name").execute()
    return result.data


async def verify_field(supabase: Client, field_id: str, actor: CurrentUser) -> dict | None:
    from datetime import datetime, timezone
    result = supabase.table("ai_extracted_fields").update({
        "is_verified": True,
        "verified_by": actor.id,
        "verified_at": datetime.now(timezone.utc).isoformat(),
    }).eq("id", field_id).execute()
    await audit_log(supabase, action="ai_field_verified", resource_type="ai_extracted_field",
                   resource_id=field_id, actor=actor)
    return result.data[0] if result.data else None


async def correct_field(supabase: Client, field_id: str, new_value: str, actor: CurrentUser) -> dict | None:
    result = supabase.table("ai_extracted_fields").update({
        "field_value": new_value,
        "is_verified": True,
        "verified_by": actor.id,
    }).eq("id", field_id).execute()
    await audit_log(supabase, action="ai_field_corrected", resource_type="ai_extracted_field",
                   resource_id=field_id, details={"new_value": new_value}, actor=actor)
    return result.data[0] if result.data else None
```

### B4.3 `app/ai_analysis/router.py`
```python
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.ai_analysis.schemas import TriggerAnalysisInput, VerifyFieldInput, CorrectFieldInput
from app.ai_analysis import service

router = APIRouter(tags=["ai_analysis"])


@router.post(
    "/contracts/{id}/analyze",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def trigger_analysis(
    id: UUID,
    body: TriggerAnalysisInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await service.trigger_analysis(supabase, str(id), body.analysis_type, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/contracts/{id}/analysis")
async def get_analysis(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    analyses = await service.get_analyses(supabase, str(id))
    fields = await service.get_extracted_fields(supabase, str(id))
    return {"analyses": analyses, "extracted_fields": fields}


@router.post(
    "/ai-fields/{field_id}/verify",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def verify_field(
    field_id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.verify_field(supabase, str(field_id), user)
    if not row:
        raise HTTPException(status_code=404, detail="Field not found")
    return row


@router.patch(
    "/ai-fields/{field_id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def correct_field(
    field_id: UUID,
    body: CorrectFieldInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.correct_field(supabase, str(field_id), body.field_value, user)
    if not row:
        raise HTTPException(status_code=404, detail="Field not found")
    return row
```

## B5. Obligations Module

**Create** `apps/api/app/obligations/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

### B5.1 `app/obligations/schemas.py`
```python
from datetime import date
from typing import Literal
from pydantic import BaseModel


class CreateObligationInput(BaseModel):
    obligation_type: Literal["reporting", "sla", "insurance", "deliverable", "payment", "other"]
    description: str
    due_date: date | None = None
    recurrence: Literal["once", "daily", "weekly", "monthly", "quarterly", "annually"] | None = None
    responsible_party: str | None = None
    evidence_clause: str | None = None


class UpdateObligationInput(BaseModel):
    description: str | None = None
    due_date: date | None = None
    recurrence: str | None = None
    responsible_party: str | None = None
    status: Literal["active", "completed", "waived", "overdue"] | None = None
```

### B5.2 `app/obligations/service.py`
```python
import structlog
from supabase import Client
from app.auth.models import CurrentUser
from app.audit.service import audit_log
from app.obligations.schemas import CreateObligationInput, UpdateObligationInput

logger = structlog.get_logger()


def list_obligations(supabase: Client, contract_id: str | None = None,
                     status: str | None = None, obligation_type: str | None = None,
                     limit: int = 50, offset: int = 0) -> tuple[list[dict], int]:
    query = supabase.table("obligations_register").select("*", count="exact")
    if contract_id:
        query = query.eq("contract_id", contract_id)
    if status:
        query = query.eq("status", status)
    if obligation_type:
        query = query.eq("obligation_type", obligation_type)
    result = query.order("due_date").range(offset, offset + limit - 1).execute()
    return result.data, result.count or 0


async def create_obligation(supabase: Client, contract_id: str, body: CreateObligationInput, actor: CurrentUser) -> dict:
    data = body.model_dump(exclude_none=True)
    data["contract_id"] = contract_id
    result = supabase.table("obligations_register").insert(data).execute()
    await audit_log(supabase, action="obligation_created", resource_type="obligation",
                   resource_id=result.data[0]["id"], details=data, actor=actor)
    return result.data[0]


async def update_obligation(supabase: Client, obligation_id: str, body: UpdateObligationInput, actor: CurrentUser) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("obligations_register").update(data).eq("id", obligation_id).execute()
    if result.data:
        await audit_log(supabase, action="obligation_updated", resource_type="obligation",
                       resource_id=obligation_id, details=data, actor=actor)
    return result.data[0] if result.data else None


async def delete_obligation(supabase: Client, obligation_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("obligations_register").delete().eq("id", obligation_id).execute()
    if result.data:
        await audit_log(supabase, action="obligation_deleted", resource_type="obligation",
                       resource_id=obligation_id, actor=actor)
    return bool(result.data)
```

### B5.3 `app/obligations/router.py`
```python
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.obligations.schemas import CreateObligationInput, UpdateObligationInput
from app.obligations import service

router = APIRouter(tags=["obligations"])


@router.get("/contracts/{id}/obligations")
async def list_contract_obligations(
    id: UUID,
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_obligations(supabase, str(id), status, obligation_type, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.get("/obligations")
async def list_all_obligations(
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_obligations(supabase, None, status, obligation_type, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.post(
    "/contracts/{id}/obligations",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    status_code=201,
)
async def create_obligation(
    id: UUID,
    body: CreateObligationInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_obligation(supabase, str(id), body, user)


@router.patch(
    "/obligations/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def update_obligation(
    id: UUID,
    body: UpdateObligationInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_obligation(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Obligation not found")
    return row


@router.delete(
    "/obligations/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def delete_obligation(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_obligation(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Obligation not found")
    return {"ok": True}
```

---

# PART C: PHASE 1c BACKEND — LLM WORKFLOW GENERATOR (Epic 6)

**Create** `apps/api/app/ai/workflow_generator.py`:

```python
import json
import anthropic
import structlog
from app.config import settings
from app.ai.mcp_tools import get_tools
from app.workflows.schemas import WorkflowStage
from app.workflows.state_machine import validate_template

logger = structlog.get_logger()

WORKFLOW_GEN_SYSTEM_PROMPT = """You are a workflow design agent for the CCRS system.
Given a natural language description of a desired contract workflow, generate a
workflow template definition as a JSON array of stages.

Each stage must have:
- name: unique identifier (snake_case)
- type: one of "draft", "review", "approval", "signing"
- description: human-readable description
- owners: list of role names who own this stage
- approvers: list of role names who can approve
- required_artifacts: list of artifact names needed
- allowed_transitions: list of stage names this can transition to
- sla_hours: max hours before SLA breach (null if none)

Rules:
1. First stage cannot be "signing" type
2. Must include at least one "approval" stage
3. Must include at least one "signing" stage
4. All allowed_transitions must reference valid stage names
5. Every stage must be reachable from the first stage
6. Use the query_org_structure tool to understand the organization
7. Use the query_authority_matrix tool to check signing authority rules
8. Use the query_wiki_contracts tool to check existing templates for reference

Return ONLY a JSON object with:
{
  "stages": [...],
  "explanation": "Brief explanation of each stage and why it was included",
  "confidence": 0.0-1.0
}"""


async def generate_workflow(
    description: str,
    region_id: str | None,
    entity_id: str | None,
    project_id: str | None,
    supabase_client,
) -> dict:
    """Generate a workflow template from natural language description."""
    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)
    tools = get_tools(supabase_client, contract_id="")

    user_message = f"""Generate a workflow for the following requirements:

Description: {description}
Region ID: {region_id or 'Not specified'}
Entity ID: {entity_id or 'Not specified'}
Project ID: {project_id or 'Not specified'}

Please query the organization structure and authority matrix to inform your design."""

    messages = [{"role": "user", "content": user_message}]
    total_input = 0
    total_output = 0

    for _ in range(10):
        response = client.messages.create(
            model=settings.ai_agent_model,
            max_tokens=4096,
            system=WORKFLOW_GEN_SYSTEM_PROMPT,
            tools=[t["definition"] for t in tools],
            messages=messages,
        )
        total_input += response.usage.input_tokens
        total_output += response.usage.output_tokens

        if response.stop_reason == "tool_use":
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    tool_fn = next((t["handler"] for t in tools if t["definition"]["name"] == block.name), None)
                    if tool_fn:
                        result = tool_fn(**block.input)
                        tool_results.append({
                            "type": "tool_result",
                            "tool_use_id": block.id,
                            "content": json.dumps(result) if isinstance(result, (dict, list)) else str(result),
                        })
            messages.append({"role": "assistant", "content": response.content})
            messages.append({"role": "user", "content": tool_results})
            continue

        text_content = next((b.text for b in response.content if hasattr(b, "text")), None)
        if text_content:
            parsed = json.loads(text_content)
            stages = [WorkflowStage(**s) for s in parsed.get("stages", [])]

            # Validate the generated template
            errors = validate_template(stages)

            # If invalid, ask model to self-correct (one retry)
            if errors:
                logger.info("ai_workflow_validation_failed", errors=errors)
                messages.append({"role": "assistant", "content": response.content})
                messages.append({"role": "user", "content": f"The generated workflow has validation errors: {errors}. Please fix these issues and return a corrected JSON."})
                # One more attempt
                retry = client.messages.create(
                    model=settings.ai_agent_model,
                    max_tokens=4096,
                    system=WORKFLOW_GEN_SYSTEM_PROMPT,
                    messages=messages,
                )
                total_input += retry.usage.input_tokens
                total_output += retry.usage.output_tokens
                retry_text = next((b.text for b in retry.content if hasattr(b, "text")), None)
                if retry_text:
                    parsed = json.loads(retry_text)
                    stages = [WorkflowStage(**s) for s in parsed.get("stages", [])]
                    errors = validate_template(stages)

            return {
                "stages": [s.model_dump() for s in stages],
                "explanation": parsed.get("explanation", ""),
                "confidence": parsed.get("confidence", 0.0),
                "validation_errors": errors,
                "usage": {
                    "input_tokens": total_input,
                    "output_tokens": total_output,
                },
            }
        break

    raise ValueError("Failed to generate workflow after maximum attempts")
```

**Add endpoint to** `apps/api/app/workflows/router.py`:

Add this import at the top and this endpoint to the existing workflows router:

```python
from app.ai.workflow_generator import generate_workflow

# Add this schema to workflows/schemas.py:
class GenerateWorkflowInput(BaseModel):
    description: str
    region_id: UUID | None = Field(None, alias="regionId")
    entity_id: UUID | None = Field(None, alias="entityId")
    project_id: UUID | None = Field(None, alias="projectId")
    model_config = {"populate_by_name": True}

# Add this endpoint to the router:
@router.post(
    "/workflows/generate",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def ai_generate_workflow(
    body: GenerateWorkflowInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = await generate_workflow(
        body.description,
        str(body.region_id) if body.region_id else None,
        str(body.entity_id) if body.entity_id else None,
        str(body.project_id) if body.project_id else None,
        supabase,
    )
    await audit_log(supabase, action="ai_workflow_generated", resource_type="workflow_template",
                   details={"description": body.description}, actor=user)
    return result
```

---

# PART D: PHASE 1c BACKEND — MONITORING, REMINDERS & ESCALATION (Epics 11, 16)

## D1. Reminders Module

**Create** `apps/api/app/reminders/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

### D1.1 `app/reminders/schemas.py`
```python
from typing import Literal
from uuid import UUID
from pydantic import BaseModel, Field


class CreateReminderInput(BaseModel):
    key_date_id: UUID | None = Field(None, alias="keyDateId")
    reminder_type: Literal["expiry", "renewal_notice", "payment", "sla", "obligation", "custom"]
    lead_days: int = Field(..., ge=1, le=365)
    channel: Literal["email", "teams", "calendar"] = "email"
    recipient_email: str | None = None
    recipient_user_id: str | None = None
    model_config = {"populate_by_name": True}


class UpdateReminderInput(BaseModel):
    lead_days: int | None = Field(None, ge=1, le=365)
    channel: Literal["email", "teams", "calendar"] | None = None
    recipient_email: str | None = None
    recipient_user_id: str | None = None
    is_active: bool | None = None
```

### D1.2 `app/reminders/service.py`
```python
import structlog
from datetime import datetime, timedelta, timezone
from supabase import Client
from app.auth.models import CurrentUser
from app.audit.service import audit_log
from app.reminders.schemas import CreateReminderInput, UpdateReminderInput

logger = structlog.get_logger()


def list_reminders(supabase: Client, contract_id: str) -> list[dict]:
    result = supabase.table("reminders").select("*").eq("contract_id", contract_id).order("next_due_at").execute()
    return result.data


async def create_reminder(supabase: Client, contract_id: str, body: CreateReminderInput, actor: CurrentUser) -> dict:
    data = body.model_dump(exclude_none=True)
    data["contract_id"] = contract_id

    # Calculate next_due_at from key date if provided
    if body.key_date_id:
        kd = supabase.table("contract_key_dates").select("date_value").eq("id", str(body.key_date_id)).single().execute()
        if kd.data:
            from datetime import date as date_type
            date_val = kd.data["date_value"]
            if isinstance(date_val, str):
                date_val = datetime.fromisoformat(date_val).date()
            next_due = datetime.combine(date_val - timedelta(days=body.lead_days), datetime.min.time(), tzinfo=timezone.utc)
            data["next_due_at"] = next_due.isoformat()

    if "key_date_id" in data:
        data["key_date_id"] = str(data["key_date_id"])

    result = supabase.table("reminders").insert(data).execute()
    await audit_log(supabase, action="reminder_created", resource_type="reminder",
                   resource_id=result.data[0]["id"], details={"contract_id": contract_id}, actor=actor)
    return result.data[0]


async def update_reminder(supabase: Client, reminder_id: str, body: UpdateReminderInput, actor: CurrentUser) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("reminders").update(data).eq("id", reminder_id).execute()
    if result.data:
        await audit_log(supabase, action="reminder_updated", resource_type="reminder",
                       resource_id=reminder_id, actor=actor)
    return result.data[0] if result.data else None


async def delete_reminder(supabase: Client, reminder_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("reminders").delete().eq("id", reminder_id).execute()
    if result.data:
        await audit_log(supabase, action="reminder_deleted", resource_type="reminder",
                       resource_id=reminder_id, actor=actor)
    return bool(result.data)


async def process_due_reminders(supabase: Client) -> int:
    """Called by scheduler. Find reminders where next_due_at <= now and is_active = true."""
    now = datetime.now(timezone.utc).isoformat()
    result = supabase.table("reminders").select("*, contracts(title, id)").eq("is_active", True).lte("next_due_at", now).is_("last_sent_at", "null").execute()

    sent_count = 0
    for reminder in result.data or []:
        try:
            # Create notification
            supabase.table("notifications").insert({
                "recipient_email": reminder.get("recipient_email"),
                "recipient_user_id": reminder.get("recipient_user_id"),
                "channel": reminder["channel"],
                "subject": f"CCRS Reminder: {reminder['reminder_type']} for contract",
                "body": f"Reminder for contract {reminder.get('contracts', {}).get('title', reminder['contract_id'])}. Type: {reminder['reminder_type']}. Lead time: {reminder['lead_days']} days.",
                "related_resource_type": "contract",
                "related_resource_id": reminder["contract_id"],
                "status": "pending",
            }).execute()

            # Mark as sent
            supabase.table("reminders").update({
                "last_sent_at": now,
            }).eq("id", reminder["id"]).execute()

            sent_count += 1
        except Exception as e:
            logger.error("reminder_processing_failed", reminder_id=reminder["id"], error=str(e))

    logger.info("reminders_processed", sent=sent_count)
    return sent_count
```

### D1.3 `app/reminders/router.py`
```python
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.reminders.schemas import CreateReminderInput, UpdateReminderInput
from app.reminders import service

router = APIRouter(tags=["reminders"])


@router.get("/contracts/{id}/reminders")
async def list_reminders(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_reminders(supabase, str(id))


@router.post(
    "/contracts/{id}/reminders",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    status_code=201,
)
async def create_reminder(
    id: UUID,
    body: CreateReminderInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_reminder(supabase, str(id), body, user)


@router.patch(
    "/reminders/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def update_reminder(
    id: UUID,
    body: UpdateReminderInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_reminder(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Reminder not found")
    return row


@router.delete(
    "/reminders/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def delete_reminder(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_reminder(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Reminder not found")
    return {"ok": True}
```

## D2. Escalation Module

**Create** `apps/api/app/escalation/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

### D2.1 `app/escalation/schemas.py`
```python
from uuid import UUID
from pydantic import BaseModel, Field


class CreateEscalationRuleInput(BaseModel):
    stage_name: str
    sla_breach_hours: int = Field(..., ge=1)
    tier: int = Field(1, ge=1, le=5)
    escalate_to_role: str | None = None
    escalate_to_user_id: str | None = None


class UpdateEscalationRuleInput(BaseModel):
    sla_breach_hours: int | None = Field(None, ge=1)
    tier: int | None = Field(None, ge=1, le=5)
    escalate_to_role: str | None = None
    escalate_to_user_id: str | None = None
```

### D2.2 `app/escalation/service.py`
```python
import structlog
from datetime import datetime, timezone
from supabase import Client
from app.auth.models import CurrentUser
from app.audit.service import audit_log

logger = structlog.get_logger()


def list_rules(supabase: Client, template_id: str) -> list[dict]:
    result = supabase.table("escalation_rules").select("*").eq("workflow_template_id", template_id).order("stage_name").order("tier").execute()
    return result.data


async def create_rule(supabase: Client, template_id: str, body, actor: CurrentUser) -> dict:
    data = body.model_dump(exclude_none=True)
    data["workflow_template_id"] = template_id
    result = supabase.table("escalation_rules").insert(data).execute()
    await audit_log(supabase, action="escalation_rule_created", resource_type="escalation_rule",
                   resource_id=result.data[0]["id"], actor=actor)
    return result.data[0]


async def update_rule(supabase: Client, rule_id: str, body, actor: CurrentUser) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("escalation_rules").update(data).eq("id", rule_id).execute()
    if result.data:
        await audit_log(supabase, action="escalation_rule_updated", resource_type="escalation_rule",
                       resource_id=rule_id, actor=actor)
    return result.data[0] if result.data else None


async def delete_rule(supabase: Client, rule_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("escalation_rules").delete().eq("id", rule_id).execute()
    if result.data:
        await audit_log(supabase, action="escalation_rule_deleted", resource_type="escalation_rule",
                       resource_id=rule_id, actor=actor)
    return bool(result.data)


def list_active_escalations(supabase: Client, limit: int = 50, offset: int = 0) -> tuple[list[dict], int]:
    result = supabase.table("escalation_events").select(
        "*, contracts(id, title, workflow_state), workflow_instances(id, current_stage)",
        count="exact"
    ).is_("resolved_at", "null").order("escalated_at", desc=True).range(offset, offset + limit - 1).execute()
    return result.data, result.count or 0


async def resolve_escalation(supabase: Client, event_id: str, actor: CurrentUser) -> dict | None:
    result = supabase.table("escalation_events").update({
        "resolved_at": datetime.now(timezone.utc).isoformat(),
        "resolved_by": actor.id,
    }).eq("id", event_id).is_("resolved_at", "null").execute()
    if result.data:
        await audit_log(supabase, action="escalation_resolved", resource_type="escalation_event",
                       resource_id=event_id, actor=actor)
    return result.data[0] if result.data else None


async def check_sla_breaches(supabase: Client) -> int:
    """Called by scheduler. Check active workflow instances for SLA breaches."""
    # Get all active workflow instances
    instances = supabase.table("workflow_instances").select(
        "*, workflow_templates(id), contracts(id)"
    ).eq("state", "active").execute()

    created_count = 0
    now = datetime.now(timezone.utc)

    for instance in instances.data or []:
        template_id = instance.get("template_id")
        current_stage = instance.get("current_stage")
        if not template_id or not current_stage:
            continue

        # Get the most recent action on this instance to determine stage entry time
        last_action = supabase.table("workflow_stage_actions").select("created_at").eq(
            "instance_id", instance["id"]
        ).order("created_at", desc=True).limit(1).execute()

        stage_entered_at = datetime.fromisoformat(
            last_action.data[0]["created_at"] if last_action.data
            else instance["started_at"]
        )
        if stage_entered_at.tzinfo is None:
            stage_entered_at = stage_entered_at.replace(tzinfo=timezone.utc)

        hours_in_stage = (now - stage_entered_at).total_seconds() / 3600

        # Check escalation rules for this template + stage
        rules = supabase.table("escalation_rules").select("*").eq(
            "workflow_template_id", template_id
        ).eq("stage_name", current_stage).order("tier").execute()

        for rule in rules.data or []:
            if hours_in_stage >= rule["sla_breach_hours"]:
                # Check if escalation already exists for this instance + rule
                existing = supabase.table("escalation_events").select("id").eq(
                    "workflow_instance_id", instance["id"]
                ).eq("rule_id", rule["id"]).is_("resolved_at", "null").execute()

                if not existing.data:
                    supabase.table("escalation_events").insert({
                        "workflow_instance_id": instance["id"],
                        "rule_id": rule["id"],
                        "contract_id": instance["contract_id"],
                        "stage_name": current_stage,
                        "tier": rule["tier"],
                    }).execute()

                    # Create notification for escalation
                    supabase.table("notifications").insert({
                        "recipient_email": rule.get("escalate_to_user_id"),
                        "channel": "email",
                        "subject": f"CCRS Escalation (Tier {rule['tier']}): SLA breach on stage '{current_stage}'",
                        "body": f"Workflow instance {instance['id']} has breached SLA at stage '{current_stage}'. "
                               f"Hours in stage: {hours_in_stage:.1f}. Threshold: {rule['sla_breach_hours']}h.",
                        "related_resource_type": "workflow_instance",
                        "related_resource_id": instance["id"],
                        "status": "pending",
                    }).execute()

                    created_count += 1

    logger.info("sla_check_completed", escalations_created=created_count)
    return created_count
```

### D2.3 `app/escalation/router.py`
```python
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, Response
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.escalation.schemas import CreateEscalationRuleInput, UpdateEscalationRuleInput
from app.escalation import service

router = APIRouter(tags=["escalation"])


@router.get("/workflow-templates/{id}/escalation-rules")
async def list_escalation_rules(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_rules(supabase, str(id))


@router.post(
    "/workflow-templates/{id}/escalation-rules",
    dependencies=[Depends(require_roles("System Admin"))],
    status_code=201,
)
async def create_escalation_rule(
    id: UUID,
    body: CreateEscalationRuleInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await service.create_rule(supabase, str(id), body, user)


@router.patch(
    "/escalation-rules/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def update_escalation_rule(
    id: UUID,
    body: UpdateEscalationRuleInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.update_rule(supabase, str(id), body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Rule not found")
    return row


@router.delete(
    "/escalation-rules/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def delete_escalation_rule(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_rule(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Rule not found")
    return {"ok": True}


@router.get("/escalations/active")
async def list_active_escalations(
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    data, total = service.list_active_escalations(supabase, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data


@router.post(
    "/escalations/{id}/resolve",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def resolve_escalation(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await service.resolve_escalation(supabase, str(id), user)
    if not row:
        raise HTTPException(status_code=404, detail="Escalation not found or already resolved")
    return row
```

## D3. Notifications Module

**Create** `apps/api/app/notifications/` with `__init__.py`, `router.py`, `service.py`.

### D3.1 `app/notifications/service.py`
```python
import structlog
from supabase import Client
from datetime import datetime, timezone

logger = structlog.get_logger()


def list_notifications(supabase: Client, recipient_email: str | None = None,
                       status: str | None = None, limit: int = 50, offset: int = 0) -> tuple[list[dict], int]:
    query = supabase.table("notifications").select("*", count="exact")
    if recipient_email:
        query = query.eq("recipient_email", recipient_email)
    if status:
        query = query.eq("status", status)
    result = query.order("created_at", desc=True).range(offset, offset + limit - 1).execute()
    return result.data, result.count or 0


async def send_pending_notifications(supabase: Client) -> int:
    """Called by scheduler. Process pending notifications."""
    from app.config import settings

    result = supabase.table("notifications").select("*").eq("status", "pending").limit(50).execute()
    sent_count = 0

    for notif in result.data or []:
        try:
            if notif["channel"] == "email" and settings.sendgrid_api_key:
                # SendGrid integration
                _send_email(
                    to=notif["recipient_email"],
                    subject=notif["subject"],
                    body=notif["body"],
                    api_key=settings.sendgrid_api_key,
                    from_email=settings.notification_from_email,
                )
                supabase.table("notifications").update({
                    "status": "sent",
                    "sent_at": datetime.now(timezone.utc).isoformat(),
                }).eq("id", notif["id"]).execute()
                sent_count += 1
            else:
                # Log-only mode when no email provider configured
                logger.info("notification_logged", channel=notif["channel"],
                          recipient=notif.get("recipient_email"), subject=notif["subject"])
                supabase.table("notifications").update({
                    "status": "sent",
                    "sent_at": datetime.now(timezone.utc).isoformat(),
                }).eq("id", notif["id"]).execute()
                sent_count += 1

        except Exception as e:
            logger.error("notification_send_failed", notification_id=notif["id"], error=str(e))
            supabase.table("notifications").update({
                "status": "failed",
                "error_message": str(e),
            }).eq("id", notif["id"]).execute()

    logger.info("notifications_processed", sent=sent_count)
    return sent_count


def _send_email(to: str, subject: str, body: str, api_key: str, from_email: str):
    """Send email via SendGrid. Install sendgrid package if using this."""
    import httpx
    response = httpx.post(
        "https://api.sendgrid.com/v3/mail/send",
        headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
        json={
            "personalizations": [{"to": [{"email": to}]}],
            "from": {"email": from_email},
            "subject": subject,
            "content": [{"type": "text/plain", "value": body}],
        },
    )
    response.raise_for_status()
```

### D3.2 `app/notifications/router.py`
```python
from fastapi import APIRouter, Depends, Response
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.notifications import service

router = APIRouter(tags=["notifications"])


@router.get("/notifications")
async def list_notifications(
    status: str | None = None,
    limit: int = 50,
    offset: int = 0,
    response: Response = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    # Return only the current user's notifications
    data, total = service.list_notifications(supabase, user.email, status, limit, offset)
    response.headers["X-Total-Count"] = str(total)
    return data
```

## D4. Background Scheduler

**Create** `apps/api/app/scheduler.py`:

```python
import asyncio
import structlog
from contextlib import asynccontextmanager
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from app.config import settings
from app.deps import get_supabase

logger = structlog.get_logger()
scheduler = AsyncIOScheduler()


async def _run_reminders():
    from app.reminders.service import process_due_reminders
    supabase = get_supabase()
    count = await process_due_reminders(supabase)
    logger.info("scheduler_reminders_done", sent=count)


async def _run_escalation_check():
    from app.escalation.service import check_sla_breaches
    supabase = get_supabase()
    count = await check_sla_breaches(supabase)
    logger.info("scheduler_escalation_done", created=count)


async def _run_notifications():
    from app.notifications.service import send_pending_notifications
    supabase = get_supabase()
    count = await send_pending_notifications(supabase)
    logger.info("scheduler_notifications_done", sent=count)


def start_scheduler():
    if not settings.scheduler_enabled:
        logger.info("scheduler_disabled")
        return

    # Reminders: check daily at 8:00 UTC
    scheduler.add_job(_run_reminders, "cron", hour=8, minute=0, id="reminders")
    # Escalation: check every hour
    scheduler.add_job(_run_escalation_check, "interval", hours=1, id="escalation")
    # Notifications: process every 5 minutes
    scheduler.add_job(_run_notifications, "interval", minutes=5, id="notifications")

    scheduler.start()
    logger.info("scheduler_started", jobs=["reminders", "escalation", "notifications"])


def stop_scheduler():
    if scheduler.running:
        scheduler.shutdown(wait=False)
        logger.info("scheduler_stopped")
```

**Update** `apps/api/app/main.py` lifespan to start/stop the scheduler:

Add to the lifespan function:
```python
from app.scheduler import start_scheduler, stop_scheduler

@asynccontextmanager
async def lifespan(app: FastAPI):
    logger = structlog.get_logger()
    logger.info("ccrs_api_starting", port=settings.port)
    start_scheduler()
    yield
    stop_scheduler()
    logger.info("ccrs_api_stopping")
```

---

# PART E: PHASE 1c BACKEND — REPORTING & ANALYTICS (Epic 12)

**Create** `apps/api/app/reports/` with `__init__.py`, `router.py`, `service.py`.

### E1. `app/reports/service.py`
```python
import structlog
from supabase import Client

logger = structlog.get_logger()


def contract_status_summary(supabase: Client, region_id: str | None = None,
                            entity_id: str | None = None) -> dict:
    """Aggregate contracts by workflow_state and contract_type."""
    query = supabase.table("contracts").select("workflow_state, contract_type, id")
    if region_id:
        query = query.eq("region_id", region_id)
    if entity_id:
        query = query.eq("entity_id", entity_id)
    result = query.execute()

    by_state = {}
    by_type = {}
    for row in result.data or []:
        state = row.get("workflow_state", "unknown")
        ctype = row.get("contract_type", "unknown")
        by_state[state] = by_state.get(state, 0) + 1
        by_type[ctype] = by_type.get(ctype, 0) + 1

    return {"by_state": by_state, "by_type": by_type, "total": len(result.data or [])}


def expiry_horizon(supabase: Client, region_id: str | None = None) -> dict:
    """Contracts grouped by expiry window: 30/60/90/90+ days."""
    from datetime import datetime, timedelta, timezone
    now = datetime.now(timezone.utc).date()

    # Get all key dates of type expiry_date
    query = supabase.table("contract_key_dates").select("contract_id, date_value, contracts(id, title, workflow_state, entity_id)")
    query = query.eq("date_type", "expiry_date").gte("date_value", now.isoformat())
    result = query.order("date_value").execute()

    buckets = {"0_30": [], "31_60": [], "61_90": [], "90_plus": []}
    for row in result.data or []:
        from datetime import date as date_type
        dv = row["date_value"]
        if isinstance(dv, str):
            dv = datetime.fromisoformat(dv).date()
        days_until = (dv - now).days
        entry = {"contract_id": row["contract_id"], "expiry_date": row["date_value"],
                 "days_until": days_until, "contract": row.get("contracts")}
        if days_until <= 30:
            buckets["0_30"].append(entry)
        elif days_until <= 60:
            buckets["31_60"].append(entry)
        elif days_until <= 90:
            buckets["61_90"].append(entry)
        else:
            buckets["90_plus"].append(entry)

    return {
        "buckets": buckets,
        "counts": {k: len(v) for k, v in buckets.items()},
    }


def signing_status_summary(supabase: Client) -> dict:
    """Signing pipeline funnel."""
    result = supabase.table("boldsign_envelopes").select("status, id").execute()
    by_status = {}
    for row in result.data or []:
        st = row.get("status", "unknown")
        by_status[st] = by_status.get(st, 0) + 1
    return {"by_status": by_status, "total": len(result.data or [])}


def ai_cost_summary(supabase: Client, period_days: int = 30) -> dict:
    """AI token usage and cost summary."""
    from datetime import datetime, timedelta, timezone
    since = (datetime.now(timezone.utc) - timedelta(days=period_days)).isoformat()

    result = supabase.table("ai_analysis_results").select(
        "analysis_type, cost_usd, token_usage_input, token_usage_output, model_used"
    ).gte("created_at", since).eq("status", "completed").execute()

    total_cost = 0.0
    total_input_tokens = 0
    total_output_tokens = 0
    by_type = {}

    for row in result.data or []:
        cost = float(row.get("cost_usd") or 0)
        inp = row.get("token_usage_input") or 0
        out = row.get("token_usage_output") or 0
        total_cost += cost
        total_input_tokens += inp
        total_output_tokens += out

        atype = row.get("analysis_type", "unknown")
        if atype not in by_type:
            by_type[atype] = {"count": 0, "cost_usd": 0.0, "input_tokens": 0, "output_tokens": 0}
        by_type[atype]["count"] += 1
        by_type[atype]["cost_usd"] += cost
        by_type[atype]["input_tokens"] += inp
        by_type[atype]["output_tokens"] += out

    return {
        "period_days": period_days,
        "total_analyses": len(result.data or []),
        "total_cost_usd": round(total_cost, 4),
        "total_input_tokens": total_input_tokens,
        "total_output_tokens": total_output_tokens,
        "by_type": by_type,
    }
```

### E2. `app/reports/router.py`
```python
from fastapi import APIRouter, Depends
from supabase import Client

from app.auth.dependencies import get_current_user
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.reports import service

router = APIRouter(prefix="/reports", tags=["reports"])


@router.get("/contract-status")
async def contract_status(
    region_id: str | None = None,
    entity_id: str | None = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.contract_status_summary(supabase, region_id, entity_id)


@router.get("/expiry-horizon")
async def expiry_horizon(
    region_id: str | None = None,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.expiry_horizon(supabase, region_id)


@router.get("/signing-status")
async def signing_status(
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.signing_status_summary(supabase)


@router.get("/ai-costs")
async def ai_costs(
    period_days: int = 30,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.ai_cost_summary(supabase, period_days)
```

---

# PART F: PHASE 1c BACKEND — MULTI-LANGUAGE SUPPORT (Epic 13)

**Create** `apps/api/app/contract_languages/` with `__init__.py`, `router.py`, `service.py`, `schemas.py`.

### F1. `app/contract_languages/schemas.py`
```python
from pydantic import BaseModel


class AttachLanguageInput(BaseModel):
    language_code: str
    is_primary: bool = False
```

### F2. `app/contract_languages/service.py`
```python
import structlog
from supabase import Client
from fastapi import UploadFile
from app.auth.models import CurrentUser
from app.audit.service import audit_log

logger = structlog.get_logger()

ALLOWED_MIME_TYPES = {
    "application/pdf", "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
}


def list_languages(supabase: Client, contract_id: str) -> list[dict]:
    result = supabase.table("contract_languages").select("*").eq("contract_id", contract_id).order("is_primary", desc=True).execute()
    return result.data


async def attach_language(
    supabase: Client, contract_id: str, language_code: str,
    is_primary: bool, file: UploadFile, actor: CurrentUser,
) -> dict:
    if file.content_type not in ALLOWED_MIME_TYPES:
        raise ValueError(f"Unsupported file type: {file.content_type}")

    # Upload to Supabase Storage
    storage_path = f"contracts/{contract_id}/languages/{language_code}/{file.filename}"
    file_bytes = await file.read()
    supabase.storage.from_("contracts").upload(storage_path, file_bytes, {"content-type": file.content_type})

    # If marking as primary, unset other primaries
    if is_primary:
        supabase.table("contract_languages").update({"is_primary": False}).eq("contract_id", contract_id).execute()

    record = supabase.table("contract_languages").insert({
        "contract_id": contract_id,
        "language_code": language_code,
        "is_primary": is_primary,
        "storage_path": storage_path,
        "file_name": file.filename,
    }).execute()

    await audit_log(supabase, action="language_version_attached", resource_type="contract",
                   resource_id=contract_id, details={"language_code": language_code}, actor=actor)
    return record.data[0]


async def delete_language(supabase: Client, lang_id: str, actor: CurrentUser) -> bool:
    record = supabase.table("contract_languages").select("*").eq("id", lang_id).single().execute()
    if not record.data:
        return False

    # Delete file from storage
    if record.data.get("storage_path"):
        try:
            supabase.storage.from_("contracts").remove([record.data["storage_path"]])
        except Exception as e:
            logger.warning("language_file_delete_failed", error=str(e))

    supabase.table("contract_languages").delete().eq("id", lang_id).execute()
    await audit_log(supabase, action="language_version_deleted", resource_type="contract_language",
                   resource_id=lang_id, actor=actor)
    return True
```

### F3. `app/contract_languages/router.py`
```python
from uuid import UUID
from fastapi import APIRouter, Depends, HTTPException, File, Form, UploadFile
from supabase import Client

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.contract_languages import service

router = APIRouter(tags=["contract_languages"])


@router.get("/contracts/{id}/languages")
async def list_languages(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return service.list_languages(supabase, str(id))


@router.post(
    "/contracts/{id}/languages",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
    status_code=201,
)
async def attach_language(
    id: UUID,
    language_code: str = Form(...),
    is_primary: bool = Form(False),
    file: UploadFile = File(...),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await service.attach_language(supabase, str(id), language_code, is_primary, file, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.delete(
    "/contract-languages/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
)
async def delete_language(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await service.delete_language(supabase, str(id), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Language version not found")
    return {"ok": True}
```

---

# PART G: REGISTER ALL NEW ROUTERS

**Update** `apps/api/app/main.py` to import and include all new routers:

Add these imports:
```python
from app.ai_analysis.router import router as ai_analysis_router
from app.obligations.router import router as obligations_router
from app.reminders.router import router as reminders_router
from app.escalation.router import router as escalation_router
from app.notifications.router import router as notifications_router
from app.reports.router import router as reports_router
from app.contract_languages.router import router as contract_languages_router
```

Add these includes after the existing routers:
```python
app.include_router(ai_analysis_router)
app.include_router(obligations_router)
app.include_router(reminders_router)
app.include_router(escalation_router)
app.include_router(notifications_router)
app.include_router(reports_router)
app.include_router(contract_languages_router)
```

**Update** `apps/api/requirements.txt` — append:
```
anthropic>=0.52.0
apscheduler>=3.10.0
PyMuPDF>=1.25.0
python-docx>=1.1.0
```

---

# PART H: PHASE 1c FRONTEND

## H1. Install chart library

```bash
cd apps/web && npm install recharts date-fns
```

## H2. AI Analysis Tab on Contract Detail

**Enhance** `apps/web/src/app/(dashboard)/contracts/[id]/page.tsx` (or the contract detail component):

Add an "AI Analysis" tab with:
1. **"Analyze" dropdown button** with options: Summary, Full Extraction, Risk Assessment, Template Deviation, Obligations. Each calls `POST /api/ccrs/contracts/${id}/analyze` with the selected `analysisType`.
2. **Analysis results section** — fetched via `GET /api/ccrs/contracts/${id}/analysis`. Show each analysis as an expandable card:
   - Status badge (pending/processing/completed/failed)
   - Analysis type label
   - Cost indicator: "Tokens: {input}+{output} | Cost: ${cost_usd}"
   - Processing time
3. **Summary card** — rendered Markdown for the summary text, key parties list, dates, governing law
4. **Extracted fields table** — Table with columns: Field Name, Value, Evidence, Confidence (progress bar), Verified (checkbox), Actions (Verify/Correct). "Verify" calls `POST /api/ccrs/ai-fields/${id}/verify`. "Correct" opens inline edit then calls `PATCH /api/ccrs/ai-fields/${id}`.
5. **Risk assessment panel** — Overall risk score (color-coded gauge), risk items as cards with severity badges
6. **Deviations list** — Table of deviations with clause reference, template vs contract text diff
7. **Obligations list** — Table with type, description, due date, recurrence, responsible party

## H3. Obligations Register Page

**Create** `apps/web/src/app/(dashboard)/obligations/page.tsx`:

1. **Global obligations list** fetched from `GET /api/ccrs/obligations`
2. **Filters:** Status (active/completed/waived/overdue), obligation type, date range
3. **Table:** Using shadcn Table. Columns: Contract, Type, Description, Due Date, Recurrence, Responsible, Status, Actions
4. **CSV export button** — client-side CSV generation from the table data
5. **Status update** — dropdown to change status inline

**Add** "Obligations" to `app-nav.tsx`.

## H4. "Generate with AI" on Workflow Builder

**Enhance** `apps/web/src/app/(dashboard)/workflows/new/page.tsx` (or the workflow builder):

1. **"Generate with AI" button** at the top of the builder
2. Opens a modal with:
   - Large textarea for natural language workflow description
   - Optional dropdowns: Region, Entity, Project
   - "Generate" button → calls `POST /api/ccrs/workflows/generate`
3. On success, populate the React Flow canvas with the generated stages and transitions
4. Show confidence score and explanation panel
5. Show validation errors (if any) as warnings
6. User can edit the generated workflow in the visual builder before saving

## H5. Reminders UI on Contract Detail

**Enhance** the contract detail page — add a "Reminders" section in the Key Dates tab:

1. **List existing reminders** for the contract via `GET /api/ccrs/contracts/${id}/reminders`
2. **"Add Reminder" button** → form with: key date selector (from existing key dates), reminder type, lead days, channel (email/teams/calendar), recipient email
3. **Toggle active/inactive** per reminder
4. **Delete reminder** with confirmation

## H6. Escalation Dashboard

**Create** `apps/web/src/app/(dashboard)/escalations/page.tsx`:

1. **Active escalations table** from `GET /api/ccrs/escalations/active`
2. Columns: Contract Title, Stage, Tier, Hours Breached, Escalated To, Escalated At, Actions
3. **"Resolve" button** per row → calls `POST /api/ccrs/escalations/${id}/resolve`
4. Color-coded tiers: Tier 1 = yellow, Tier 2 = orange, Tier 3+ = red
5. Auto-refresh every 60 seconds

**Add** "Escalations" to `app-nav.tsx`.

## H7. Escalation Rules in Workflow Template Editor

**Enhance** the workflow template edit page:

1. **"Escalation Rules" tab** per stage in the side panel
2. Table of rules for the current stage: SLA hours, tier, escalate to role/user
3. Add/edit/delete rules via `POST/PATCH/DELETE /api/ccrs/workflow-templates/${id}/escalation-rules`

## H8. Executive Reporting Dashboard

**Create** `apps/web/src/app/(dashboard)/reports/page.tsx`:

1. **Contract Status Summary** — Pie chart (by workflow state) + bar chart (by type) using recharts. Fetch from `GET /api/ccrs/reports/contract-status`.
2. **Expiry Horizon** — Stacked bar chart showing contracts expiring in 0-30, 31-60, 61-90, 90+ days. Fetch from `GET /api/ccrs/reports/expiry-horizon`. Click a bar to see the list of contracts.
3. **Signing Pipeline** — Horizontal funnel chart showing signing status breakdown. Fetch from `GET /api/ccrs/reports/signing-status`.
4. **AI Costs** — Line/bar chart of AI analysis costs over time. Fetch from `GET /api/ccrs/reports/ai-costs`.
5. **Filter bar** at the top: Region, Entity, Date Range (using date-fns).
6. **Export buttons** — CSV and PDF for each section.

**Add** "Reports" to `app-nav.tsx`.

## H9. Multi-Language UI

**Enhance** the contract detail page — add a "Languages" tab:

1. **List language versions** via `GET /api/ccrs/contracts/${id}/languages`
2. Each row shows: language code, file name, is_primary badge, download link, delete button
3. **"Add Language Version" form:** language code dropdown (ISO 639-1), file upload, is_primary checkbox
4. Calls `POST /api/ccrs/contracts/${id}/languages` (multipart form)

## H10. Notification Preferences

**Create** `apps/web/src/app/(dashboard)/settings/page.tsx`:

1. **Notifications list** from `GET /api/ccrs/notifications`
2. Table of recent notifications: Subject, Channel, Status, Sent At
3. Placeholder for notification preferences (email/Teams toggles) — can be expanded later

## H11. Navigation Update

**Update** `apps/web/src/components/app-nav.tsx` to add:
- "Obligations" → `/obligations`
- "Escalations" → `/escalations`
- "Reports" → `/reports`
- "Settings" → `/settings`

---

# PART I: PHASE 1c TESTS

**Create test files for all new backend modules:**

### I1. `tests/test_ai_analysis.py`
- Trigger summary analysis (mock Anthropic client) → returns completed result
- Trigger extraction analysis → saves extracted fields
- Trigger obligations analysis → saves to obligations register
- Verify extracted field → updates is_verified and verified_by
- Correct extracted field → updates field_value
- Analysis of contract with no file → returns 400
- Budget enforcement: mock cost exceeding limit → analysis still completes gracefully
- Unauthenticated request → 401

### I2. `tests/test_ai_workflow_generator.py`
- Generate workflow from description (mock Anthropic) → returns valid stages
- Generated workflow passes validation
- Self-correction on invalid output (mock two responses)
- Requires System Admin role → 403 for non-admin

### I3. `tests/test_obligations.py`
- CRUD operations: create (201), list (200 + X-Total-Count), update, delete
- Filter by status and obligation_type
- List all obligations (global endpoint)
- Requires appropriate roles

### I4. `tests/test_reminders.py`
- CRUD operations: create, list, update (toggle active), delete
- next_due_at calculated from key_date lead_days
- process_due_reminders creates notifications for due reminders
- process_due_reminders skips already-sent reminders

### I5. `tests/test_escalation.py`
- CRUD escalation rules on workflow template
- check_sla_breaches creates escalation events for breached instances
- check_sla_breaches skips already-escalated instances (dedup)
- resolve_escalation sets resolved_at and resolved_by
- list_active_escalations returns only unresolved events
- Requires System Admin role for rule management

### I6. `tests/test_notifications.py`
- list_notifications filters by recipient
- send_pending_notifications processes pending → sent (log-only mode)

### I7. `tests/test_reports.py`
- contract_status_summary returns by_state and by_type counts
- expiry_horizon buckets contracts correctly
- signing_status_summary aggregates boldsign_envelopes
- ai_cost_summary totals costs and tokens by type

### I8. `tests/test_contract_languages.py`
- Attach language version (multipart upload) → 201
- List language versions
- Delete language version → removes file from storage
- Duplicate language_code for same contract → error (unique constraint)
- Unsupported file type → 400

### Test fixture patterns

All tests should follow the existing `conftest.py` pattern:
```python
@pytest.fixture
def authed_client(mock_supabase, test_user):
    app.dependency_overrides[get_supabase] = lambda: mock_supabase
    app.dependency_overrides[get_current_user] = lambda: test_user
    client = TestClient(app)
    yield client
    app.dependency_overrides.clear()
```

For AI tests, additionally mock the `anthropic.Anthropic` client:
```python
@pytest.fixture
def mock_anthropic(monkeypatch):
    mock_client = MagicMock()
    mock_response = MagicMock()
    mock_response.content = [MagicMock(text='{"summary": "Test summary", "key_parties": []}', type="text")]
    mock_response.usage = MagicMock(input_tokens=100, output_tokens=50)
    mock_response.stop_reason = "end_turn"
    mock_client.messages.create.return_value = mock_response
    monkeypatch.setattr("anthropic.Anthropic", lambda **kwargs: mock_client)
    return mock_client
```

---

# Completion Checklist

After all changes:
1. [ ] `cd apps/api && pytest tests/ -v` — all tests pass
2. [ ] `cd apps/web && npm run build` — no errors
3. [ ] New Phase 1c migration applied to Supabase
4. [ ] AI analysis module (summary, extraction, risk, deviation, obligations) working
5. [ ] AI-extracted fields with verify/correct working
6. [ ] Obligations register with CRUD and filtering working
7. [ ] LLM workflow generator producing valid template JSON
8. [ ] Reminders module with background scheduler working
9. [ ] Escalation rules + SLA breach detection working
10. [ ] Notifications module (log mode, SendGrid when configured) working
11. [ ] Reporting endpoints returning aggregated data
12. [ ] Executive dashboard with recharts visualizations working
13. [ ] Multi-language contract support working
14. [ ] All new endpoints visible in Swagger at `/docs`
15. [ ] Background scheduler starts on app startup
