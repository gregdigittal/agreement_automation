from typing import Literal

from pydantic import BaseModel, Field

WORKFLOW_STATES = Literal[
    "draft", "review", "approval", "signing", "executed", "archived", "cancelled"
]
SIGNING_STATUSES = Literal[
    "draft", "sent", "viewed", "partially_signed", "completed", "declined", "expired", "voided"
]


class UpdateContractInput(BaseModel):
    title: str | None = None
    contract_type: str | None = Field(None, pattern=r"^(Commercial|Merchant)$")
    workflow_state: WORKFLOW_STATES | None = None
    signing_status: SIGNING_STATUSES | None = None

    model_config = {"populate_by_name": True}
