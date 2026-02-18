import time

import anthropic
import structlog

from app.ai.schemas import AnalysisUsage, SummaryResult
from app.config import settings

logger = structlog.get_logger()

SUMMARY_SYSTEM_PROMPT = """You are a contract analysis assistant for the CCRS system.
Analyze the provided contract text and return a structured JSON summary.
Extract: summary (2-3 sentences), key_parties, contract_type_detected,
effective_date (ISO format or null), expiry_date (ISO format or null),
total_value, governing_law, language_detected.
Return ONLY valid JSON matching this schema — no markdown, no code fences."""


async def analyze_summary(contract_text: str) -> tuple[SummaryResult, AnalysisUsage]:
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
    if "sonnet" in model:
        return (input_tokens * 3.0 / 1_000_000) + (output_tokens * 15.0 / 1_000_000)
    if "haiku" in model:
        return (input_tokens * 0.8 / 1_000_000) + (output_tokens * 4.0 / 1_000_000)
    if "opus" in model:
        return (input_tokens * 15.0 / 1_000_000) + (output_tokens * 75.0 / 1_000_000)
    return 0.0
import time

import anthropic
import structlog

from app.config import settings
from app.ai.schemas import AnalysisUsage, SummaryResult

logger = structlog.get_logger()

SUMMARY_SYSTEM_PROMPT = """You are a contract analysis assistant for the CCRS system.
Analyze the provided contract text and return a structured JSON summary.
Extract: summary (2-3 sentences), key_parties, contract_type_detected,
effective_date (ISO format or null), expiry_date (ISO format or null),
total_value, governing_law, language_detected.
Return ONLY valid JSON matching this schema — no markdown, no code fences."""


async def analyze_summary(contract_text: str) -> tuple[SummaryResult, AnalysisUsage]:
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
    if "sonnet" in model:
        return (input_tokens * 3.0 / 1_000_000) + (output_tokens * 15.0 / 1_000_000)
    if "haiku" in model:
        return (input_tokens * 0.8 / 1_000_000) + (output_tokens * 4.0 / 1_000_000)
    if "opus" in model:
        return (input_tokens * 15.0 / 1_000_000) + (output_tokens * 75.0 / 1_000_000)
    return 0.0
