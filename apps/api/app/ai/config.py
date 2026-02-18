from app.config import settings

SIMPLE_TASKS = {"summary"}
COMPLEX_TASKS = {"extraction", "risk", "deviation", "obligations"}


def get_task_type(analysis_type: str) -> str:
    if analysis_type in SIMPLE_TASKS:
        return "simple"
    return "complex"
from app.config import settings

SIMPLE_TASKS = {"summary"}
COMPLEX_TASKS = {"extraction", "risk", "deviation", "obligations"}


def get_task_type(analysis_type: str) -> str:
    if analysis_type in SIMPLE_TASKS:
        return "simple"
    return "complex"
