from typing import Literal

from pydantic import BaseModel, Field


class TriggerAnalysisInput(BaseModel):
    analysis_type: Literal["summary", "extraction", "risk", "deviation", "obligations"] = Field(
        ..., alias="analysisType"
    )

    model_config = {"populate_by_name": True}


class VerifyFieldInput(BaseModel):
    is_verified: bool = Field(True, alias="isVerified")

    model_config = {"populate_by_name": True}


class CorrectFieldInput(BaseModel):
    field_value: str = Field(..., alias="fieldValue")

    model_config = {"populate_by_name": True}
