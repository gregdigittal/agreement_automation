from datetime import datetime, timezone
from uuid import UUID

import httpx
from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser
from app.config import settings


async def send_to_sign(
    supabase: Client,
    contract_id: UUID,
    actor: CurrentUser,
) -> dict:
    contract_r = supabase.table("contracts").select("*").eq("id", str(contract_id)).execute()
    if not contract_r.data:
        raise ValueError("Contract not found")
    contract = contract_r.data[0]

    if "sign" not in (contract.get("workflow_state") or "").lower():
        raise ValueError("Contract is not in a signing stage")

    signing_rules = supabase.table("signing_authority").select("*").execute().data or []
    signing_rules = [
        r
        for r in signing_rules
        if r.get("entity_id") == contract.get("entity_id")
        and (r.get("project_id") in (None, contract.get("project_id")))
    ]
    if not signing_rules:
        raise ValueError("No signing authority configured")

    envelope = {
        "contract_id": str(contract_id),
        "boldsign_document_id": None,
        "status": "sent",
        "signing_order": "sequential",
        "signers": [],
        "sent_at": datetime.now(timezone.utc).isoformat(),
    }
    row = supabase.table("boldsign_envelopes").insert(envelope).execute()
    if settings.boldsign_api_key and settings.boldsign_api_url:
        try:
            async with httpx.AsyncClient(timeout=10) as client:
                await client.post(
                    f"{settings.boldsign_api_url}/send",
                    headers={"X-API-KEY": settings.boldsign_api_key},
                    json={"contract_id": str(contract_id)},
                )
        except Exception:
            pass

    supabase.table("contracts").update({"signing_status": "sent"}).eq("id", str(contract_id)).execute()
    await audit_log(
        supabase,
        action="boldsign.send",
        resource_type="boldsign_envelope",
        resource_id=row.data[0]["id"] if row.data else None,
        actor=actor,
    )
    return row.data[0] if row.data else {}


def get_signing_status(supabase: Client, contract_id: UUID) -> dict | None:
    r = (
        supabase.table("boldsign_envelopes")
        .select("*")
        .eq("contract_id", str(contract_id))
        .order("created_at", desc=True)
        .limit(1)
        .execute()
    )
    return r.data[0] if r.data else None


async def handle_webhook(supabase: Client, payload: dict) -> None:
    status = payload.get("status") or payload.get("event")
    document_id = payload.get("document_id") or payload.get("data", {}).get("document_id")
    if not document_id:
        return
    r = supabase.table("boldsign_envelopes").select("*").eq("boldsign_document_id", document_id).execute()
    if not r.data:
        return
    envelope = r.data[0]
    supabase.table("boldsign_envelopes").update({"status": status, "webhook_payload": payload}).eq(
        "id", envelope["id"]
    ).execute()
    if status == "completed":
        supabase.table("contracts").update({"signing_status": "completed", "workflow_state": "executed"}).eq(
            "id", envelope["contract_id"]
        ).execute()
    await audit_log(
        supabase,
        action="boldsign.webhook",
        resource_type="boldsign_envelope",
        resource_id=envelope["id"],
        details={"status": status},
        actor=None,
    )
