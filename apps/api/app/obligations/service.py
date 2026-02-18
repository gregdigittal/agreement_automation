import structlog
from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.obligations.schemas import CreateObligationInput, UpdateObligationInput

logger = structlog.get_logger()


def list_obligations(
    supabase: Client,
    contract_id: str | None = None,
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list[dict], int]:
    query = supabase.table("obligations_register").select("*", count="exact")
    if contract_id:
        query = query.eq("contract_id", contract_id)
    if status:
        query = query.eq("status", status)
    if obligation_type:
        query = query.eq("obligation_type", obligation_type)
    result = query.order("due_date").range(offset, offset + limit - 1).execute()
    return result.data, result.count or 0


async def create_obligation(
    supabase: Client, contract_id: str, body: CreateObligationInput, actor: CurrentUser
) -> dict:
    data = body.model_dump(exclude_none=True)
    data["contract_id"] = contract_id
    result = supabase.table("obligations_register").insert(data).execute()
    await audit_log(
        supabase,
        action="obligation_created",
        resource_type="obligation",
        resource_id=result.data[0]["id"],
        details=data,
        actor=actor,
    )
    return result.data[0]


async def update_obligation(
    supabase: Client, obligation_id: str, body: UpdateObligationInput, actor: CurrentUser
) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("obligations_register").update(data).eq("id", obligation_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="obligation_updated",
            resource_type="obligation",
            resource_id=obligation_id,
            details=data,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def delete_obligation(supabase: Client, obligation_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("obligations_register").delete().eq("id", obligation_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="obligation_deleted",
            resource_type="obligation",
            resource_id=obligation_id,
            actor=actor,
        )
    return bool(result.data)
import structlog
from supabase import Client

from app.auth.models import CurrentUser
from app.audit.service import audit_log
from app.obligations.schemas import CreateObligationInput, UpdateObligationInput

logger = structlog.get_logger()


def list_obligations(
    supabase: Client,
    contract_id: str | None = None,
    status: str | None = None,
    obligation_type: str | None = None,
    limit: int = 50,
    offset: int = 0,
) -> tuple[list[dict], int]:
    query = supabase.table("obligations_register").select("*", count="exact")
    if contract_id:
        query = query.eq("contract_id", contract_id)
    if status:
        query = query.eq("status", status)
    if obligation_type:
        query = query.eq("obligation_type", obligation_type)
    result = query.order("due_date").range(offset, offset + limit - 1).execute()
    return result.data, result.count or 0


async def create_obligation(
    supabase: Client, contract_id: str, body: CreateObligationInput, actor: CurrentUser
) -> dict:
    data = body.model_dump(exclude_none=True)
    data["contract_id"] = contract_id
    result = supabase.table("obligations_register").insert(data).execute()
    await audit_log(
        supabase,
        action="obligation_created",
        resource_type="obligation",
        resource_id=result.data[0]["id"],
        details=data,
        actor=actor,
    )
    return result.data[0]


async def update_obligation(
    supabase: Client, obligation_id: str, body: UpdateObligationInput, actor: CurrentUser
) -> dict | None:
    data = body.model_dump(exclude_none=True)
    if not data:
        return None
    result = supabase.table("obligations_register").update(data).eq("id", obligation_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="obligation_updated",
            resource_type="obligation",
            resource_id=obligation_id,
            details=data,
            actor=actor,
        )
    return result.data[0] if result.data else None


async def delete_obligation(supabase: Client, obligation_id: str, actor: CurrentUser) -> bool:
    result = supabase.table("obligations_register").delete().eq("id", obligation_id).execute()
    if result.data:
        await audit_log(
            supabase,
            action="obligation_deleted",
            resource_type="obligation",
            resource_id=obligation_id,
            actor=actor,
        )
    return bool(result.data)
