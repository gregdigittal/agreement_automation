import json
from typing import Literal, Optional

import structlog
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field

from app.config import settings
from app.middleware.auth import verify_ai_worker_secret

logger = structlog.get_logger()
router = APIRouter(dependencies=[Depends(verify_ai_worker_secret)])


class ComplianceRequirement(BaseModel):
    id: str
    text: str
    category: str
    severity: str


class ComplianceFramework(BaseModel):
    id: str
    name: str
    jurisdiction_code: str
    requirements: list[ComplianceRequirement]


class ComplianceCheckRequest(BaseModel):
    contract_text: str = Field(max_length=500_000)  # ~500K chars max
    contract_id: str
    framework: ComplianceFramework


class ComplianceFindingResult(BaseModel):
    requirement_id: str
    status: Literal["compliant", "non_compliant", "unclear", "not_applicable"] = "unclear"
    evidence_clause: Optional[str] = None
    evidence_page: Optional[int] = None
    rationale: str
    confidence: float = Field(ge=0.0, le=1.0)


class ComplianceCheckResponse(BaseModel):
    contract_id: str
    framework_id: str
    findings: list[ComplianceFindingResult]


@router.post("/check-compliance", response_model=ComplianceCheckResponse)
async def check_compliance(request: ComplianceCheckRequest):
    """
    Evaluate a contract against a regulatory framework's requirements.

    This endpoint checks whether specific clauses or provisions in the contract
    appear to address each requirement. It does NOT provide legal advice or
    automated legal opinions.
    """
    import anthropic

    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)

    requirements_text = "\n".join([
        f"- [{req.id}] (Category: {req.category}, Severity: {req.severity}): {req.text}"
        for req in request.framework.requirements
    ])

    system_prompt = (
        "You are a regulatory compliance checking assistant for contract review. "
        "IMPORTANT: You are NOT providing legal advice or legal opinions. "
        "You are checking whether specific clauses or provisions in the contract text "
        "appear to address each regulatory requirement. Flag issues for human legal review. "
        "You must NOT make definitive legal determinations. "
        "For each requirement, provide:\n"
        "1. status: 'compliant' if the contract clearly addresses the requirement, "
        "'non_compliant' if the requirement is clearly not addressed, "
        "'unclear' if the contract partially addresses it or the language is ambiguous, "
        "'not_applicable' if the requirement does not apply to this type of contract.\n"
        "2. evidence_clause: A direct quote from the contract that relates to this requirement (if any).\n"
        "3. evidence_page: The approximate page number where the evidence was found (if determinable).\n"
        "4. rationale: A brief explanation of your assessment.\n"
        "5. confidence: A float between 0.0 and 1.0 indicating your confidence in this assessment.\n\n"
        "Respond with a JSON array of findings. Each finding must have the fields: "
        "requirement_id, status, evidence_clause, evidence_page, rationale, confidence."
    )

    user_prompt = (
        f"## Regulatory Framework: {request.framework.name}\n"
        f"## Jurisdiction: {request.framework.jurisdiction_code}\n\n"
        f"## Requirements to check:\n{requirements_text}\n\n"
        f"## Contract Text:\n{request.contract_text}\n\n"
        "Evaluate the contract against each requirement and return a JSON array of findings."
    )

    try:
        response = client.messages.create(
            model=settings.ai_model,
            max_tokens=4096,
            system=system_prompt,
            messages=[{"role": "user", "content": user_prompt}],
        )

        response_text = response.content[0].text

        # Extract JSON from potential markdown code blocks
        if "```json" in response_text:
            response_text = response_text.split("```json")[1].split("```")[0].strip()
        elif "```" in response_text:
            response_text = response_text.split("```")[1].split("```")[0].strip()

        findings_raw = json.loads(response_text)

        findings = []
        for finding in findings_raw:
            findings.append(ComplianceFindingResult(
                requirement_id=finding["requirement_id"],
                status=finding.get("status", "unclear"),
                evidence_clause=finding.get("evidence_clause"),
                evidence_page=finding.get("evidence_page"),
                rationale=finding.get("rationale", ""),
                confidence=float(finding.get("confidence", 0.5)),
            ))

        usage = {
            "input_tokens": response.usage.input_tokens,
            "output_tokens": response.usage.output_tokens,
            "model": settings.ai_model,
            "analysis_type": "compliance_check",
        }
        logger.info(
            "compliance_check_completed",
            contract_id=request.contract_id,
            framework_id=request.framework.id,
            findings_count=len(findings),
            **usage,
        )

        return ComplianceCheckResponse(
            contract_id=request.contract_id,
            framework_id=request.framework.id,
            findings=findings,
        )

    except json.JSONDecodeError as e:
        logger.error("compliance_check_json_parse_error", error=str(e), contract_id=request.contract_id)
        raise HTTPException(status_code=500, detail="Failed to parse AI response. See AI worker logs for details.")
    except Exception as e:
        logger.error("compliance_check_failed", error=str(e), contract_id=request.contract_id)
        raise HTTPException(status_code=500, detail="Compliance check failed. See AI worker logs for details.")
