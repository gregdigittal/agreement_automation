import json
import uuid
from datetime import datetime

import structlog
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.deps import get_db
from app.middleware.auth import verify_ai_worker_secret
from app.redline import analyze_redline

logger = structlog.get_logger()
router = APIRouter(dependencies=[Depends(verify_ai_worker_secret)])


class RedlineRequest(BaseModel):
    contract_text: str
    template_text: str
    contract_id: str
    session_id: str


class RedlineResponse(BaseModel):
    status: str
    session_id: str
    total_clauses: int
    message: str


@router.post("/analyze-redline", response_model=RedlineResponse)
async def analyze_redline_endpoint(request: RedlineRequest, db: Session = Depends(get_db)):
    """
    Analyze a contract against a template and store clause-by-clause
    redline results directly in MySQL.

    Called by Laravel's ProcessRedlineAnalysis job.
    """
    session_id = request.session_id

    try:
        # Update session status to 'processing'
        db.execute(
            __import__("sqlalchemy").text(
                "UPDATE redline_sessions SET status = :status, updated_at = :now WHERE id = :id"
            ),
            {"status": "processing", "now": datetime.utcnow(), "id": session_id},
        )
        db.commit()

        # Run AI analysis
        result = analyze_redline(request.contract_text, request.template_text)

        clauses = result.get("clauses", [])
        summary = result.get("summary", {})
        total_clauses = len(clauses)

        # Insert each clause into redline_clauses
        for clause in clauses:
            now = datetime.utcnow()
            db.execute(
                __import__("sqlalchemy").text(
                    """
                    INSERT INTO redline_clauses
                        (id, session_id, clause_number, clause_heading, original_text,
                         suggested_text, change_type, ai_rationale, confidence,
                         status, created_at, updated_at)
                    VALUES (:id, :session_id, :clause_number, :clause_heading, :original_text,
                            :suggested_text, :change_type, :ai_rationale, :confidence,
                            :status, :created_at, :updated_at)
                    """
                ),
                {
                    "id": str(uuid.uuid4()),
                    "session_id": session_id,
                    "clause_number": clause.get("clause_number", 0),
                    "clause_heading": clause.get("clause_heading"),
                    "original_text": clause.get("original_text", ""),
                    "suggested_text": clause.get("suggested_text"),
                    "change_type": clause.get("change_type", "unchanged"),
                    "ai_rationale": clause.get("ai_rationale"),
                    "confidence": clause.get("confidence"),
                    "status": "pending",
                    "created_at": now,
                    "updated_at": now,
                },
            )

        # Update session to completed with summary
        db.execute(
            __import__("sqlalchemy").text(
                """
                UPDATE redline_sessions
                SET status = :status, total_clauses = :total, summary = :summary, updated_at = :now
                WHERE id = :id
                """
            ),
            {
                "status": "completed",
                "total": total_clauses,
                "summary": json.dumps(summary),
                "now": datetime.utcnow(),
                "id": session_id,
            },
        )
        db.commit()

        logger.info(
            "redline_analysis_completed",
            session_id=session_id,
            total_clauses=total_clauses,
        )

        return RedlineResponse(
            status="completed",
            session_id=session_id,
            total_clauses=total_clauses,
            message=f"Analysis complete. {total_clauses} clauses compared.",
        )

    except Exception as e:
        logger.error("redline_analysis_failed", session_id=session_id, error=str(e))

        # Update session status to 'failed'
        try:
            db.execute(
                __import__("sqlalchemy").text(
                    """
                    UPDATE redline_sessions
                    SET status = :status, error_message = :error, updated_at = :now
                    WHERE id = :id
                    """
                ),
                {
                    "status": "failed",
                    "error": str(e)[:2000],
                    "now": datetime.utcnow(),
                    "id": session_id,
                },
            )
            db.commit()
        except Exception as db_err:
            logger.error("failed_to_update_session_status", error=str(db_err))

        raise HTTPException(
            status_code=500,
            detail=f"Redline analysis failed: {str(e)}",
        )
