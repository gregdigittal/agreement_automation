"""AI task type and analysis config."""
from app.config import settings


def get_task_type(analysis_type: str) -> str:
    """Return 'simple' for summary, 'complex' for extraction/risk/deviation/obligations."""
    if analysis_type == "summary":
        return "simple"
    return "complex"
