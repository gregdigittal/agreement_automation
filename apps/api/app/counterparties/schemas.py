from pydantic import BaseModel, Field


class CreateCounterpartyInput(BaseModel):
    legal_name: str = Field(..., max_length=255)
    registration_number: str | None = None
    address: str | None = None
    jurisdiction: str | None = None

    model_config = {"populate_by_name": True}


class UpdateCounterpartyInput(BaseModel):
    legal_name: str | None = Field(None, max_length=255)
    registration_number: str | None = None
    address: str | None = None
    jurisdiction: str | None = None

    model_config = {"populate_by_name": True}


class StatusChangeInput(BaseModel):
    status: str = Field(..., pattern="^(Active|Suspended|Blacklisted)$")
    reason: str = Field(...)
    supporting_document_ref: str | None = None

    model_config = {"populate_by_name": True}
