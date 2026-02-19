from fastapi import APIRouter, Depends
from fastapi.responses import JSONResponse

from app.deps import get_supabase
from supabase import Client

router = APIRouter(tags=["health"])


@router.get("/health")
async def health(supabase: Client = Depends(get_supabase)):
    try:
        supabase.table("regions").select("id").limit(1).execute()
        return {"status": "ok", "db": "connected"}
    except Exception as e:
        return JSONResponse(
            status_code=503,
            content={"status": "degraded", "db": "error", "error": str(e)},
        )
