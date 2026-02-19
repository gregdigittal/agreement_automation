from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, Response

from app.ai.workflow_generator import generate_workflow
from app.audit.service import audit_log
from app.auth.dependencies import get_current_user, require_roles
from app.auth.models import CurrentUser
from app.deps import get_supabase
from app.workflows.schemas import (
    CreateWorkflowTemplateInput,
    GenerateWorkflowInput,
    StageActionInput,
    StartWorkflowInput,
    UpdateWorkflowTemplateInput,
)
from app.workflows.service import (
    create_template,
    delete_template,
    get_active_instance,
    get_history,
    get_template,
    get_template_versions,
    list_templates,
    publish_template,
    record_action,
    start_workflow,
    update_template,
)
from supabase import Client
from app.schemas.responses import AuditLogOut, WorkflowTemplateOut

router = APIRouter(tags=["workflows"])


@router.post(
    "/workflow-templates",
    dependencies=[Depends(require_roles("System Admin"))],
    response_model=WorkflowTemplateOut,
)
async def workflow_template_create(
    body: CreateWorkflowTemplateInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return await create_template(supabase, body, user)


@router.get("/workflow-templates", response_model=list[WorkflowTemplateOut])
async def workflow_template_list(
    response: Response,
    status: str | None = None,
    contract_type: str | None = Query(None, alias="contractType"),
    region_id: UUID | None = Query(None, alias="regionId"),
    entity_id: UUID | None = Query(None, alias="entityId"),
    project_id: UUID | None = Query(None, alias="projectId"),
    limit: int = Query(50, ge=1, le=500),
    offset: int = Query(0, ge=0),
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    items, total = list_templates(
        supabase,
        status=status,
        contract_type=contract_type,
        region_id=region_id,
        entity_id=entity_id,
        project_id=project_id,
        limit=limit,
        offset=offset,
    )
    response.headers["X-Total-Count"] = str(total)
    return items


@router.get("/workflow-templates/{id}", response_model=WorkflowTemplateOut)
async def workflow_template_get(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_template(supabase, id)
    if not row:
        raise HTTPException(status_code=404, detail="Workflow template not found")
    return row


@router.get("/workflow-templates/{id}/versions", response_model=list[AuditLogOut])
async def workflow_template_versions(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return get_template_versions(supabase, id)


@router.patch(
    "/workflow-templates/{id}",
    dependencies=[Depends(require_roles("System Admin"))],
    response_model=WorkflowTemplateOut,
)
async def workflow_template_update(
    id: UUID,
    body: UpdateWorkflowTemplateInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = await update_template(supabase, id, body, user)
    if not row:
        raise HTTPException(status_code=404, detail="Workflow template not found")
    return row


@router.post(
    "/workflow-templates/{id}/publish",
    dependencies=[Depends(require_roles("System Admin"))],
    response_model=WorkflowTemplateOut,
)
async def workflow_template_publish(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = await publish_template(supabase, id, user)
    if result is None:
        raise HTTPException(status_code=404, detail="Workflow template not found")
    if isinstance(result, dict) and result.get("validation_errors"):
        raise HTTPException(status_code=400, detail=result["validation_errors"])
    return result


@router.delete("/workflow-templates/{id}", dependencies=[Depends(require_roles("System Admin"))])
async def workflow_template_delete(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    ok = await delete_template(supabase, id, user)
    if not ok:
        raise HTTPException(status_code=404, detail="Workflow template not found")
    return {"ok": True}


@router.post(
    "/workflows/generate",
    dependencies=[Depends(require_roles("System Admin"))],
)
async def ai_generate_workflow(
    body: GenerateWorkflowInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    result = await generate_workflow(
        body.description,
        str(body.region_id) if body.region_id else None,
        str(body.entity_id) if body.entity_id else None,
        str(body.project_id) if body.project_id else None,
        supabase,
    )
    await audit_log(
        supabase,
        action="ai_workflow_generated",
        resource_type="workflow_template",
        details={"description": body.description},
        actor=user,
    )
    return result


@router.post(
    "/contracts/{contract_id}/workflow",
    dependencies=[Depends(require_roles("System Admin", "Legal", "Commercial"))],
)
async def workflow_start(
    contract_id: UUID,
    body: StartWorkflowInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await start_workflow(supabase, contract_id, body.template_id, user)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/contracts/{contract_id}/workflow")
async def workflow_get(
    contract_id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    row = get_active_instance(supabase, contract_id)
    if not row:
        raise HTTPException(status_code=404, detail="Workflow instance not found")
    return row


@router.post("/workflow-instances/{id}/stages/{stage_name}/action")
async def workflow_stage_action(
    id: UUID,
    stage_name: str,
    body: StageActionInput,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    try:
        return await record_action(supabase, instance_id=id, stage_name=stage_name, input=body, actor=user)
    except PermissionError as e:
        raise HTTPException(status_code=403, detail=str(e))
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.get("/workflow-instances/{id}/history")
async def workflow_history(
    id: UUID,
    user: CurrentUser = Depends(get_current_user),
    supabase: Client = Depends(get_supabase),
):
    return get_history(supabase, id)
