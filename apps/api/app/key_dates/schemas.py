from datetime import date

from pydantic import BaseModel, Field


class CreateKeyDateInput(BaseModel):
    date_type: str = Field(..., max_length=64)
    date_value: date
    description: str | None = None
    reminder_days: list[int] | None = None


class UpdateKeyDateInput(BaseModel):
    date_type: str | None = Field(None, max_length=64)
    date_value: date | None = None
    description: str | None = None
    reminder_days: list[int] | None = None
