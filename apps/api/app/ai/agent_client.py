import json
import time

import anthropic
import structlog

from app.ai.mcp_tools import get_tools
from app.ai.schemas import (
    AnalysisUsage,
    DeviationResult,
    ExtractionResult,
    ObligationsResult,
    RiskResult,
)
from app.config import settings

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
    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)
    tools = get_tools(supabase_client, contract_id)
    system_prompt = SYSTEM_PROMPTS[analysis_type]
    result_model = RESULT_MODELS[analysis_type]

    start = time.monotonic()
    total_input = 0
    total_output = 0

    messages = [{"role": "user", "content": contract_text[:100_000]}]

    for _ in range(10):
        response = client.messages.create(
            model=settings.ai_agent_model,
            max_tokens=4096,
            system=system_prompt,
            tools=[t["definition"] for t in tools],
            messages=messages,
        )

        total_input += response.usage.input_tokens
        total_output += response.usage.output_tokens

        current_cost = _estimate_cost(total_input, total_output, settings.ai_agent_model)
        if current_cost > settings.ai_max_budget_usd:
            logger.warning("ai_budget_exceeded", cost=current_cost, budget=settings.ai_max_budget_usd)
            break

        if response.stop_reason == "tool_use":
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    tool_fn = next((t["handler"] for t in tools if t["definition"]["name"] == block.name), None)
                    if tool_fn:
                        result = tool_fn(**block.input)
                        tool_results.append(
                            {
                                "type": "tool_result",
                                "tool_use_id": block.id,
                                "content": json.dumps(result) if isinstance(result, (dict, list)) else str(result),
                            }
                        )

            messages.append({"role": "assistant", "content": response.content})
            messages.append({"role": "user", "content": tool_results})
            continue

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
import json
import time

import anthropic
import structlog

from app.config import settings
from app.ai.schemas import (
    AnalysisUsage,
    DeviationResult,
    ExtractionResult,
    ObligationsResult,
    RiskResult,
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
    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)
    tools = get_tools(supabase_client, contract_id)
    system_prompt = SYSTEM_PROMPTS[analysis_type]
    result_model = RESULT_MODELS[analysis_type]

    start = time.monotonic()
    total_input = 0
    total_output = 0

    messages = [{"role": "user", "content": contract_text[:100_000]}]

    for _ in range(10):
        response = client.messages.create(
            model=settings.ai_agent_model,
            max_tokens=4096,
            system=system_prompt,
            tools=[t["definition"] for t in tools],
            messages=messages,
        )

        total_input += response.usage.input_tokens
        total_output += response.usage.output_tokens

        current_cost = _estimate_cost(total_input, total_output, settings.ai_agent_model)
        if current_cost > settings.ai_max_budget_usd:
            logger.warning("ai_budget_exceeded", cost=current_cost, budget=settings.ai_max_budget_usd)
            break

        if response.stop_reason == "tool_use":
            tool_results = []
            for block in response.content:
                if block.type == "tool_use":
                    tool_fn = next((t["handler"] for t in tools if t["definition"]["name"] == block.name), None)
                    if tool_fn:
                        result = tool_fn(**block.input)
                        tool_results.append(
                            {
                                "type": "tool_result",
                                "tool_use_id": block.id,
                                "content": json.dumps(result) if isinstance(result, (dict, list)) else str(result),
                            }
                        )
            messages.append({"role": "assistant", "content": response.content})
            messages.append({"role": "user", "content": tool_results})
            continue

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
