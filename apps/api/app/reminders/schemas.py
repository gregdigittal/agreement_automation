from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field


class CreateReminderInput(BaseModel):
    key_date_id: UUID | None = Field(None, alias="keyDateId")
    reminder_type: Literal["expiry", "renewal_notice", "payment", "sla", "obligation", "custom"]
    lead_days: int = Field(..., ge=1, le=365)
    channel: Literal["email", "teams", "calendar"] = "email"
    recipient_email: str | None = None
    recipient_user_id: str | None = None

    model_config = {"populate_by_name": True}


class UpdateReminderInput(BaseModel):
    lead_days: int | None = Field(None, ge=1, le=365)
    channel: Literal["email", "teams", "calendar"] | None = None
    recipient_email: str | None = None
    recipient_user_id: str | None = None
    is_active: bool | None = None
from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field


class CreateReminderInput(BaseModel):
    key_date_id: UUID | None = Field(None, alias="keyDateId")
    reminder_type: Literal["expiry", "renewal_notice", "payment", "sla", "obligation", "custom"]
    lead_days: int = Field(..., ge=1, le=365)
    channel: Literal["email", "teams", "calendar"] = "email"
    recipient_email: str | None = None
    recipient_user_id: str | None = None
    model_config = {"populate_by_name": True}


class UpdateReminderInput(BaseModel):
    lead_days: int | None = Field(None, ge=1, le=365)
    channel: Literal["email", "teams", "calendar"] | None = None
    recipient_email: str | None = None
    recipient_user_id: str | None = None
    is_active: bool | None = None
