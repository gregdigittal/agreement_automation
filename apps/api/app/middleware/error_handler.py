import structlog
from fastapi import Request
from fastapi.responses import JSONResponse

logger = structlog.get_logger()


def global_exception_handler(request: Request, exc: Exception) -> JSONResponse:
    err_str = str(exc).lower()
    if "duplicate" in err_str or "unique" in err_str:
        status = 409
        detail = "Resource already exists or conflicts with existing data."
    elif "foreign key" in err_str or "violates" in err_str:
        status = 400
        detail = "Invalid reference or constraint violation."
    elif "not found" in err_str or "no row" in err_str:
        status = 404
        detail = "Resource not found."
    else:
        status = 500
        detail = "An internal error occurred."

    logger.exception("unhandled_exception", path=request.url.path, error=str(exc))
    return JSONResponse(status_code=status, content={"detail": detail})
