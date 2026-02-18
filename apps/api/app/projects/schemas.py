from uuid import UUID

from pydantic import BaseModel, Field


class CreateProjectInput(BaseModel):
    entity_id: UUID = Field(..., alias="entityId")
    name: str = Field(..., max_length=255)
    code: str | None = Field(None, max_length=64)

    model_config = {"populate_by_name": True}


class UpdateProjectInput(BaseModel):
    entity_id: UUID | None = Field(None, alias="entityId")
    name: str | None = Field(None, max_length=255)
    code: str | None = Field(None, max_length=64)

    model_config = {"populate_by_name": True}
