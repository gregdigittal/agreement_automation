from pydantic import BaseModel, Field


class CreateOverrideRequestInput(BaseModel):
    contract_title: str = Field(..., alias="contractTitle")
    reason: str

    model_config = {"populate_by_name": True}


class DecideOverrideRequestInput(BaseModel):
    decision: str = Field(..., pattern="^(approved|rejected)$")
    comment: str | None = None
