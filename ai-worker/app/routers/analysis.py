import base64
import structlog
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, Field
from sqlalchemy.orm import Session

from app.deps import get_db
from app.ai.agent_client import analyze_complex
from app.ai.config import get_task_type
from app.ai.messages_client import analyze_summary
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
