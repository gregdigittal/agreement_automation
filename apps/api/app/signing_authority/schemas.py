from uuid import UUID

from pydantic import BaseModel, Field


class CreateSigningAuthorityInput(BaseModel):
    entity_id: UUID = Field(..., alias="entityId")
    project_id: UUID | None = Field(None, alias="projectId")
    user_id: str = Field(..., alias="userId")
    user_email: str | None = Field(None, alias="userEmail")
    role_or_name: str = Field(..., alias="roleOrName")
    contract_type_pattern: str | None = Field(None, alias="contractTypePattern")

    model_config = {"populate_by_name": True}


class UpdateSigningAuthorityInput(BaseModel):
    entity_id: UUID | None = Field(None, alias="entityId")
    project_id: UUID | None = Field(None, alias="projectId")
    user_id: str | None = Field(None, alias="userId")
    user_email: str | None = Field(None, alias="userEmail")
    role_or_name: str | None = Field(None, alias="roleOrName")
    contract_type_pattern: str | None = Field(None, alias="contractTypePattern")

    model_config = {"populate_by_name": True}
