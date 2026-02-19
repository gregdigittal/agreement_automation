from pydantic import BaseModel, EmailStr, Field


class CreateContactInput(BaseModel):
    name: str = Field(...)
    email: EmailStr | None = None
    role: str | None = None
    is_signer: bool = Field(False, alias="isSigner")

    model_config = {"populate_by_name": True}


class UpdateContactInput(BaseModel):
    name: str | None = None
    email: EmailStr | None = None
    role: str | None = None
    is_signer: bool | None = Field(None, alias="isSigner")

    model_config = {"populate_by_name": True}
