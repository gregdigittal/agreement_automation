from pydantic import BaseModel


class SendToSignInput(BaseModel):
    message: str | None = None


class BoldsignWebhookPayload(BaseModel):
    event: str | None = None
    document_id: str | None = None
    status: str | None = None
    data: dict | None = None
