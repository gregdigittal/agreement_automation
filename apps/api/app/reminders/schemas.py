from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field


class CreateReminderInput(BaseModel):
    key_date_id: UUID | None = Field(None, alias="keyDateId")
    reminder_type: Literal["expiry", "renewal_notice", "payment", "sla", "obligation", "custom"] = Field(
        ..., alias="reminderType"
    )
    lead_days: int = Field(..., ge=1, le=365, alias="leadDays")
    channel: Literal["email", "teams", "calendar"] = "email"
    recipient_email: str | None = Field(None, alias="recipientEmail")
    recipient_user_id: str | None = Field(None, alias="recipientUserId")

    model_config = {"populate_by_name": True}


class UpdateReminderInput(BaseModel):
    lead_days: int | None = Field(None, ge=1, le=365, alias="leadDays")
    channel: Literal["email", "teams", "calendar"] | None = None
    recipient_email: str | None = Field(None, alias="recipientEmail")
    recipient_user_id: str | None = Field(None, alias="recipientUserId")
    is_active: bool | None = Field(None, alias="isActive")

    model_config = {"populate_by_name": True}
