from datetime import date
from typing import Literal

from pydantic import BaseModel


class CreateObligationInput(BaseModel):
    obligation_type: Literal["reporting", "sla", "insurance", "deliverable", "payment", "other"]
    description: str
    due_date: date | None = None
    recurrence: Literal["once", "daily", "weekly", "monthly", "quarterly", "annually"] | None = None
    responsible_party: str | None = None
    evidence_clause: str | None = None


class UpdateObligationInput(BaseModel):
    description: str | None = None
    due_date: date | None = None
    recurrence: str | None = None
    responsible_party: str | None = None
    status: Literal["active", "completed", "waived", "overdue"] | None = None
from datetime import date
from typing import Literal

from pydantic import BaseModel


class CreateObligationInput(BaseModel):
    obligation_type: Literal["reporting", "sla", "insurance", "deliverable", "payment", "other"]
    description: str
    due_date: date | None = None
    recurrence: Literal["once", "daily", "weekly", "monthly", "quarterly", "annually"] | None = None
    responsible_party: str | None = None
    evidence_clause: str | None = None


class UpdateObligationInput(BaseModel):
    description: str | None = None
    due_date: date | None = None
    recurrence: str | None = None
    responsible_party: str | None = None
    status: Literal["active", "completed", "waived", "overdue"] | None = None
