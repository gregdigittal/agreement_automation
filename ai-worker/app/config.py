from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    anthropic_api_key: str
    ai_model: str = "claude-sonnet-4-6"
    ai_agent_model: str = "claude-sonnet-4-6"
    ai_max_budget_usd: float = 5.0
    ai_analysis_timeout: int = 120
    db_url: str  # mysql+pymysql://ccrs:password@mysql:3306/ccrs
    ai_worker_secret: str  # shared secret for X-AI-Worker-Secret header auth
    log_level: str = "info"

    class Config:
        env_file = ".env"


settings = Settings()
