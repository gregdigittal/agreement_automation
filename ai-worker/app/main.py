from fastapi import FastAPI

app = FastAPI(title="CCRS AI Worker", version="1.0.0")

@app.get("/health")
async def health():
    return {"status": "ok", "service": "ai-worker"}
