from uuid import UUID

from pydantic import BaseModel, Field


class CreateWikiContractInput(BaseModel):
    name: str = Field(..., max_length=255)
    category: str | None = None
    region_id: UUID | None = Field(None, alias="regionId")
    description: str | None = None

    model_config = {"populate_by_name": True}


class UpdateWikiContractInput(BaseModel):
    name: str | None = Field(None, max_length=255)
    category: str | None = None
    region_id: UUID | None = Field(None, alias="regionId")
    description: str | None = None
    status: str | None = None

    model_config = {"populate_by_name": True}
