from pydantic import BaseModel


class AttachLanguageInput(BaseModel):
    language_code: str
    is_primary: bool = False
