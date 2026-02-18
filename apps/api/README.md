# CCRS API (Python FastAPI)

Contract & Merchant Agreement Repository System — backend API.

## Local run

```bash
python -m venv .venv
source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
# Edit .env: SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY, JWT_SECRET
uvicorn app.main:app --reload --port 4000
```

- API: http://localhost:4000  
- Docs: http://localhost:4000/docs  

Use the same `JWT_SECRET` as `AUTH_SECRET` on the Next.js app.
