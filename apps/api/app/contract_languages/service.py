import structlog
from fastapi import UploadFile
from supabase import Client

from app.audit.service import audit_log
from app.auth.models import CurrentUser

logger = structlog.get_logger()

ALLOWED_MIME_TYPES = {
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
}


def list_languages(supabase: Client, contract_id: str) -> list[dict]:
    result = (
        supabase.table("contract_languages")
        .select("*")
        .eq("contract_id", contract_id)
        .order("is_primary", desc=True)
        .execute()
    )
    return result.data


async def attach_language(
    supabase: Client,
    contract_id: str,
    language_code: str,
    is_primary: bool,
    file: UploadFile,
    actor: CurrentUser,
) -> dict:
    if file.content_type not in ALLOWED_MIME_TYPES:
        raise ValueError(f"Unsupported file type: {file.content_type}")

    existing = (
        supabase.table("contract_languages")
        .select("id")
        .eq("contract_id", contract_id)
        .eq("language_code", language_code)
        .execute()
    )
    if existing.data:
        raise ValueError("Language version already exists for this contract")

    storage_path = f"contracts/{contract_id}/languages/{language_code}/{file.filename}"
    file_bytes = await file.read()
    supabase.storage.from_("contracts").upload(
        path=storage_path,
        file=file_bytes,
        file_options={"content-type": file.content_type},
    )

    if is_primary:
        supabase.table("contract_languages").update({"is_primary": False}).eq(
            "contract_id", contract_id
        ).execute()

    record = (
        supabase.table("contract_languages")
        .insert(
            {
                "contract_id": contract_id,
                "language_code": language_code,
                "is_primary": is_primary,
                "storage_path": storage_path,
                "file_name": file.filename,
            }
        )
        .execute()
    )

    await audit_log(
        supabase,
        action="language_version_attached",
        resource_type="contract",
        resource_id=contract_id,
        details={"language_code": language_code},
        actor=actor,
    )
    return record.data[0]


async def delete_language(supabase: Client, lang_id: str, actor: CurrentUser) -> bool:
    record = (
        supabase.table("contract_languages").select("*").eq("id", lang_id).single().execute()
    )
    if not record.data:
        return False

    if record.data.get("storage_path"):
        try:
            supabase.storage.from_("contracts").remove([record.data["storage_path"]])
        except Exception as e:
            logger.warning("language_file_delete_failed", error=str(e))

    supabase.table("contract_languages").delete().eq("id", lang_id).execute()
    await audit_log(
        supabase,
        action="language_version_deleted",
        resource_type="contract_language",
        resource_id=lang_id,
        actor=actor,
    )
    return True


def get_download_url(supabase: Client, lang_id: str) -> str | None:
    record = (
        supabase.table("contract_languages").select("*").eq("id", lang_id).single().execute()
    )
    if not record.data or not record.data.get("storage_path"):
        return None
    result = supabase.storage.from_("contracts").create_signed_url(record.data["storage_path"], 3600)
    if isinstance(result, dict):
        return result.get("signedUrl") or result.get("signed_url") or result.get("path")
    return getattr(result, "signed_url", None) or getattr(result, "signedUrl", None)
import structlog
from fastapi import UploadFile
from supabase import Client

from app.auth.models import CurrentUser
from app.audit.service import audit_log

logger = structlog.get_logger()

ALLOWED_MIME_TYPES = {
    "application/pdf",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
}


def list_languages(supabase: Client, contract_id: str) -> list[dict]:
    result = (
        supabase.table("contract_languages")
        .select("*")
        .eq("contract_id", contract_id)
        .order("is_primary", desc=True)
        .execute()
    )
    rows = result.data or []
    for row in rows:
        if row.get("storage_path"):
            row["download_url"] = _get_signed_url(supabase, row["storage_path"])
    return rows


def _get_signed_url(supabase: Client, path: str) -> str:
    result = supabase.storage.from_("contracts").create_signed_url(path, 3600)
    if isinstance(result, dict):
        return result.get("signedUrl") or result.get("signed_url") or result.get("path") or ""
    return getattr(result, "signed_url", None) or getattr(result, "signedUrl", None) or ""


async def attach_language(
    supabase: Client,
    contract_id: str,
    language_code: str,
    is_primary: bool,
    file: UploadFile,
    actor: CurrentUser,
) -> dict:
    if file.content_type not in ALLOWED_MIME_TYPES:
        raise ValueError(f"Unsupported file type: {file.content_type}")
    existing = (
        supabase.table("contract_languages")
        .select("id")
        .eq("contract_id", contract_id)
        .eq("language_code", language_code)
        .execute()
    )
    if existing.data:
        raise ValueError("Language version already exists for this contract")

    storage_path = f"contracts/{contract_id}/languages/{language_code}/{file.filename}"
    file_bytes = await file.read()
    supabase.storage.from_("contracts").upload(
        storage_path, file_bytes, {"content-type": file.content_type}
    )

    if is_primary:
        supabase.table("contract_languages").update({"is_primary": False}).eq(
            "contract_id", contract_id
        ).execute()

    record = (
        supabase.table("contract_languages")
        .insert(
            {
                "contract_id": contract_id,
                "language_code": language_code,
                "is_primary": is_primary,
                "storage_path": storage_path,
                "file_name": file.filename,
            }
        )
        .execute()
    )

    await audit_log(
        supabase,
        action="language_version_attached",
        resource_type="contract",
        resource_id=contract_id,
        details={"language_code": language_code},
        actor=actor,
    )
    return record.data[0]


async def delete_language(supabase: Client, lang_id: str, actor: CurrentUser) -> bool:
    record = supabase.table("contract_languages").select("*").eq("id", lang_id).single().execute()
    if not record.data:
        return False

    if record.data.get("storage_path"):
        try:
            supabase.storage.from_("contracts").remove([record.data["storage_path"]])
        except Exception as e:
            logger.warning("language_file_delete_failed", error=str(e))

    supabase.table("contract_languages").delete().eq("id", lang_id).execute()
    await audit_log(
        supabase,
        action="language_version_deleted",
        resource_type="contract_language",
        resource_id=lang_id,
        actor=actor,
    )
    return True
