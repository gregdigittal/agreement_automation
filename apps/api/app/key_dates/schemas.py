from datetime import date

from pydantic import BaseModel, Field


class CreateKeyDateInput(BaseModel):
    date_type: str = Field(..., max_length=64, alias="dateType")
    date_value: date = Field(..., alias="dateValue")
    description: str | None = None
    reminder_days: list[int] | None = Field(None, alias="reminderDays")

    model_config = {"populate_by_name": True}


class UpdateKeyDateInput(BaseModel):
    date_type: str | None = Field(None, max_length=64, alias="dateType")
    date_value: date | None = Field(None, alias="dateValue")
    description: str | None = None
    reminder_days: list[int] | None = Field(None, alias="reminderDays")

    model_config = {"populate_by_name": True}
