from uuid import UUID

from datetime import datetime, timezone

from fastapi import APIRouter, Depends, File, Form, HTTPException, Query, Response, UploadFile

from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.contracts.schemas import UpdateContractInput
from app.contracts.service import (
    ALLOWED_MIME,
    BUCKET,
    IMMUTABLE_STATES,
    _check_counterparty_active,
    audit_download,
    delete_contract,
    get_by_id,
    list_contracts,
    update_contract,
    upload_contract,
)
from app.deps import get_supabase
from app.schemas.responses import ContractOut
from supabase import Client

router = APIRouter(tags=["contracts"])


@router.post(
    "/contracts/upload",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
    response_model=ContractOut,
)
async def contract_upload(
    file: UploadFile = File(...),
    region_id: str = Form(..., alias="regionId"),
    entity_id: str = Form(..., alias="entityId"),
    project_id: str = Form(..., alias="projectId"),
    counterparty_id: str = Form(..., alias="counterpartyId"),
    contract_type: str = Form(..., alias="contractType"),
    title: str | None = Form(None, alias="title"),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    rid, eid, pid, cpid = UUID(region_id), UUID(entity_id), UUID(project_id), UUID(counterparty_id)
    ok, status, reason = _check_counterparty_active(supabase, cpid)
    if not ok:
        raise HTTPException(
            status_code=400,
            detail=f"Cannot create contract: counterparty is {status}. Reason: {reason}",
        )
    content_type = (file.content_type or "").strip().lower()
    if content_type not in ALLOWED_MIME:
        raise HTTPException(
            status_code=400,
            detail="Only PDF and DOCX files are allowed",
        )
    ts = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    safe_name = (file.filename or "document").split("/")[-1]
    storage_path = f"{rid}/{eid}/{pid}/{ts}-{safe_name}"
    body = await file.read()
    supabase.storage.from_(BUCKET).upload(
        path=storage_path,
        file=body,
        file_options={"content-type": content_type, "upsert": "false"},
    )
    data = upload_contract(
        supabase,
        region_id=rid,
        entity_id=eid,
        project_id=pid,
        counterparty_id=cpid,
        contract_type=contract_type,
        title=title,
        storage_path=storage_path,
        file_name=safe_name,
        actor=user,
    )
    from app.audit.service import audit_log  # noqa: E402
    await audit_log(
        supabase,
        action="contract.upload",
        resource_type="contract",
        resource_id=data.get("id"),
        details={"title": data.get("title"), "storage_path": storage_path},
        actor=user,
    )
    return data


@router.get("/contracts", response_model=list[ContractOut])
async def contract_list(
    response: Response,
    q: str | None = None,
    region_id: UUID | None = Query(None, alias="regionId"),
    entity_id: UUID | None = Query(None, alias="entityId"),
    project_id: UUID | None = Query(None, alias="projectId"),
    contract_type: str | None = Query(None, alias="contractType"),
    workflow_state: str | None = Query(None, alias="workflowState"),
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_contracts(
        supabase,
        q=q,
        region_id=region_id,
        entity_id=entity_id,
        project_id=project_id,
        contract_type=contract_type,
        workflow_state=workflow_state,
        limit=limit,
        offset=offset,
    )
    response.headers["X-Total-Count"] = str(total)
    return items


@router.get("/contracts/{id}", response_model=ContractOut)
async def contract_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Contract not found")
    from app.contract_links.service import list_linked

    row["linked_contracts"] = list_linked(supabase, id)
    return row


@router.get("/contracts/{id}/download-url")
async def contract_download_url(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Contract not found")
    path = row.get("storage_path")
    if not path:
        raise HTTPException(status_code=404, detail="Contract file not found")
    result = supabase.storage.from_(BUCKET).create_signed_url(path, 3600)
    if isinstance(result, dict):
        url = result.get("signedUrl") or result.get("signed_url") or result.get("path")
    else:
        url = getattr(result, "signed_url", None) or getattr(result, "signedUrl", None) or getattr(result, "path", None)
    await audit_download(supabase, str(id), user)
    return {"url": url or ""}


@router.patch(
    "/contracts/{id}",
    dependencies=[Depends(require_roles("System Admin", "Legal"))],
    response_model=ContractOut,
)
async def contract_update(
    id: UUID,
    body: UpdateContractInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Contract not found")
    ws = (row.get("workflow_state") or "").strip().lower()
    if ws in IMMUTABLE_STATES:
        raise HTTPException(status_code=400, detail="Cannot update contract in archived or executed state")
    payload = body.model_dump(exclude_unset=True)
    if not payload:
        return row
    updated = await update_contract(supabase, id, payload, user)
    return updated or row


@router.delete(
    "/contracts/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def contract_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_by_id(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Contract not found")
    ws = (row.get("workflow_state") or "").strip().lower()
    if ws in IMMUTABLE_STATES:
        raise HTTPException(status_code=400, detail="Cannot delete contract in archived or executed state")
    ok = await delete_contract(supabase, id, row.get("storage_path"), user)
    if not ok:
        raise HTTPException(status_code=404, detail="Contract not found")
    return {"ok": True}
