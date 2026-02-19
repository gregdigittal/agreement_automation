# Cursor Prompt — Laravel Migration Phase C: Python AI Worker Microservice

## Context

Phases A and B are complete. The `ai-worker/` directory exists with a minimal FastAPI stub. Phase C extracts the Python AI code from `apps/api/app/ai/` and `apps/api/app/ai_analysis/` into the standalone `ai-worker/` service, adapts it to use SQLAlchemy+MySQL instead of Supabase, and wires up the `ProcessAiAnalysis` Laravel Job to call it.

**The AI worker does NOT write to the database.** It receives file content, runs Claude AI analysis, and returns the result JSON to the Laravel caller. Laravel writes results to the database.

---

## Task 1: Create `ai-worker/app/config.py`

```python
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
```

---

## Task 2: Create `ai-worker/app/deps.py`

Provides the SQLAlchemy engine and session for use in the adapted `mcp_tools.py`:

```python
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from .config import settings

engine = create_engine(settings.db_url, pool_pre_ping=True, pool_recycle=3600)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
```

---

## Task 3: Copy AI Modules Verbatim (No Changes)

Copy these files from `apps/api/app/ai/` to `ai-worker/app/ai/` **exactly as-is**:
- `agent_client.py`
- `messages_client.py`
- `workflow_generator.py`
- `schemas.py`

The only change needed: update their imports from `from app.ai.*` to `from app.ai.*` (same relative path — should be unchanged). Verify `from app.config import settings` references work with the new `config.py` in `ai-worker/app/config.py`.

**Create `ai-worker/app/ai/__init__.py`** (empty).

---

## Task 4: Create Adapted `ai-worker/app/ai/config.py`

Copy from `apps/api/app/ai/config.py` verbatim. The only change is the import path for settings:
```python
# Change this line:
from app.config import settings
# To remain the same — the ai-worker's config.py will be at app/config.py
```
The original `config.py` defines `get_task_type(analysis_type: str) -> str` returning `"simple"` or `"complex"`. Copy it unchanged.

---

## Task 5: Rewrite `ai-worker/app/ai/mcp_tools.py`

This file is **NOT copied verbatim** — it must be rewritten to use SQLAlchemy Core queries against MySQL instead of Supabase client calls. The public interface `get_tools(db_session, contract_id: str) -> list[dict]` must remain **identical** in structure — only the internals of the four private functions change.

**Source reference:** `apps/api/app/ai/mcp_tools.py` defines 4 tool functions. Port each:

