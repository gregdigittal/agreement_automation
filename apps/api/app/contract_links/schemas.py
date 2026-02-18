from typing import Literal

from pydantic import BaseModel


class RenewalInput(BaseModel):
    type: Literal["extension", "new_version"]
