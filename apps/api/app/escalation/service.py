import structlog
from datetime import datetime, timezone

from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser

logger = structlog.get_logger()


def list_rules(supabase: Client, template_id: str) -> list[dict]:
    result = (
        supabase.table("escalation_rules")
        .select("*")
        .eq("workflow_template_id", template_id)
        .order("stage_name")
        .order("tier")
        .execute()
    )
    return result.data


async def create_rule(supabase: Client, template_id: str, body, actor: CurrentUser) -> dict:
    data = body.model_dump(exclude_none=True)
    data["workflow_template_id"] = template_id
    result = supabase.table("escalation_rules").insert(data).execute()
    await audit_log(
        supabase,
        action="escalation_rule_created",
        resource_type="escalation_rule",
        resource_id=result.data[0]["id"],
        actor=actor,
    )
    return result.data[0]


async def update_rule(supabase: Client, rule_id: str, body, actor: CurrentUser) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("escalation_rules").update(data).eq("id", rule_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="escalation_rule_updated",
            resource_type="escalation_rule",
            resource_id=rule_id,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def delete_rule(supabase: Client, rule_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("escalation_rules").delete().eq("id", rule_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="escalation_rule_deleted",
            resource_type="escalation_rule",
            resource_id=rule_id,
            actor=actor,
        )
    return bool(result.data)


def list_active_escalations(supabase: Client, limit: int = 50, offset: int = 0) -> tuple[list[dict], int]:
    result = (
        supabase.table("escalation_events")
        .select(
            "*, contracts(id, title, workflow_state), workflow_instances(id, current_stage)",
            count="exact",
        )
        .is_("resolved_at", "null")
        .order("escalated_at", desc=True)
        .range(offset, offset + limit - 1)
        .execute()
    )
    return result.data, result.count or 0


async def resolve_escalation(supabase: Client, event_id: str, actor: CurrentUser) -> dict | None:
    result = (
        supabase.table("escalation_events")
        .update(
            {
                "resolved_at": datetime.now(timezone.utc).isoformat(),
                "resolved_by": actor.id,
            }
        )
        .eq("id", event_id)
        .is_("resolved_at", "null")
        .execute()
    )
    if result.data:
        await audit_log(
            supabase,
            action="escalation_resolved",
            resource_type="escalation_event",
            resource_id=event_id,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def check_sla_breaches(supabase: Client) -> int:
    instances = (
        supabase.table("workflow_instances")
        .select("*, workflow_templates(id), contracts(id)")
        .eq("state", "active")
        .execute()
    )

    created_count = 0
    now = datetime.now(timezone.utc)

    for instance in instances.data or []:
        template_id = instance.get("template_id")
        current_stage = instance.get("current_stage")
        if not template_id or not current_stage:
            continue

        last_action = (
            supabase.table("workflow_stage_actions")
            .select("created_at")
            .eq("instance_id", instance["id"])
            .order("created_at", desc=True)
            .limit(1)
            .execute()
        )

        stage_entered_at = datetime.fromisoformat(
            last_action.data[0]["created_at"] if last_action.data else instance["started_at"]
        )
        if stage_entered_at.tzinfo is None:
            stage_entered_at = stage_entered_at.replace(tzinfo=timezone.utc)

        hours_in_stage = (now - stage_entered_at).total_seconds() / 3600

        rules = (
            supabase.table("escalation_rules")
            .select("*")
            .eq("workflow_template_id", template_id)
            .eq("stage_name", current_stage)
            .order("tier")
            .execute()
        )

        for rule in rules.data or []:
            if hours_in_stage >= rule["sla_breach_hours"]:
                existing = (
                    supabase.table("escalation_events")
                    .select("id")
                    .eq("workflow_instance_id", instance["id"])
                    .eq("rule_id", rule["id"])
                    .is_("resolved_at", "null")
                    .execute()
                )

                if not existing.data:
                    supabase.table("escalation_events").insert(
                        {
                            "workflow_instance_id": instance["id"],
                            "rule_id": rule["id"],
                            "contract_id": instance["contract_id"],
                            "stage_name": current_stage,
                            "tier": rule["tier"],
                        }
                    ).execute()

                    supabase.table("notifications").insert(
                        {
                            "recipient_email": rule.get("escalate_to_user_id"),
                            "channel": "email",
                            "subject": f"CCRS Escalation (Tier {rule['tier']}): SLA breach on stage '{current_stage}'",
                            "body": (
                                f"Workflow instance {instance['id']} has breached SLA at stage '{current_stage}'. "
                                f"Hours in stage: {hours_in_stage:.1f}. Threshold: {rule['sla_breach_hours']}h."
                            ),
                            "related_resource_type": "workflow_instance",
                            "related_resource_id": instance["id"],
                            "status": "pending",
                        }
                    ).execute()

                    created_count += 1

    logger.info("sla_check_completed", escalations_created=created_count)
    return created_count
import structlog
from datetime import datetime, timezone

from supabase import Client

from app.auth.models import CurrentUser
from app.audit.service import audit_log

logger = structlog.get_logger()


def list_rules(supabase: Client, template_id: str) -> list[dict]:
    result = (
        supabase.table("escalation_rules")
        .select("*")
        .eq("workflow_template_id", template_id)
        .order("stage_name")
        .order("tier")
        .execute()
    )
    return result.data


async def create_rule(supabase: Client, template_id: str, body, actor: CurrentUser) -> dict:
    data = body.model_dump(exclude_none=True)
    data["workflow_template_id"] = template_id
    result = supabase.table("escalation_rules").insert(data).execute()
    await audit_log(
        supabase,
        action="escalation_rule_created",
        resource_type="escalation_rule",
        resource_id=result.data[0]["id"],
        actor=actor,
    )
    return result.data[0]


async def update_rule(supabase: Client, rule_id: str, body, actor: CurrentUser) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("escalation_rules").update(data).eq("id", rule_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="escalation_rule_updated",
            resource_type="escalation_rule",
            resource_id=rule_id,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def delete_rule(supabase: Client, rule_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("escalation_rules").delete().eq("id", rule_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="escalation_rule_deleted",
            resource_type="escalation_rule",
            resource_id=rule_id,
            actor=actor,
        )
    return bool(result.data)


def list_active_escalations(supabase: Client, limit: int = 50, offset: int = 0) -> tuple[list[dict], int]:
    result = (
        supabase.table("escalation_events")
        .select(
            "*, contracts(id, title, workflow_state), workflow_instances(id, current_stage)",
            count="exact",
        )
        .is_("resolved_at", "null")
        .order("escalated_at", desc=True)
        .range(offset, offset + limit - 1)
        .execute()
    )
    return result.data, result.count or 0


async def resolve_escalation(supabase: Client, event_id: str, actor: CurrentUser) -> dict | None:
    result = (
        supabase.table("escalation_events")
        .update(
            {
                "resolved_at": datetime.now(timezone.utc).isoformat(),
                "resolved_by": actor.id,
            }
        )
        .eq("id", event_id)
        .is_("resolved_at", "null")
        .execute()
    )
    if result.data:
        await audit_log(
            supabase,
            action="escalation_resolved",
            resource_type="escalation_event",
            resource_id=event_id,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def check_sla_breaches(supabase: Client) -> int:
    instances = (
        supabase.table("workflow_instances")
        .select("*, workflow_templates(id), contracts(id)")
        .eq("state", "active")
        .execute()
    )

    created_count = 0
    now = datetime.now(timezone.utc)

    for instance in instances.data or []:
        template_id = instance.get("template_id")
        current_stage = instance.get("current_stage")
        if not template_id or not current_stage:
            continue

        last_action = (
            supabase.table("workflow_stage_actions")
            .select("created_at")
            .eq("instance_id", instance["id"])
            .order("created_at", desc=True)
            .limit(1)
            .execute()
        )

        stage_entered_at = datetime.fromisoformat(
            last_action.data[0]["created_at"] if last_action.data else instance["started_at"]
        )
        if stage_entered_at.tzinfo is None:
            stage_entered_at = stage_entered_at.replace(tzinfo=timezone.utc)

        hours_in_stage = (now - stage_entered_at).total_seconds() / 3600

        rules = (
            supabase.table("escalation_rules")
            .select("*")
            .eq("workflow_template_id", template_id)
            .eq("stage_name", current_stage)
            .order("tier")
            .execute()
        )

        for rule in rules.data or []:
            if hours_in_stage >= rule["sla_breach_hours"]:
                existing = (
                    supabase.table("escalation_events")
                    .select("id")
                    .eq("workflow_instance_id", instance["id"])
                    .eq("rule_id", rule["id"])
                    .is_("resolved_at", "null")
                    .execute()
                )

                if not existing.data:
                    supabase.table("escalation_events").insert(
                        {
                            "workflow_instance_id": instance["id"],
                            "rule_id": rule["id"],
                            "contract_id": instance["contract_id"],
                            "stage_name": current_stage,
                            "tier": rule["tier"],
                        }
                    ).execute()

                    supabase.table("notifications").insert(
                        {
                            "recipient_email": rule.get("escalate_to_user_id"),
                            "channel": "email",
                            "subject": f"CCRS Escalation (Tier {rule['tier']}): SLA breach on stage '{current_stage}'",
                            "body": (
                                f"Workflow instance {instance['id']} has breached SLA at stage '{current_stage}'. "
                                f"Hours in stage: {hours_in_stage:.1f}. Threshold: {rule['sla_breach_hours']}h."
                            ),
                            "related_resource_type": "workflow_instance",
                            "related_resource_id": instance["id"],
                            "status": "pending",
                        }
                    ).execute()

                    created_count += 1

    logger.info("sla_check_completed", escalations_created=created_count)
    return created_count
