"""
Redline analysis module — compares contract clauses against WikiContract templates
using Claude AI for structured diff output.
"""

import json
import logging
from typing import Any

import anthropic

from app.config import settings

logger = logging.getLogger(__name__)

REDLINE_SYSTEM_PROMPT = """You are a legal contract analyst. Your task is to compare a contract against a standard template and produce a structured clause-by-clause analysis.

For each clause in the contract:
1. Identify the clause number and heading.
2. Find the most similar clause in the template (by subject matter, not just position).
3. Compare the two and classify the difference:
   - "unchanged" — the clause is substantively identical to the template
   - "modification" — the clause exists in both but has material differences (changed terms, altered conditions, different thresholds)
   - "deletion" — a template clause has been removed from the contract entirely
   - "addition" — a clause exists in the contract but has no counterpart in the template
4. For modifications, deletions, and additions, provide:
   - The suggested text (what the template says, or what should be added)
   - A plain English rationale explaining the material difference and its business/legal impact
   - A confidence score from 0.0 to 1.0 indicating how certain you are about the comparison

Focus on material deviations: changed payment terms, altered liability caps, missing indemnification, removed cure periods, added obligations, changed termination rights, modified IP ownership, and similar substantive changes. Ignore minor formatting or stylistic differences.

You MUST respond with valid JSON only, no markdown, no explanation outside the JSON."""

REDLINE_USER_PROMPT = """Compare the following contract against the standard template.

=== CONTRACT TEXT ===
{contract_text}

=== TEMPLATE TEXT ===
{template_text}

Respond with a JSON object in exactly this format:
{{
    "clauses": [
        {{
            "clause_number": 1,
            "clause_heading": "Definitions",
            "original_text": "The exact clause text from the contract",
            "suggested_text": "The corresponding template clause text (null if unchanged)",
            "change_type": "unchanged|modification|deletion|addition",
            "ai_rationale": "Plain English explanation of the difference and its impact (null if unchanged)",
            "confidence": 0.95
        }}
    ],
    "summary": {{
        "total_clauses": 15,
        "unchanged": 10,
        "modifications": 3,
        "deletions": 1,
        "additions": 1,
        "material_risk_areas": ["Liability cap reduced from 2x to 1x annual fees", "30-day cure period removed"],
        "overall_assessment": "Contract has 5 material deviations from the standard template. The most significant are the reduced liability cap and removed cure period, which increase organizational risk."
    }}
}}"""


def analyze_redline(contract_text: str, template_text: str) -> dict[str, Any]:
    """
    Use Claude to compare a contract against a template and produce
    a structured clause-by-clause redline analysis.

    Returns a dict with 'clauses' (list) and 'summary' (dict).
    """
    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)

    user_prompt = REDLINE_USER_PROMPT.format(
        contract_text=contract_text,
        template_text=template_text,
    )

    logger.info("Sending redline analysis request to Claude (%s)", settings.ai_model)

    response = client.messages.create(
        model=settings.ai_model,
        max_tokens=8192,
        system=REDLINE_SYSTEM_PROMPT,
        messages=[
            {"role": "user", "content": user_prompt},
        ],
    )

    raw_text = response.content[0].text.strip()

    # Strip markdown code fences if present
    if raw_text.startswith("```"):
        lines = raw_text.split("\n")
        lines = [line for line in lines if not line.strip().startswith("```")]
        raw_text = "\n".join(lines)

    try:
        result = json.loads(raw_text)
    except json.JSONDecodeError as e:
        logger.error("Failed to parse Claude response as JSON: %s", e)
        logger.error("Raw response (first 500 chars): %s", raw_text[:500])
        raise ValueError(f"AI returned invalid JSON: {e}") from e

    if "clauses" not in result:
        raise ValueError("AI response missing 'clauses' key")
    if "summary" not in result:
        raise ValueError("AI response missing 'summary' key")

    return result