```python
import structlog
from sqlalchemy import text
from sqlalchemy.orm import Session

logger = structlog.get_logger()


def get_tools(db: Session, contract_id: str) -> list[dict]:
    """Returns the same 4 tool definitions as the original, but using SQLAlchemy."""
    return [
        {
            "definition": {
                "name": "query_org_structure",
                "description": "Query the CCRS organizational structure: regions, entities, projects.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "region_id": {"type": "string"},
                        "entity_id": {"type": "string"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_org_structure(db, **kwargs),
        },
        {
            "definition": {
                "name": "query_authority_matrix",
                "description": "Query signing authority rules for a given entity/project.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "entity_id": {"type": "string"},
                        "project_id": {"type": "string"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_authority_matrix(db, **kwargs),
        },
        {
            "definition": {
                "name": "query_wiki_contracts",
                "description": "Search the WikiContracts template library for standard templates.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "category": {"type": "string"},
                        "region_id": {"type": "string"},
                        "status": {"type": "string"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_wiki_contracts(db, **kwargs),
        },
        {
            "definition": {
                "name": "query_counterparty",
                "description": "Look up counterparty details including status and contacts.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "counterparty_id": {"type": "string"},
                    },
                    "required": ["counterparty_id"],
                },
            },
            "handler": lambda **kwargs: _query_counterparty(db, **kwargs),
        },
    ]


def _query_org_structure(db: Session, region_id: str | None = None, entity_id: str | None = None) -> dict:
    try:
        if entity_id:
            rows = db.execute(
                text("SELECT e.*, r.name as region_name, r.code as region_code "
                     "FROM entities e JOIN regions r ON e.region_id = r.id "
                     "WHERE e.id = :entity_id LIMIT 1"),
                {"entity_id": entity_id}
            ).mappings().all()
            return {"entities": [dict(r) for r in rows]}
        if region_id:
            rows = db.execute(
                text("SELECT e.*, r.name as region_name FROM entities e "
                     "JOIN regions r ON e.region_id = r.id WHERE e.region_id = :region_id"),
                {"region_id": region_id}
            ).mappings().all()
            return {"entities": [dict(r) for r in rows]}
        regions = db.execute(text("SELECT * FROM regions LIMIT 50")).mappings().all()
        entities = db.execute(text("SELECT * FROM entities LIMIT 50")).mappings().all()
        return {"regions": [dict(r) for r in regions], "entities": [dict(r) for r in entities]}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_org_structure", error=str(e))
        return {"error": str(e)}


def _query_authority_matrix(db: Session, entity_id: str | None = None, project_id: str | None = None) -> dict:
    try:
        query = "SELECT * FROM signing_authority WHERE 1=1"
        params: dict = {}
        if entity_id:
            query += " AND entity_id = :entity_id"
            params["entity_id"] = entity_id
        if project_id:
            query += " AND (project_id = :project_id OR project_id IS NULL)"
            params["project_id"] = project_id
        query += " LIMIT 50"
        rows = db.execute(text(query), params).mappings().all()
        return {"signing_authority": [dict(r) for r in rows]}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_authority_matrix", error=str(e))
        return {"error": str(e)}


def _query_wiki_contracts(
    db: Session,
    category: str | None = None,
    region_id: str | None = None,
    status: str = "published"
) -> dict:
    try:
        query = ("SELECT id, name, category, region_id, version, status, description "
                 "FROM wiki_contracts WHERE status = :status")
        params: dict = {"status": status}
        if category:
            query += " AND category = :category"
            params["category"] = category
        if region_id:
            query += " AND region_id = :region_id"
            params["region_id"] = region_id
        query += " LIMIT 25"
        rows = db.execute(text(query), params).mappings().all()
        return {"templates": [dict(r) for r in rows]}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_wiki_contracts", error=str(e))
        return {"error": str(e)}


def _query_counterparty(db: Session, counterparty_id: str) -> dict:
    try:
        counterparty = db.execute(
            text("SELECT * FROM counterparties WHERE id = :id LIMIT 1"),
            {"id": counterparty_id}
        ).mappings().first()
        if not counterparty:
            return {"error": "Counterparty not found"}
        contacts = db.execute(
            text("SELECT * FROM counterparty_contacts WHERE counterparty_id = :id"),
            {"id": counterparty_id}
        ).mappings().all()
        result = dict(counterparty)
        result["counterparty_contacts"] = [dict(c) for c in contacts]
        return {"counterparty": result}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_counterparty", error=str(e))
        return {"error": str(e)}
```

---

## Task 6: Create `ai-worker/app/routers/analysis.py`

This is the main AI Worker API — a direct port of `apps/api/app/ai_analysis/service.py::trigger_analysis()`, but **without any database writes**.

