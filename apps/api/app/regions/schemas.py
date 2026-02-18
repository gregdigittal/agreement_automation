from pydantic import BaseModel, Field


class CreateRegionInput(BaseModel):
    name: str = Field(..., max_length=255)
    code: str | None = Field(None, max_length=64)

    model_config = {"populate_by_name": True}


class UpdateRegionInput(BaseModel):
    name: str | None = Field(None, max_length=255)
    code: str | None = Field(None, max_length=64)

    model_config = {"populate_by_name": True}
