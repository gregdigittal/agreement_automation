"""Complex AI analysis (risk, extraction, obligations, deviation) with tool use."""
import json
import time
from app.config import settings
from app.ai.schemas import AnalysisUsage


def _find_tool_handler(tools: list[dict], name: str):
    """Return the handler for a tool by definition name."""
    for t in tools:
        if t["definition"].get("name") == name:
            return t.get("handler")
    return None


def _run_tool(tools: list[dict], name: str, tool_input: dict) -> str:
    """Execute a tool by name with the given input. Returns string content for tool_result."""
    handler = _find_tool_handler(tools, name)
    if not handler:
        return json.dumps({"error": f"Unknown tool: {name}"})
    try:
        result = handler(**tool_input) if tool_input else handler()
        return json.dumps(result) if not isinstance(result, str) else result
    except Exception as e:
        return json.dumps({"error": str(e)})


async def analyze_complex(
    analysis_type: str,
    contract_text: str,
    contract_id: str,
    tools: list[dict],
) -> tuple[dict, AnalysisUsage]:
    """Run complex analysis with tool-use loop. When Claude returns tool_use blocks,
    execute the matching MCP tool handler and send results back until Claude responds with text.
    """
    import anthropic
    start = time.perf_counter()
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    system = (
        f"You are a contract analyst. Perform {analysis_type} analysis on the following contract. "
        "You may use the provided tools to query organizational structure, signing authority, "
        "wiki templates, or counterparty details. Return a structured JSON result appropriate for the analysis type."
    )
    tool_defs = [t["definition"] for t in tools]
    messages: list[dict] = [{"role": "user", "content": contract_text[:80000]}]

    total_input_tokens = 0
    total_output_tokens = 0
    max_rounds = 10
    round_count = 0

    while round_count < max_rounds:
        round_count += 1
        message = await client.messages.create(
            model=settings.ai_agent_model,
            max_tokens=4096,
            system=system,
            messages=messages,
            tools=tool_defs if tool_defs else None,
        )

        if message.usage:
            total_input_tokens += message.usage.input_tokens
            total_output_tokens += message.usage.output_tokens

        tool_use_blocks = []
        text_block = None
        for block in (message.content or []):
            if getattr(block, "type", None) == "tool_use":
                tool_use_blocks.append(block)
            elif hasattr(block, "text") and block.text:
                text_block = block

        if text_block is not None and not tool_use_blocks:
            elapsed_ms = int((time.perf_counter() - start) * 1000)
            result_dict = {"summary": "", "confidence": 0.8}
            try:
                result_dict = json.loads(text_block.text)
            except json.JSONDecodeError:
                result_dict = {"raw": text_block.text}
            usage = AnalysisUsage(
                input_tokens=total_input_tokens,
                output_tokens=total_output_tokens,
                cost_usd=0.0,
                processing_time_ms=elapsed_ms,
                model_used=settings.ai_agent_model,
            )
            return result_dict, usage

        if not tool_use_blocks:
            elapsed_ms = int((time.perf_counter() - start) * 1000)
            usage = AnalysisUsage(
                input_tokens=total_input_tokens,
                output_tokens=total_output_tokens,
                cost_usd=0.0,
                processing_time_ms=elapsed_ms,
                model_used=settings.ai_agent_model,
            )
            return {"summary": "", "confidence": 0.8}, usage

        tool_results = []
        for block in tool_use_blocks:
            tool_name = getattr(block, "name", None) or ""
            tool_id = getattr(block, "id", "")
            tool_input = getattr(block, "input", None) or {}
            if isinstance(tool_input, str):
                try:
                    tool_input = json.loads(tool_input)
                except json.JSONDecodeError:
                    tool_input = {}
            content = _run_tool(tools, tool_name, tool_input)
            tool_results.append({
                "type": "tool_result",
                "tool_use_id": tool_id,
                "content": content,
            })

        messages.append({"role": "assistant", "content": message.content})
        messages.append({"role": "user", "content": tool_results})

    elapsed_ms = int((time.perf_counter() - start) * 1000)
    usage = AnalysisUsage(
        input_tokens=total_input_tokens,
        output_tokens=total_output_tokens,
        cost_usd=0.0,
        processing_time_ms=elapsed_ms,
        model_used=settings.ai_agent_model,
    )
    return {"summary": "", "confidence": 0.8, "error": "Max tool-use rounds reached"}, usage
