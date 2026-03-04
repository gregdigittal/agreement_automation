import base64
import structlog
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.deps import get_db
import json

from app.ai.agent_client import analyze_complex
from app.ai.config import get_task_type
from app.ai.messages_client import analyze_summary
from app.ai.config import settings
from app.ai.workflow_generator import generate_workflow
from app.middleware.auth import verify_ai_worker_secret

logger = structlog.get_logger()
router = APIRouter(dependencies=[Depends(verify_ai_worker_secret)])


class AnalyzeRequest(BaseModel):
    contract_id: str
    analysis_type: str  # summary | extraction | risk | deviation | obligations
    file_content_base64: str = Field(max_length=20_000_000)  # ~15MB decoded
    file_name: str = Field(max_length=500)
    context: dict = {}  # optional: region_id, entity_id, counterparty_id for mcp_tools


class GenerateWorkflowRequest(BaseModel):
    description: str
    region_id: str | None = None
    entity_id: str | None = None
    project_id: str | None = None


@router.post("/analyze")
async def analyze(req: AnalyzeRequest, db: Session = Depends(get_db)):
    """
    Runs AI analysis on a contract file. Does NOT write to database.
    Returns result + usage. Caller (Laravel) writes to database.
    """
    try:
        try:
            file_bytes = base64.b64decode(req.file_content_base64)
        except Exception:
            raise HTTPException(status_code=400, detail="Invalid base64 file content")

        contract_text = _extract_text(file_bytes, req.file_name)
        if not contract_text.strip():
            raise HTTPException(status_code=422, detail="Could not extract text from file")

        from app.ai.mcp_tools import get_tools
        tools = get_tools(db, req.contract_id)

        task_type = get_task_type(req.analysis_type)
        if task_type == "simple":
            result, usage = await analyze_summary(contract_text)
            result_dict = result.model_dump()
        elif req.analysis_type == "discovery":
            result_dict, usage = await analyze_discovery(contract_text, req.context, tools)
        else:
            result_dict, usage = await analyze_complex(
                req.analysis_type,
                contract_text,
                req.contract_id,
                tools,
            )

        return {
            "result": result_dict,
            "usage": {
                "input_tokens": usage.input_tokens,
                "output_tokens": usage.output_tokens,
                "cost_usd": usage.cost_usd,
                "processing_time_ms": usage.processing_time_ms,
                "model_used": usage.model_used,
            }
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error("analyze_failed", contract_id=req.contract_id, analysis_type=req.analysis_type, error=str(e))
        raise HTTPException(status_code=500, detail="Analysis failed. See AI worker logs for details.")


@router.post("/generate-workflow")
async def generate_workflow_endpoint(req: GenerateWorkflowRequest):
    """Generate a workflow template using AI."""
    try:
        result = await generate_workflow(
            description=req.description,
            region_id=req.region_id,
            entity_id=req.entity_id,
            project_id=req.project_id,
        )
        return result
    except Exception as e:
        logger.error("generate_workflow_failed", error=str(e))
        raise HTTPException(status_code=500, detail="Workflow generation failed. See AI worker logs for details.")


def _strip_markdown_json(text: str) -> str:
    """Strip markdown code fences from Claude's JSON responses.

    Claude frequently wraps JSON in ```json ... ``` blocks even when asked not to.
    This function extracts the raw JSON string from such wrappers.
    """
    import re
    stripped = text.strip()
    # Match ```json ... ``` or ``` ... ``` (with optional language tag)
    m = re.match(r"^```(?:json)?\s*\n?(.*?)```\s*$", stripped, re.DOTALL)
    if m:
        return m.group(1).strip()
    return stripped


async def analyze_discovery(contract_text: str, context: dict, mcp_tools: list) -> tuple[dict, "AnalysisUsage"]:
    """Extract structured entity data from contract text using Claude."""
    import time
    import anthropic
    from app.ai.schemas import AnalysisUsage

    prompt = f"""Analyze this contract and extract the following structured information.
For each item found, provide the data and a confidence score (0.0 to 1.0).

Return ONLY raw valid JSON (no markdown, no code fences, no explanation).
The JSON must have a 'discoveries' array. Each item has:
- type: one of 'counterparty', 'entity', 'jurisdiction', 'governing_law'
- confidence: float 0.0-1.0
- data: object with relevant fields

For counterparty: legal_name, registration_number, registered_address, jurisdiction
For entity: name, registration_number, code
For jurisdiction: name, country_code
For governing_law: name, country_code

Contract text:
{contract_text[:50000]}

Context from the system:
{json.dumps(context, default=str)}"""

    start = time.perf_counter()
    client = anthropic.AsyncAnthropic(api_key=settings.anthropic_api_key)
    msg = await client.messages.create(
        model=settings.ai_model,
        max_tokens=4096,
        messages=[{"role": "user", "content": prompt}],
    )
    elapsed_ms = int((time.perf_counter() - start) * 1000)

    raw_text = ""
    if msg.content and len(msg.content) > 0:
        raw_text = msg.content[0].text if hasattr(msg.content[0], "text") else str(msg.content[0])

    # Strip markdown code fences — Claude frequently wraps JSON despite instructions
    cleaned_text = _strip_markdown_json(raw_text)

    try:
        result_dict = json.loads(cleaned_text)
    except json.JSONDecodeError:
        logger.warning("analyze_discovery_json_parse_failed",
                       raw=raw_text[:500],
                       cleaned=cleaned_text[:500])
        result_dict = {"discoveries": []}

    # Validate we got a discoveries array
    if "discoveries" not in result_dict:
        logger.warning("analyze_discovery_missing_key",
                       keys=list(result_dict.keys()),
                       raw_preview=raw_text[:300])
        result_dict = {"discoveries": result_dict.get("results", result_dict.get("data", []))}
        if not isinstance(result_dict["discoveries"], list):
            result_dict = {"discoveries": []}

    logger.info("analyze_discovery_completed",
                discovery_count=len(result_dict.get("discoveries", [])),
                elapsed_ms=elapsed_ms)

    usage = AnalysisUsage(
        input_tokens=msg.usage.input_tokens if msg.usage else 0,
        output_tokens=msg.usage.output_tokens if msg.usage else 0,
        cost_usd=0.0,
        processing_time_ms=elapsed_ms,
        model_used=settings.ai_model,
    )
    return result_dict, usage


def _extract_text(file_bytes: bytes, file_name: str) -> str:
    """Extract text from PDF or DOCX."""
    if file_name.lower().endswith(".pdf"):
        try:
            import fitz
            doc = fitz.open(stream=file_bytes, filetype="pdf")
            return "\n".join(page.get_text() for page in doc)
        except Exception:
            return file_bytes.decode("utf-8", errors="ignore")
    if file_name.lower().endswith((".docx", ".doc")):
        try:
            import docx
            from io import BytesIO
            doc = docx.Document(BytesIO(file_bytes))
            return "\n".join(p.text for p in doc.paragraphs)
        except Exception:
            return file_bytes.decode("utf-8", errors="ignore")
    return file_bytes.decode("utf-8", errors="ignore")
