from datetime import datetime, timezone
from uuid import UUID

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.workflows.schemas import CreateWorkflowTemplateInput, StageActionInput, UpdateWorkflowTemplateInput, WorkflowStage
from app.workflows.state_machine import can_actor_act, get_next_stage, validate_template


def _serialize_stages(stages: list[WorkflowStage]) -> list[dict]:
    return [s.model_dump() for s in stages]


async def create_template(
    supabase: Client, body: CreateWorkflowTemplateInput, actor: CurrentUser | None
) -> dict:
    row = (
        supabase.table("workflow_templates")
        .insert(
            {
                "name": body.name,
                "contract_type": body.contract_type,
                "region_id": str(body.region_id) if body.region_id else None,
                "entity_id": str(body.entity_id) if body.entity_id else None,
                "project_id": str(body.project_id) if body.project_id else None,
                "stages": _serialize_stages(body.stages),
                "status": "draft",
                "version": 1,
                "created_by": actor.id if actor else None,
            }
        )
        .execute()
    )
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="workflow_template.create",
        resource_type="workflow_template",
        resource_id=data.get("id"),
        actor=actor,
    )
    return data


def list_templates(
    supabase: Client,
    status: str | None = None,
    contract_type: str | None = None,
    region_id: UUID | None = None,
    entity_id: UUID | None = None,
    project_id: UUID | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list, int]:
    q = (
        supabase.table("workflow_templates")
        .select("*", count="exact")
        .order("created_at", desc=True)
        .range(offset, offset + limit - 1)
    )
    if status:
        q = q.eq("status", status)
    if contract_type:
        q = q.eq("contract_type", contract_type)
    if region_id:
        q = q.eq("region_id", str(region_id))
    if entity_id:
        q = q.eq("entity_id", str(entity_id))
    if project_id:
        q = q.eq("project_id", str(project_id))
    r = q.execute()
    total = getattr(r, "count", None) or len(r.data or [])
    return (r.data or [], total)


def get_template(supabase: Client, id: UUID) -> dict | None:
    r = supabase.table("workflow_templates").select("*").eq("id", str(id)).execute()
    if not r.data:
        return None
    return r.data[0]


def get_template_versions(supabase: Client, template_id: UUID) -> list[dict]:
    audit = (
        supabase.table("audit_log")
        .select("*")
        .eq("resource_type", "workflow_template")
        .eq("resource_id", str(template_id))
        .eq("action", "workflow_template_published")
        .order("created_at", desc=True)
        .execute()
    )
    return audit.data or []


async def update_template(
    supabase: Client, id: UUID, body: UpdateWorkflowTemplateInput, actor: CurrentUser | None
) -> dict | None:
    payload = body.model_dump(exclude_unset=True, by_alias=False)
    if "region_id" in payload and payload["region_id"] is not None:
        payload["region_id"] = str(payload["region_id"])
    if "entity_id" in payload and payload["entity_id"] is not None:
        payload["entity_id"] = str(payload["entity_id"])
    if "project_id" in payload and payload["project_id"] is not None:
        payload["project_id"] = str(payload["project_id"])
    if "stages" in payload and payload["stages"] is not None:
        payload["stages"] = _serialize_stages(payload["stages"])
    if not payload:
        return get_template(supabase, id)
    r = supabase.table("workflow_templates").update(payload).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="workflow_template.update",
        resource_type="workflow_template",
        resource_id=str(id),
        actor=actor,
    )
    return r.data[0]


async def publish_template(
    supabase: Client, id: UUID, actor: CurrentUser | None
) -> dict | None:
    current = get_template(supabase, id)
    if not current:
        return None
    stages = [WorkflowStage(**s) for s in current.get("stages") or []]
    errors = validate_template(stages)
    if errors:
        return {"validation_errors": errors}
    payload = {
        "status": "published",
        "published_at": datetime.now(timezone.utc).isoformat(),
        "version": int(current.get("version") or 1) + 1,
        "validation_errors": None,
    }
    r = supabase.table("workflow_templates").update(payload).eq("id", str(id)).execute()
    if not r.data:
        return None
    await audit_log(
        supabase,
        action="workflow_template_published",
        resource_type="workflow_template",
        resource_id=str(id),
        details={
            "version": payload["version"],
            "stages": current.get("stages"),
        },
        actor=actor,
    )
    return r.data[0]


