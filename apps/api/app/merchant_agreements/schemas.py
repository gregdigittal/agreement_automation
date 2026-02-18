from uuid import UUID

from pydantic import BaseModel, Field


class GenerateMerchantAgreementInput(BaseModel):
    template_id: UUID = Field(..., alias="templateId")
    vendor_name: str = Field(..., alias="vendorName")
    merchant_fee: str | None = Field(None, alias="merchantFee")
    region_id: UUID = Field(..., alias="regionId")
    entity_id: UUID = Field(..., alias="entityId")
    project_id: UUID = Field(..., alias="projectId")
    counterparty_id: UUID = Field(..., alias="counterpartyId")
    region_terms: dict | None = Field(None, alias="regionTerms")

    model_config = {"populate_by_name": True}
