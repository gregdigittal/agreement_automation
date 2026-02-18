from uuid import UUID

from pydantic import BaseModel, Field


class CreateEntityInput(BaseModel):
    region_id: UUID = Field(..., alias="regionId")
    name: str = Field(..., max_length=255)
    code: str | None = Field(None, max_length=64)

    model_config = {"populate_by_name": True}


class UpdateEntityInput(BaseModel):
    region_id: UUID | None = Field(None, alias="regionId")
    name: str | None = Field(None, max_length=255)
    code: str | None = Field(None, max_length=64)

    model_config = {"populate_by_name": True}