```python
import base64
import structlog
from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.deps import get_db
from app.ai.agent_client import analyze_complex
from app.ai.config import get_task_type
from app.ai.messages_client import analyze_summary
from app.ai.workflow_generator import generate_workflow
from app.middleware.auth import verify_ai_worker_secret

logger = structlog.get_logger()
router = APIRouter(dependencies=[Depends(verify_ai_worker_secret)])


class AnalyzeRequest(BaseModel):
    contract_id: str
    analysis_type: str  # summary | extraction | risk | deviation | obligations
    file_content_base64: str
    file_name: str
    context: dict = {}  # optional: region_id, entity_id, counterparty_id for mcp_tools


class GenerateWorkflowRequest(BaseModel):
    description: str
    region_id: str | None = None
    entity_id: str | None = None
    project_id: str | None = None


@router.post("/analyze")
async def analyze(req: AnalyzeRequest, db: Session = Depends(get_db)):
    """
    Runs AI analysis on a contract file. Does NOT write to database.
    Returns result + usage. Caller (Laravel) writes to database.

    Port of apps/api/app/ai_analysis/service.py::trigger_analysis()
    with database writes removed.
    """
    try:
        # Decode base64 file content
        try:
            file_bytes = base64.b64decode(req.file_content_base64)
        except Exception:
            raise HTTPException(status_code=400, detail="Invalid base64 file content")

        # Extract text from PDF or DOCX (same logic as _extract_text in service.py)
        contract_text = _extract_text(file_bytes, req.file_name)
        if not contract_text.strip():
            raise HTTPException(status_code=422, detail="Could not extract text from file")

        # Import mcp_tools here to pass db session
        from app.ai.mcp_tools import get_tools
        tools = get_tools(db, req.contract_id)

        task_type = get_task_type(req.analysis_type)
        if task_type == "simple":
            result, usage = await analyze_summary(contract_text)
            result_dict = result.model_dump()
        else:
            result_dict, usage = await analyze_complex(
                req.analysis_type,
                contract_text,
                req.contract_id,
                tools,  # pass tools list instead of supabase client
            )

        return {
            "result": result_dict,
            "usage": {
                "input_tokens": usage.input_tokens,
                "output_tokens": usage.output_tokens,
                "cost_usd": usage.cost_usd,
                "processing_time_ms": usage.processing_time_ms,
                "model_used": usage.model_used,
            }
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error("analyze_failed", contract_id=req.contract_id, analysis_type=req.analysis_type, error=str(e))
        raise HTTPException(status_code=500, detail=f"Analysis failed: {str(e)}")


@router.post("/generate-workflow")
async def generate_workflow_endpoint(req: GenerateWorkflowRequest):
    """
    Generate a workflow template using AI.
    Port of apps/api/app/ai/workflow_generator.py::generate_workflow()
    """
    try:
        result = await generate_workflow(
            description=req.description,
            region_id=req.region_id,
            entity_id=req.entity_id,
            project_id=req.project_id,
        )
        return result
    except Exception as e:
        logger.error("generate_workflow_failed", error=str(e))
        raise HTTPException(status_code=500, detail=f"Workflow generation failed: {str(e)}")


def _extract_text(file_bytes: bytes, file_name: str) -> str:
    """Port of apps/api/app/ai_analysis/service.py::_extract_text()"""
    if file_name.lower().endswith(".pdf"):
        try:
            import fitz
            doc = fitz.open(stream=file_bytes, filetype="pdf")
            return "\n".join(page.get_text() for page in doc)
        except Exception:
            return file_bytes.decode("utf-8", errors="ignore")
    if file_name.lower().endswith((".docx", ".doc")):
        try:
            import docx
            from io import BytesIO
            doc = docx.Document(BytesIO(file_bytes))
            return "\n".join(p.text for p in doc.paragraphs)
        except Exception:
            return file_bytes.decode("utf-8", errors="ignore")
    return file_bytes.decode("utf-8", errors="ignore")
```

**Important:** The `analyze_complex()` function in `agent_client.py` was originally written to accept `(analysis_type, contract_text, contract_id, supabase_client)`. Since we're passing a `tools` list instead, you need to update `agent_client.py`'s `analyze_complex()` signature to accept `tools: list[dict]` as the fourth parameter instead of `supabase: Client`. The tools list format is identical — only the source changes.

Update this line in `ai-worker/app/ai/agent_client.py`:
```python
# FROM:
async def analyze_complex(analysis_type: str, contract_text: str, contract_id: str, supabase: Client) -> tuple[dict, AnalysisUsage]:
    tools = get_tools(supabase, contract_id)
# TO:
async def analyze_complex(analysis_type: str, contract_text: str, contract_id: str, tools: list[dict]) -> tuple[dict, AnalysisUsage]:
    # tools already provided — no need to call get_tools() here
```

---

## Task 7: Create `ai-worker/app/middleware/auth.py`

