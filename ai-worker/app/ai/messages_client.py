"""Simple summary analysis via Claude."""
import time
from app.config import settings
from app.ai.schemas import AnalysisUsage, SummaryResult


async def analyze_summary(contract_text: str) -> tuple[SummaryResult, AnalysisUsage]:
    """Run a simple summary analysis. Returns (result, usage)."""
    import anthropic
    start = time.perf_counter()
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    msg = await client.messages.create(
        model=settings.ai_model,
        max_tokens=1024,
        messages=[{"role": "user", "content": f"Summarize this contract in a few paragraphs:\n\n{contract_text[:50000]}"}],
    )
    elapsed_ms = int((time.perf_counter() - start) * 1000)
    summary_text = ""
    if msg.content and len(msg.content) > 0:
        summary_text = msg.content[0].text if hasattr(msg.content[0], "text") else str(msg.content[0])
    result = SummaryResult(summary=summary_text, key_terms=[], confidence=0.9)
    usage = AnalysisUsage(
        input_tokens=msg.usage.input_tokens if msg.usage else 0,
        output_tokens=msg.usage.output_tokens if msg.usage else 0,
        cost_usd=0.0,
        processing_time_ms=elapsed_ms,
        model_used=settings.ai_model,
    )
    return result, usage
