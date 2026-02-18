from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field


class WorkflowStage(BaseModel):
    name: str
    type: Literal["approval", "signing", "review", "draft"]
    description: str | None = None
    owners: list[str] = []
    approvers: list[str] = []
    required_artifacts: list[str] = []
    allowed_transitions: list[str] = []
    sla_hours: int | None = None
    signing_order: Literal["parallel", "sequential"] | None = None


class CreateWorkflowTemplateInput(BaseModel):
    name: str
    contract_type: Literal["Commercial", "Merchant"] = Field(..., alias="contractType")
    region_id: UUID | None = Field(None, alias="regionId")
    entity_id: UUID | None = Field(None, alias="entityId")
    project_id: UUID | None = Field(None, alias="projectId")
    stages: list[WorkflowStage]

    model_config = {"populate_by_name": True}


class UpdateWorkflowTemplateInput(BaseModel):
    name: str | None = None
    contract_type: Literal["Commercial", "Merchant"] | None = Field(None, alias="contractType")
    region_id: UUID | None = Field(None, alias="regionId")
    entity_id: UUID | None = Field(None, alias="entityId")
    project_id: UUID | None = Field(None, alias="projectId")
    stages: list[WorkflowStage] | None = None
    status: str | None = None

    model_config = {"populate_by_name": True}


class StartWorkflowInput(BaseModel):
    template_id: UUID = Field(..., alias="templateId")

    model_config = {"populate_by_name": True}


class StageActionInput(BaseModel):
    action: Literal["approve", "reject", "rework"]
    comment: str | None = None
    artifacts: dict | None = None


class GenerateWorkflowInput(BaseModel):
    description: str
    region_id: UUID | None = Field(None, alias="regionId")
    entity_id: UUID | None = Field(None, alias="entityId")
    project_id: UUID | None = Field(None, alias="projectId")

    model_config = {"populate_by_name": True}