async def delete_template(supabase: Client, id: UUID, actor: CurrentUser | None) -> bool:
    r = supabase.table("workflow_templates").delete().eq("id", str(id)).execute()
    deleted = bool(r.data)
    if deleted:
        await audit_log(
            supabase,
            action="workflow_template.delete",
            resource_type="workflow_template",
            resource_id=str(id),
            actor=actor,
        )
    return deleted


async def start_workflow(
    supabase: Client, contract_id: UUID, template_id: UUID, actor: CurrentUser | None
) -> dict:
    template = get_template(supabase, template_id)
    if not template or template.get("status") != "published":
        raise ValueError("Template not found or not published")
    stages = [WorkflowStage(**s) for s in template.get("stages") or []]
    if not stages:
        raise ValueError("Template has no stages")
    first_stage = stages[0].name
    row = (
        supabase.table("workflow_instances")
        .insert(
            {
                "contract_id": str(contract_id),
                "template_id": str(template_id),
                "template_version": template.get("version") or 1,
                "current_stage": first_stage,
                "state": "active",
            }
        )
        .execute()
    )
    supabase.table("contracts").update({"workflow_state": first_stage}).eq("id", str(contract_id)).execute()
    data = row.data[0] if row.data else {}
    await audit_log(
        supabase,
        action="workflow_instance.start",
        resource_type="workflow_instance",
        resource_id=data.get("id"),
        actor=actor,
    )
    return data


def get_active_instance(supabase: Client, contract_id: UUID) -> dict | None:
    r = (
        supabase.table("workflow_instances")
        .select("*")
        .eq("contract_id", str(contract_id))
        .eq("state", "active")
        .execute()
    )
    if not r.data:
        return None
    return r.data[0]


def get_instance(supabase: Client, instance_id: UUID) -> dict | None:
    r = supabase.table("workflow_instances").select("*").eq("id", str(instance_id)).execute()
    if not r.data:
        return None
    return r.data[0]


def get_history(supabase: Client, instance_id: UUID) -> list:
    r = (
        supabase.table("workflow_stage_actions")
        .select("*")
        .eq("instance_id", str(instance_id))
        .order("created_at", desc=True)
        .execute()
    )
    return r.data or []


async def record_action(
    supabase: Client,
    *,
    instance_id: UUID,
    stage_name: str,
    input: StageActionInput,
    actor: CurrentUser,
) -> dict:
    instance = get_instance(supabase, instance_id)
    if not instance:
        raise ValueError("Instance not found")
    if instance.get("current_stage") != stage_name:
        raise ValueError("Stage does not match current stage")

    template = get_template(supabase, UUID(instance["template_id"]))
    stages = [WorkflowStage(**s) for s in template.get("stages") or []]
    stage = next((s for s in stages if s.name == stage_name), None)
    if not stage:
        raise ValueError("Stage not found")

    contract = (
        supabase.table("contracts").select("*").eq("id", instance["contract_id"]).execute().data[0]
    )
    signing_rules = supabase.table("signing_authority").select("*").execute().data or []
    signing_rules = [
        r
        for r in signing_rules
        if r.get("entity_id") == contract.get("entity_id")
        and (r.get("project_id") in (None, contract.get("project_id")))
    ]
    if not can_actor_act(stage, actor, signing_rules):
        raise PermissionError("Not authorized for this stage")

    next_stage = get_next_stage(stages, stage_name, input.action)
    if next_stage is None and input.action == "approve":
        supabase.table("workflow_instances").update(
            {"state": "completed", "completed_at": datetime.now(timezone.utc).isoformat()}
        ).eq("id", str(instance_id)).execute()
        supabase.table("contracts").update({"workflow_state": "completed"}).eq(
            "id", instance["contract_id"]
        ).execute()
    else:
        supabase.table("workflow_instances").update({"current_stage": next_stage}).eq(
            "id", str(instance_id)
        ).execute()
        supabase.table("contracts").update({"workflow_state": next_stage}).eq(
            "id", instance["contract_id"]
        ).execute()

    action_row = (
        supabase.table("workflow_stage_actions")
        .insert(
            {
                "instance_id": str(instance_id),
                "stage_name": stage_name,
                "action": input.action,
                "actor_id": actor.id,
                "actor_email": actor.email,
                "comment": input.comment,
                "artifacts": input.artifacts,
            }
        )
        .execute()
    )
    await audit_log(
        supabase,
        action="workflow_stage.action",
        resource_type="workflow_stage_action",
        resource_id=action_row.data[0]["id"] if action_row.data else None,
        actor=actor,
    )
    return action_row.data[0] if action_row.data else {}
