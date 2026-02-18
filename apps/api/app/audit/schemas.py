from datetime import datetime

from pydantic import BaseModel, Field


class AuditExportFilters(BaseModel):
    from_date: datetime | None = Field(None, alias="from")
    to_date: datetime | None = Field(None, alias="to")
    resource_type: str | None = None
    actor_id: str | None = None
    limit: int = Field(10000, ge=1, le=50000)

    model_config = {"populate_by_name": True}