```python
from fastapi import Header, HTTPException
from app.config import settings


async def verify_ai_worker_secret(x_ai_worker_secret: str = Header(...)):
    """Validates the shared secret header for internal service-to-service auth."""
    if not x_ai_worker_secret or x_ai_worker_secret != settings.ai_worker_secret:
        raise HTTPException(status_code=401, detail="Invalid AI Worker secret")
```

---

## Task 8: Create `ai-worker/app/routers/health.py`

```python
from fastapi import APIRouter
from app.config import settings

router = APIRouter()


@router.get("/health")
async def health():
    return {
        "status": "ok",
        "service": "ccrs-ai-worker",
        "model": settings.ai_model,
    }
```

---

## Task 9: Update `ai-worker/app/main.py`

Replace the stub with the full FastAPI application:

```python
import structlog
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.routers import analysis, health

# Configure structured logging
structlog.configure(
    wrapper_class=structlog.make_filtering_bound_logger(
        {"debug": 10, "info": 20, "warning": 30, "error": 40}.get(settings.log_level.lower(), 20)
    )
)

app = FastAPI(
    title="CCRS AI Worker",
    version="1.0.0",
    description="Internal AI microservice for CCRS contract analysis",
    docs_url=None,  # Disable public docs in production
    redoc_url=None,
)

# CORS: allow only internal calls (no public browser access)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://ccrs_laravel:8000", "http://app:8000"],
    allow_methods=["POST", "GET"],
    allow_headers=["X-AI-Worker-Secret", "Content-Type"],
)

app.include_router(health.router, tags=["health"])
app.include_router(analysis.router, prefix="/api/v1", tags=["analysis"])

# Also mount /analyze and /generate-workflow at root for backwards compatibility
app.include_router(analysis.router, tags=["analysis-root"])
```

---

## Task 10: Implement `app/Services/AiWorkerClient.php`

Full implementation replacing the placeholder from Phase B:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\RequestException;

class AiWorkerClient
{
    private string $baseUrl;
    private string $secret;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('ccrs.ai_worker_url');
        $this->secret = config('ccrs.ai_worker_secret');
        $this->timeout = config('ccrs.ai_analysis_timeout', 120);
    }

    /**
     * Run AI analysis on a contract file.
     * Downloads file from S3, base64-encodes it, sends to ai-worker.
     * Returns result and usage data. Does NOT write to DB.
     */
    public function analyze(
        string $contractId,
        string $analysisType,
        string $storagePath,
        string $fileName,
        array $context = []
    ): array {
        // Download contract file from S3
        $fileContent = Storage::disk('s3')->get($storagePath);
        if (!$fileContent) {
            throw new \RuntimeException("Could not download contract file from storage: {$storagePath}");
        }

        $response = Http::withHeaders([
                'X-AI-Worker-Secret' => $this->secret,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/analyze", [
                'contract_id' => $contractId,
                'analysis_type' => $analysisType,
                'file_content_base64' => base64_encode($fileContent),
                'file_name' => $fileName,
                'context' => $context,
            ]);

        $response->throw(); // throws RequestException on 4xx/5xx
        return $response->json();
    }

    /**
     * Generate a workflow template using AI.
     */
    public function generateWorkflow(string $description, ?string $regionId = null, ?string $entityId = null): array
    {
        $response = Http::withHeaders(['X-AI-Worker-Secret' => $this->secret])
            ->timeout(60)
            ->post("{$this->baseUrl}/generate-workflow", [
                'description' => $description,
                'region_id' => $regionId,
                'entity_id' => $entityId,
            ]);

        $response->throw();
        return $response->json();
    }

    /**
     * Health check.
     */
    public function health(): array
    {
        $response = Http::timeout(5)->get("{$this->baseUrl}/health");
        return $response->json();
    }
}
```

Register in `app/Providers/AppServiceProvider.php`:
```php
$this->app->singleton(AiWorkerClient::class, fn() => new AiWorkerClient());
```

---

## Task 11: Implement `app/Jobs/ProcessAiAnalysis.php`

Full implementation replacing the Phase B placeholder. This is the port of `apps/api/app/ai_analysis/service.py::trigger_analysis()` — the database write side.

```php
<?php

