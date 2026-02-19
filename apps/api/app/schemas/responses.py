"""Shared Pydantic response models for OpenAPI documentation."""

from datetime import datetime
from uuid import UUID

from pydantic import BaseModel


class RegionOut(BaseModel):
    id: UUID
    name: str
    code: str | None = None
    description: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class EntityOut(BaseModel):
    id: UUID
    name: str
    region_id: UUID
    code: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class ProjectOut(BaseModel):
    id: UUID
    name: str
    entity_id: UUID
    code: str | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class CounterpartyOut(BaseModel):
    id: UUID
    legal_name: str
    registration_number: str | None = None
    address: str | None = None
    jurisdiction: str | None = None
    preferred_language: str | None = None
    status: str
    status_reason: str | None = None
    status_changed_at: datetime | None = None
    status_changed_by: str | None = None
    created_at: datetime
    updated_at: datetime
    counterparty_contacts: list[dict] | None = None

    model_config = {"from_attributes": True}


class ContractOut(BaseModel):
    id: UUID
    title: str | None = None
    contract_type: str
    region_id: UUID | None = None
    entity_id: UUID | None = None
    project_id: UUID | None = None
    counterparty_id: UUID | None = None
    workflow_state: str | None = None
    signing_status: str | None = None
    storage_path: str | None = None
    file_name: str | None = None
    created_by: str | None = None
    created_at: datetime
    updated_at: datetime
    regions: dict | None = None
    entities: dict | None = None
    projects: dict | None = None
    counterparties: dict | None = None

    model_config = {"from_attributes": True}


class AuditLogOut(BaseModel):
    id: UUID
    at: datetime
    action: str
    resource_type: str
    resource_id: str | None = None
    actor_id: str | None = None
    actor_email: str | None = None
    details: dict | None = None
    ip_address: str | None = None

    model_config = {"from_attributes": True}


class WorkflowTemplateOut(BaseModel):
    id: UUID
    name: str
    version: int
    contract_type: str
    region_id: UUID | None = None
    entity_id: UUID | None = None
    project_id: UUID | None = None
    stages: list | None = None
    status: str
    created_by: str | None = None
    published_at: datetime | None = None
    created_at: datetime
    updated_at: datetime

    model_config = {"from_attributes": True}


class NotificationOut(BaseModel):
    id: UUID
    recipient_email: str | None = None
    channel: str
    subject: str
    body: str | None = None
    status: str
    related_resource_type: str | None = None
    related_resource_id: str | None = None
    read_at: datetime | None = None
    sent_at: datetime | None = None
    created_at: datetime

    model_config = {"from_attributes": True}
