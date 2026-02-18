from pydantic import BaseModel, Field


class UpdateContractInput(BaseModel):
    title: str | None = None
    contract_type: str | None = Field(None, pattern="^(Commercial|Merchant)$")
    workflow_state: str | None = None
    signing_status: str | None = None

    model_config = {"populate_by_name": True}
