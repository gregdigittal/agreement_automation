import structlog
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.routers import analysis, compliance, health, redline

structlog.configure(
    wrapper_class=structlog.make_filtering_bound_logger(
        {"debug": 10, "info": 20, "warning": 30, "error": 40}.get(settings.log_level.lower(), 20)
    )
)

app = FastAPI(
    title="CCRS AI Worker",
    version="1.0.0",
    description="Internal AI microservice for CCRS contract analysis",
    docs_url=None,
    redoc_url=None,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://ccrs_laravel:8000", "http://app:8000"],
    allow_methods=["POST", "GET"],
    allow_headers=["X-AI-Worker-Secret", "Content-Type"],
)

app.include_router(health.router, tags=["health"])
app.include_router(analysis.router, prefix="/api/v1", tags=["analysis"])
app.include_router(analysis.router, tags=["analysis-root"])
app.include_router(redline.router, prefix="/api/v1", tags=["redline"])
app.include_router(redline.router, tags=["redline-root"])
app.include_router(compliance.router, prefix="/api/v1", tags=["compliance"])
app.include_router(compliance.router, tags=["compliance-root"])
