"""Complex AI analysis (risk, extraction, obligations, deviation) with tool use."""
import json
import time
from app.config import settings
from app.ai.schemas import AnalysisUsage


async def analyze_complex(
    analysis_type: str,
    contract_text: str,
    contract_id: str,
    tools: list[dict],
) -> tuple[dict, AnalysisUsage]:
    """Run complex analysis with optional tool use. tools already provided (no supabase)."""
    import anthropic
    start = time.perf_counter()
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    system = (
        f"You are a contract analyst. Perform {analysis_type} analysis on the following contract. "
        "Return a structured JSON result appropriate for the analysis type."
    )
    tool_defs = [t["definition"] for t in tools]
    message = await client.messages.create(
        model=settings.ai_agent_model,
        max_tokens=4096,
        system=system,
        messages=[{"role": "user", "content": contract_text[:80000]}],
        tools=tool_defs if tool_defs else None,
    )
    elapsed_ms = int((time.perf_counter() - start) * 1000)
    result_dict = {"summary": "", "confidence": 0.8}
    if message.content:
        for block in message.content:
            if hasattr(block, "text") and block.text:
                try:
                    result_dict = json.loads(block.text)
                except json.JSONDecodeError:
                    result_dict = {"raw": block.text}
                break
    usage = AnalysisUsage(
        input_tokens=message.usage.input_tokens if message.usage else 0,
        output_tokens=message.usage.output_tokens if message.usage else 0,
        cost_usd=0.0,
        processing_time_ms=elapsed_ms,
        model_used=settings.ai_agent_model,
    )
    return result_dict, usage
