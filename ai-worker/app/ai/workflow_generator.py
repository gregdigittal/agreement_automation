"""Generate workflow template stages using AI."""
import json
import anthropic
from app.config import settings


async def generate_workflow(
    description: str,
    region_id: str | None = None,
    entity_id: str | None = None,
    project_id: str | None = None,
):
    """Generate workflow stages from a description. Returns dict with 'stages' list."""
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    prompt = f"Generate a contract approval workflow with stages (name, order, approver_role, sla_hours, required). Description: {description}"
    if region_id:
        prompt += f" Region ID: {region_id}"
    if entity_id:
        prompt += f" Entity ID: {entity_id}"
    if project_id:
        prompt += f" Project ID: {project_id}"
    prompt += "\nReturn only a JSON object with a 'stages' array. Each stage: name, order (int), approver_role, sla_hours (int), required (bool)."
    msg = await client.messages.create(
        model=settings.ai_model,
        max_tokens=2048,
        messages=[{"role": "user", "content": prompt}],
    )
    out = msg.content[0].text if msg.content and hasattr(msg.content[0], "text") else "{}"
    try:
        return json.loads(out)
    except json.JSONDecodeError:
        return {"stages": [{"name": "Review", "order": 1, "approver_role": "legal", "sla_hours": 24, "required": True}]}
