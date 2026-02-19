from contextlib import asynccontextmanager

import logging
import structlog
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.scheduler import start_scheduler, stop_scheduler
from app.health.router import router as health_router
from app.regions.router import router as regions_router
from app.entities.router import router as entities_router
from app.projects.router import router as projects_router
from app.counterparties.router import router as counterparties_router
from app.counterparty_contacts.router import router as contacts_router
from app.contracts.router import router as contracts_router
from app.signing_authority.router import router as signing_authority_router
from app.audit.router import router as audit_router
from app.boldsign.router import router as boldsign_router
from app.contract_links.router import router as contract_links_router
from app.middleware.logging import RequestLoggingMiddleware
from app.middleware.error_handler import global_exception_handler
from app.key_dates.router import router as key_dates_router
from app.merchant_agreements.router import router as merchant_agreements_router
from app.wiki_contracts.router import router as wiki_contracts_router
from app.workflows.router import router as workflows_router
from app.ai_analysis.router import router as ai_analysis_router
from app.obligations.router import router as obligations_router
from app.reminders.router import router as reminders_router
from app.escalation.router import router as escalation_router
from app.notifications.router import router as notifications_router
from app.reports.router import router as reports_router
from app.contract_languages.router import router as contract_languages_router
from app.override_requests.router import router as override_requests_router

structlog.configure(
    processors=[
        structlog.contextvars.merge_contextvars,
        structlog.processors.add_log_level,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.dev.ConsoleRenderer()
        if settings.log_level == "debug"
        else structlog.processors.JSONRenderer(),
    ],
    wrapper_class=structlog.make_filtering_bound_logger(
        logging.getLevelName(settings.log_level.upper())
    ),
)


@asynccontextmanager
async def lifespan(app: FastAPI):
    logger = structlog.get_logger()
    logger.info("ccrs_api_starting", port=settings.port)
    start_scheduler()
    yield
    stop_scheduler()
    logger.info("ccrs_api_stopping")


app = FastAPI(
    title="CCRS API",
    version="0.1.0",
    lifespan=lifespan,
)

origins = [o.strip() for o in settings.cors_origin.split(",")]
app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
app.add_middleware(RequestLoggingMiddleware)
app.add_exception_handler(Exception, global_exception_handler)


@app.get("/")
async def root():
    return {"name": "CCRS API", "version": "0.1.0"}

app.include_router(health_router)
app.include_router(regions_router)
app.include_router(entities_router)
app.include_router(projects_router)
app.include_router(counterparties_router)
app.include_router(contacts_router)
app.include_router(contracts_router)
app.include_router(signing_authority_router)
app.include_router(audit_router)
app.include_router(wiki_contracts_router)
app.include_router(workflows_router)
app.include_router(boldsign_router)
app.include_router(contract_links_router)
app.include_router(key_dates_router)
app.include_router(merchant_agreements_router)
app.include_router(ai_analysis_router)
app.include_router(obligations_router)
app.include_router(reminders_router)
app.include_router(escalation_router)
app.include_router(notifications_router)
app.include_router(reports_router)
app.include_router(contract_languages_router)
app.include_router(override_requests_router)
