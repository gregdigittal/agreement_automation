from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    supabase_url: str
    supabase_service_role_key: str
    jwt_secret: str

    azure_ad_client_id: str | None = None
    azure_ad_client_secret: str | None = None
    azure_ad_issuer: str | None = None

    boldsign_api_key: str | None = None
    boldsign_api_url: str | None = None
    tito_api_key: str | None = None

    anthropic_api_key: str | None = None
    ai_model: str = "claude-sonnet-4-5-20250929"
    ai_agent_model: str = "claude-sonnet-4-5-20250929"
    ai_max_budget_usd: float = 5.0
    ai_analysis_timeout: int = 120

    sendgrid_api_key: str | None = None
    notification_from_email: str = "noreply@ccrs.digittal.com"

    scheduler_enabled: bool = True

    port: int = 4000
    cors_origin: str = "http://localhost:3000"
    log_level: str = "info"

    model_config = {
        "env_file": ".env",
        "env_file_encoding": "utf-8",
        "extra": "ignore",
    }


settings = Settings()
