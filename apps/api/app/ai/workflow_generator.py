import json

import anthropic
import structlog

from app.ai.mcp_tools import get_tools
from app.config import settings
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
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
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
        response = await client.messages.create(
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
            parsed = json.loads(text_content)
            stages = [WorkflowStage(**s) for s in parsed.get("stages", [])]
            errors = validate_template(stages)

            if errors:
                logger.info("ai_workflow_validation_failed", errors=errors)
                messages.append({"role": "assistant", "content": response.content})
                messages.append(
                    {
                        "role": "user",
                        "content": f"The generated workflow has validation errors: {errors}. Please fix these issues and return a corrected JSON.",
                    }
                )
                retry = await client.messages.create(
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
                "usage": {"input_tokens": total_input, "output_tokens": total_output},
            }
        break

    raise ValueError("Failed to generate workflow after maximum attempts")
