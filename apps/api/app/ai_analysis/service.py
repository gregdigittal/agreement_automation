import structlog
from datetime import datetime, timezone

from supabase import Client

from app.ai.agent_client import analyze_complex
from app.ai.config import get_task_type
from app.ai.messages_client import analyze_summary
from app.auth.models import CurrentUser
from app.audit.service import audit_log

logger = structlog.get_logger()


async def trigger_analysis(
    supabase: Client,
    contract_id: str,
    analysis_type: str,
    actor: CurrentUser,
) -> dict:
    contract = (
        supabase.table("contracts")
        .select("*")
        .eq("id", contract_id)
        .single()
        .execute()
    )
    if not contract.data:
        raise ValueError("Contract not found")

    storage_path = contract.data.get("storage_path")
    if not storage_path:
        raise ValueError("Contract has no uploaded file")

    record = (
        supabase.table("ai_analysis_results")
        .insert(
            {
                "contract_id": contract_id,
                "analysis_type": analysis_type,
                "status": "pending",
            }
        )
        .execute()
    )
    analysis_id = record.data[0]["id"]

    try:
        supabase.table("ai_analysis_results").update({"status": "processing"}).eq(
            "id", analysis_id
        ).execute()

        file_bytes = supabase.storage.from_("contracts").download(storage_path)
        contract_text = _extract_text(file_bytes, contract.data.get("file_name", ""))

        task_type = get_task_type(analysis_type)
        if task_type == "simple":
            result, usage = await analyze_summary(contract_text)
            result_dict = result.model_dump()
        else:
            result_dict, usage = await analyze_complex(
                analysis_type, contract_text, contract_id, supabase
            )

        supabase.table("ai_analysis_results").update(
            {
                "status": "completed",
                "result": result_dict,
                "model_used": usage.model_used,
                "token_usage_input": usage.input_tokens,
                "token_usage_output": usage.output_tokens,
                "cost_usd": usage.cost_usd,
                "processing_time_ms": usage.processing_time_ms,
                "confidence_score": result_dict.get("overall_risk_score")
                or result_dict.get("confidence"),
            }
        ).eq("id", analysis_id).execute()

        if analysis_type == "extraction" and "fields" in result_dict:
            for field in result_dict["fields"]:
                supabase.table("ai_extracted_fields").insert(
                    {
                        "contract_id": contract_id,
                        "analysis_id": analysis_id,
                        **field,
                    }
                ).execute()

        if analysis_type == "obligations" and "obligations" in result_dict:
            for obl in result_dict["obligations"]:
                supabase.table("obligations_register").insert(
                    {
                        "contract_id": contract_id,
                        "analysis_id": analysis_id,
                        **obl,
                    }
                ).execute()

        await audit_log(
            supabase,
            action="ai_analysis_completed",
            resource_type="contract",
            resource_id=contract_id,
            details={"analysis_type": analysis_type, "cost_usd": usage.cost_usd},
            actor=actor,
        )

        return (
            supabase.table("ai_analysis_results")
            .select("*")
            .eq("id", analysis_id)
            .single()
            .execute()
            .data
        )
    except Exception as e:
        logger.error("ai_analysis_failed", analysis_id=analysis_id, error=str(e))
        supabase.table("ai_analysis_results").update(
            {"status": "failed", "error_message": str(e)}
        ).eq("id", analysis_id).execute()
        raise


def _extract_text(file_bytes: bytes, file_name: str) -> str:
    if file_name.lower().endswith(".pdf"):
        try:
            import fitz

            doc = fitz.open(stream=file_bytes, filetype="pdf")
            return "\n".join(page.get_text() for page in doc)
        except Exception:
            return file_bytes.decode("utf-8", errors="ignore")
    if file_name.lower().endswith((".docx", ".doc")):
        try:
            import docx
            from io import BytesIO

            doc = docx.Document(BytesIO(file_bytes))
            return "\n".join(p.text for p in doc.paragraphs)
        except Exception:
            return file_bytes.decode("utf-8", errors="ignore")
    return file_bytes.decode("utf-8", errors="ignore")


async def get_analyses(supabase: Client, contract_id: str) -> list[dict]:
    result = (
        supabase.table("ai_analysis_results")
        .select("*")
        .eq("contract_id", contract_id)
        .order("created_at", desc=True)
        .execute()
    )
    return result.data


async def get_extracted_fields(supabase: Client, contract_id: str) -> list[dict]:
    result = (
        supabase.table("ai_extracted_fields")
        .select("*")
        .eq("contract_id", contract_id)
        .order("field_name")
        .execute()
    )
    return result.data


async def verify_field(supabase: Client, field_id: str, actor: CurrentUser) -> dict | None:
    result = supabase.table("ai_extracted_fields").update(
        {
            "is_verified": True,
            "verified_by": actor.id,
            "verified_at": datetime.now(timezone.utc).isoformat(),
        }
    ).eq("id", field_id).execute()
    await audit_log(
        supabase,
        action="ai_field_verified",
        resource_type="ai_extracted_field",
        resource_id=field_id,
        actor=actor,
    )
    return result.data[0] if result.data else None


async def correct_field(
    supabase: Client, field_id: str, new_value: str, actor: CurrentUser
) -> dict | None:
    result = supabase.table("ai_extracted_fields").update(
        {
            "field_value": new_value,
            "is_verified": True,
            "verified_by": actor.id,
            "verified_at": datetime.now(timezone.utc).isoformat(),
        }
    ).eq("id", field_id).execute()
    await audit_log(
        supabase,
        action="ai_field_corrected",
        resource_type="ai_extracted_field",
        resource_id=field_id,
        details={"new_value": new_value},
        actor=actor,
    )
    return result.data[0] if result.data else None
