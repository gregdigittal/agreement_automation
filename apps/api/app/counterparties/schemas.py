from uuid import UUID

from pydantic import BaseModel, Field


class CreateCounterpartyInput(BaseModel):
    legal_name: str = Field(..., max_length=255, alias="legalName")
    registration_number: str | None = Field(None, alias="registrationNumber")
    address: str | None = None
    jurisdiction: str | None = None
    preferred_language: str | None = Field(None, alias="preferredLanguage")

    model_config = {"populate_by_name": True}


class UpdateCounterpartyInput(BaseModel):
    legal_name: str | None = Field(None, max_length=255, alias="legalName")
    registration_number: str | None = Field(None, alias="registrationNumber")
    address: str | None = None
    jurisdiction: str | None = None
    preferred_language: str | None = Field(None, alias="preferredLanguage")

    model_config = {"populate_by_name": True}


class StatusChangeInput(BaseModel):
    status: str = Field(..., pattern="^(Active|Suspended|Blacklisted)$")
    reason: str = Field(...)
    supporting_document_ref: str | None = Field(None, alias="supportingDocumentRef")

    model_config = {"populate_by_name": True}


class MergeCounterpartyInput(BaseModel):
    source_id: UUID = Field(
        ...,
        alias="sourceId",
        description="The duplicate counterparty to merge INTO this one",
    )

    model_config = {"populate_by_name": True}
