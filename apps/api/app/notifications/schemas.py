from pydantic import BaseModel, Field


class CreateNotificationInput(BaseModel):
    recipient_email: str = Field(..., alias="recipientEmail")
    channel: str = Field("email", pattern="^(email|teams|calendar)$")
    subject: str
    body: str
    related_resource_type: str | None = Field(None, alias="relatedResourceType")
    related_resource_id: str | None = Field(None, alias="relatedResourceId")

    model_config = {"populate_by_name": True}
