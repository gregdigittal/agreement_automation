from pydantic import BaseModel


class ExtractionField(BaseModel):
    field_name: str
    field_value: str | None = None
    evidence_clause: str | None = None
    evidence_page: int | None = None
    confidence: float = 0.0


class RiskItem(BaseModel):
    category: str
    description: str
    severity: str
    evidence_clause: str | None = None
    recommendation: str | None = None


class DeviationItem(BaseModel):
    clause_reference: str
    template_text: str | None = None
    contract_text: str
    deviation_type: str
    risk_level: str


class ObligationItem(BaseModel):
    obligation_type: str
    description: str
    due_date: str | None = None
    recurrence: str | None = None
    responsible_party: str | None = None
    evidence_clause: str | None = None
    confidence: float = 0.0


class SummaryResult(BaseModel):
    summary: str
    key_parties: list[str] = []
    contract_type_detected: str | None = None
    effective_date: str | None = None
    expiry_date: str | None = None
    total_value: str | None = None
    governing_law: str | None = None
    language_detected: str | None = None


class ExtractionResult(BaseModel):
    fields: list[ExtractionField] = []


class RiskResult(BaseModel):
    overall_risk_score: float = 0.0
    risks: list[RiskItem] = []


class DeviationResult(BaseModel):
    template_name: str | None = None
    deviations: list[DeviationItem] = []


class ObligationsResult(BaseModel):
    obligations: list[ObligationItem] = []


class AnalysisUsage(BaseModel):
    input_tokens: int = 0
    output_tokens: int = 0
    cost_usd: float = 0.0
    processing_time_ms: int = 0
    model_used: str = ""
