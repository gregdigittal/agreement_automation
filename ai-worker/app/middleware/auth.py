from fastapi import Header, HTTPException
from app.config import settings


async def verify_ai_worker_secret(x_ai_worker_secret: str = Header(...)):
    """Validates the shared secret header for internal service-to-service auth."""
    if not x_ai_worker_secret or x_ai_worker_secret != settings.ai_worker_secret:
        raise HTTPException(status_code=401, detail="Invalid AI Worker secret")
