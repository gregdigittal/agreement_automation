from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field


class TriggerAnalysisInput(BaseModel):
    analysis_type: Literal["summary", "extraction", "risk", "deviation", "obligations"] = Field(
        ..., alias="analysisType"
    )

    model_config = {"populate_by_name": True}


class VerifyFieldInput(BaseModel):
    is_verified: bool = True


class CorrectFieldInput(BaseModel):
    field_value: str
from typing import Literal
from uuid import UUID

from pydantic import BaseModel, Field


class TriggerAnalysisInput(BaseModel):
    analysis_type: Literal["summary", "extraction", "risk", "deviation", "obligations"] = Field(
        ..., alias="analysisType"
    )
    model_config = {"populate_by_name": True}


class VerifyFieldInput(BaseModel):
    is_verified: bool = True


class CorrectFieldInput(BaseModel):
    field_value: str