namespace App\Jobs;

use App\Models\AiAnalysisResult;
use App\Models\AiExtractedField;
use App\Models\Contract;
use App\Models\ObligationsRegister;
use App\Services\AiWorkerClient;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 2;

    public function __construct(
        public readonly string $contractId,
        public readonly string $analysisType,
        public readonly ?string $actorId = null,
        public readonly ?string $actorEmail = null,
    ) {}

    public function handle(AiWorkerClient $client): void
    {
        // Load contract
        $contract = Contract::find($this->contractId);
        if (!$contract) {
            Log::error('ProcessAiAnalysis: contract not found', ['contract_id' => $this->contractId]);
            return;
        }

        if (!$contract->storage_path) {
            Log::error('ProcessAiAnalysis: no storage_path', ['contract_id' => $this->contractId]);
            return;
        }

        // Create analysis record (status=pending → processing)
        $analysis = AiAnalysisResult::create([
            'id' => Str::uuid()->toString(),
            'contract_id' => $this->contractId,
            'analysis_type' => $this->analysisType,
            'status' => 'processing',
        ]);

        try {
            // Call AI worker
            $response = $client->analyze(
                contractId: $this->contractId,
                analysisType: $this->analysisType,
                storagePath: $contract->storage_path,
                fileName: $contract->file_name ?? 'contract.pdf',
                context: [
                    'region_id' => $contract->region_id,
                    'entity_id' => $contract->entity_id,
                    'counterparty_id' => $contract->counterparty_id,
                ],
            );

            $result = $response['result'] ?? [];
            $usage = $response['usage'] ?? [];

            // Update analysis record with results
            $analysis->update([
                'status' => 'completed',
                'result' => $result,
                'model_used' => $usage['model_used'] ?? null,
                'token_usage_input' => $usage['input_tokens'] ?? null,
                'token_usage_output' => $usage['output_tokens'] ?? null,
                'cost_usd' => $usage['cost_usd'] ?? null,
                'processing_time_ms' => $usage['processing_time_ms'] ?? null,
                'confidence_score' => $result['overall_risk_score'] ?? $result['confidence'] ?? null,
            ]);

            // Persist extracted fields (for extraction analysis type)
            if ($this->analysisType === 'extraction' && isset($result['fields'])) {
                foreach ($result['fields'] as $field) {
                    AiExtractedField::create([
                        'id' => Str::uuid()->toString(),
                        'contract_id' => $this->contractId,
                        'analysis_id' => $analysis->id,
                        'field_name' => $field['field_name'] ?? '',
                        'field_value' => $field['field_value'] ?? null,
                        'evidence_clause' => $field['evidence_clause'] ?? null,
                        'evidence_page' => $field['evidence_page'] ?? null,
                        'confidence' => $field['confidence'] ?? null,
                    ]);
                }
            }

            // Persist obligations (for obligations analysis type)
            if ($this->analysisType === 'obligations' && isset($result['obligations'])) {
                foreach ($result['obligations'] as $obl) {
                    ObligationsRegister::create([
                        'id' => Str::uuid()->toString(),
                        'contract_id' => $this->contractId,
                        'analysis_id' => $analysis->id,
                        'obligation_type' => $obl['obligation_type'] ?? 'other',
                        'description' => $obl['description'] ?? '',
                        'due_date' => $obl['due_date'] ?? null,
                        'recurrence' => $obl['recurrence'] ?? null,
                        'responsible_party' => $obl['responsible_party'] ?? null,
                        'evidence_clause' => $obl['evidence_clause'] ?? null,
                        'confidence' => $obl['confidence'] ?? null,
                        'status' => 'active',
                    ]);
                }
            }

            // Audit log
            AuditService::log(
                action: 'ai_analysis_completed',
                resourceType: 'contract',
                resourceId: $this->contractId,
                details: [
                    'analysis_type' => $this->analysisType,
                    'analysis_id' => $analysis->id,
                    'cost_usd' => $usage['cost_usd'] ?? null,
                ],
            );

            Log::info('ProcessAiAnalysis completed', [
                'contract_id' => $this->contractId,
                'analysis_type' => $this->analysisType,
                'cost_usd' => $usage['cost_usd'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessAiAnalysis failed', [
                'contract_id' => $this->contractId,
                'error' => $e->getMessage(),
            ]);
            $analysis->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
```

---

## Task 12: Add AI Analysis Trigger to ContractResource

In `app/Filament/Resources/ContractResource.php`, update the `trigger_ai_analysis` action that was stubbed in Phase B:

```php
Tables\Actions\Action::make('trigger_ai_analysis')
    ->label('AI Analysis')
    ->icon('heroicon-o-cpu-chip')
    ->form([
        Forms\Components\Select::make('analysis_type')
            ->options([
                'summary' => 'Summary',
                'extraction' => 'Field Extraction',
                'risk' => 'Risk Assessment',
                'deviation' => 'Template Deviation',
                'obligations' => 'Obligations Register',
            ])
            ->required(),
    ])
    ->action(function (Contract $record, array $data) {
        if (!$record->storage_path) {
            Filament\Notifications\Notification::make()
                ->title('No file uploaded')
                ->body('Upload a contract file before running AI analysis.')
                ->danger()
                ->send();
            return;
        }
        \App\Jobs\ProcessAiAnalysis::dispatch(
            $record->id,
            $data['analysis_type'],
            auth()->id(),
            auth()->user()?->email,
        );
        Filament\Notifications\Notification::make()
            ->title('AI Analysis queued')
            ->body("Analysis will complete in the background.")
            ->success()
            ->send();
    })
    ->visible(fn(Contract $record) => $record->storage_path !== null),
```

Also add `AiWorkerClient::generateWorkflow()` to the WorkflowTemplateResource "Generate AI" action:

```php
Tables\Actions\Action::make('generate_ai')
    ->label('Generate with AI')
    ->form([
        Forms\Components\Textarea::make('description')
            ->label('Describe the workflow')
            ->required()
            ->rows(3),
    ])
    ->action(function (array $data, $livewire) {
        try {
            $client = app(\App\Services\AiWorkerClient::class);
            $result = $client->generateWorkflow($data['description']);
            $livewire->data['stages'] = $result['stages'] ?? [];
            Filament\Notifications\Notification::make()
                ->title('Workflow generated')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Filament\Notifications\Notification::make()
                ->title('Generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }),
```

---

## Task 13: Create `ai-worker/app/routers/__init__.py`

Empty `__init__.py` files for all subdirectories:
- `ai-worker/app/__init__.py`
- `ai-worker/app/ai/__init__.py`
- `ai-worker/app/routers/__init__.py`
- `ai-worker/app/middleware/__init__.py`

---

## Verification Checklist

After completing all tasks, verify:

1. **AI Worker health:** `curl http://localhost:8001/health` returns `{"status":"ok","service":"ccrs-ai-worker","model":"claude-sonnet-4-6"}`

2. **AI Worker analyze (with a real base64 PDF):**
   ```bash
   curl -X POST http://localhost:8001/analyze \
     -H "X-AI-Worker-Secret: changeme" \
     -H "Content-Type: application/json" \
     -d '{"contract_id":"test","analysis_type":"summary","file_content_base64":"<base64_pdf>","file_name":"test.pdf","context":{}}'
   ```
   Returns `{"result": {...}, "usage": {...}}`

3. **AI Worker auth guard:** Same request without header returns HTTP 401.

4. **Filament trigger:** Open a Contract with a stored PDF → click AI Analysis → select "Summary" → click submit → check `docker compose exec app php artisan queue:work --once` processes the job.

5. **Database results:** After job completes, `ai_analysis_results` table contains a row with `status=completed` for the contract.

6. **Extraction results:** Run `analysis_type=extraction` on a contract → check `ai_extracted_fields` table is populated.

7. **Obligations results:** Run `analysis_type=obligations` → check `obligations_register` is populated.

8. **MCP tools:** In the ai_worker logs, verify that tool calls (`query_org_structure`, `query_counterparty`, etc.) return data without errors when running complex analysis types (risk, deviation, obligations).
