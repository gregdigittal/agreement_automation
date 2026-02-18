from datetime import datetime, timezone
from io import BytesIO
from tempfile import NamedTemporaryFile
from uuid import UUID

from docxtpl import DocxTemplate
from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser

CONTRACTS_BUCKET = "contracts"


def _download_template(supabase: Client, path: str) -> bytes:
    result = supabase.storage.from_("wiki-contracts").download(path)
    if isinstance(result, bytes):
        return result
    if isinstance(result, dict) and "data" in result:
        return result["data"]
    return result


def _render_docx(template_bytes: bytes, context: dict) -> bytes:
    with NamedTemporaryFile(suffix=".docx") as temp:
        temp.write(template_bytes)
        temp.flush()
        doc = DocxTemplate(temp.name)
        doc.render(context)
        out = BytesIO()
        doc.save(out)
        return out.getvalue()


async def generate_merchant_agreement(
    supabase: Client,
    *,
    template_id: UUID,
    vendor_name: str,
    merchant_fee: str | None,
    region_id: UUID,
    entity_id: UUID,
    project_id: UUID,
    counterparty_id: UUID,
    region_terms: dict | None,
    actor: CurrentUser | None,
) -> dict:
    template = (
        supabase.table("wiki_contracts").select("*").eq("id", str(template_id)).execute().data
    )
    if not template:
        raise ValueError("Template not found")
    template_row = template[0]
    if not template_row.get("storage_path"):
        raise ValueError("Template file not uploaded")

    template_bytes = _download_template(supabase, template_row["storage_path"])
    rendered = _render_docx(
        template_bytes,
        {
            "vendor_name": vendor_name,
            "merchant_fee": merchant_fee,
            "region_terms": region_terms or {},
        },
    )

    timestamp = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    storage_path = f"merchant-agreements/{region_id}/{timestamp}-{vendor_name}.docx"
    supabase.storage.from_(CONTRACTS_BUCKET).upload(
        path=storage_path,
        file=rendered,
        file_options={"content-type": "application/vnd.openxmlformats-officedocument.wordprocessingml.document", "upsert": "false"},
    )

    contract_row = (
        supabase.table("contracts")
        .insert(
            {
                "region_id": str(region_id),
                "entity_id": str(entity_id),
                "project_id": str(project_id),
                "counterparty_id": str(counterparty_id),
                "contract_type": "Merchant",
                "title": f"Merchant Agreement - {vendor_name}",
                "workflow_state": "draft",
                "storage_path": storage_path,
                "file_name": storage_path.split("/")[-1],
                "created_by": actor.id if actor else None,
            }
        )
        .execute()
    )
    contract = contract_row.data[0] if contract_row.data else {}
    supabase.table("merchant_agreement_inputs").insert(
        {
            "contract_id": contract.get("id"),
            "template_id": str(template_id),
            "vendor_name": vendor_name,
            "merchant_fee": merchant_fee,
            "region_terms": region_terms or {},
            "generated_at": datetime.now(timezone.utc).isoformat(),
        }
    ).execute()
    await audit_log(
        supabase,
        action="merchant_agreement.generate",
        resource_type="contract",
        resource_id=contract.get("id"),
        actor=actor,
    )
    return contract


async def tito_validate(
    supabase: Client,
    *,
    vendor: str | None,
    entity_id: str | None,
    region_id: str | None,
    project_id: str | None,
    actor: CurrentUser | None,
) -> dict:
    q = supabase.table("contracts").select("*").eq("contract_type", "Merchant").eq("signing_status", "completed")
    if entity_id:
        q = q.eq("entity_id", entity_id)
    if region_id:
        q = q.eq("region_id", region_id)
    if project_id:
        q = q.eq("project_id", project_id)
    r = q.execute()
    match = r.data[0] if r.data else None
    await audit_log(
        supabase,
        action="tito.validate",
        resource_type="contract",
        resource_id=match.get("id") if match else None,
        details={"vendor": vendor, "entity_id": entity_id, "region_id": region_id, "project_id": project_id},
        actor=actor,
    )
    if not match:
        return {"valid": False, "contract_id": None, "signed_at": None, "status": None}
    return {"valid": True, "contract_id": match.get("id"), "signed_at": match.get("updated_at"), "status": match.get("signing_status")}
