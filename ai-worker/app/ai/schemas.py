"""Pydantic schemas for AI analysis results and usage."""
from pydantic import BaseModel
from typing import Any, Optional


class AnalysisUsage(BaseModel):
    input_tokens: int = 0
    output_tokens: int = 0
    cost_usd: float = 0.0
    processing_time_ms: int = 0
    model_used: str = ""


class SummaryResult(BaseModel):
    summary: str = ""
    key_terms: list[str] = []
    confidence: Optional[float] = None
