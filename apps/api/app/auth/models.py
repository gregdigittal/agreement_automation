from enum import Enum

from pydantic import BaseModel


class Role(str, Enum):
    SYSTEM_ADMIN = "System Admin"
    LEGAL = "Legal"
    COMMERCIAL = "Commercial"
    FINANCE = "Finance"
    OPERATIONS = "Operations"
    AUDIT = "Audit"


class CurrentUser(BaseModel):
    id: str
    email: str | None = None
    roles: list[str] = []
    ip_address: str | None = None
