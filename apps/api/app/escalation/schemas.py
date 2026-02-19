from pydantic import BaseModel, Field


class CreateEscalationRuleInput(BaseModel):
    stage_name: str
    sla_breach_hours: int = Field(..., ge=1)
    tier: int = Field(1, ge=1, le=5)
    escalate_to_role: str | None = None
    escalate_to_user_id: str | None = None


class UpdateEscalationRuleInput(BaseModel):
    sla_breach_hours: int | None = Field(None, ge=1)
    tier: int | None = Field(None, ge=1, le=5)
    escalate_to_role: str | None = None
    escalate_to_user_id: str | None = None
