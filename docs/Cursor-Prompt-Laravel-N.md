# Cursor Prompt — Laravel Migration Phase N: Phase 2A — Clause Redlining & AI Negotiation Engine

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through M were executed.

This is the first Phase 2 prompt. The clause redlining engine was scoped out of Phase 1 and exists only as a documented stub (`app/Services/RedlineService.php` from Prompt K, Section 4.1). The requirements state: *"Full clause negotiation/redlining engine (beyond SharePoint tracked changes)"* was out of scope for Phase 1 but is now being built.

This feature enables Legal users to compare a contract's clauses against WikiContracts standard templates and get AI-suggested changes, then accept/reject/modify each suggestion in a side-by-side diff view.

The feature is gated by `config('features.redlining')` (defined in `config/features.php` from Prompt K, Section 4.3) and controlled via `FEATURE_REDLINING=true` in `.env`.

**Key dependencies from prior phases:**
- `config/features.php` and `App\Helpers\Feature` — feature flag system (Prompt K)
- `app/Services/RedlineService.php` — stub to replace (Prompt K)
- `ai-worker/` — Python FastAPI microservice on port 8001 (Prompt C)
- `app/Jobs/ProcessAiAnalysis.php` — reference for text extraction + AI Worker client pattern (Prompt C)
- `app/Services/AiWorkerClient.php` — HTTP client for AI Worker (Prompt C)
- `app/Services/AuditService.php` — audit logging helper (Prompt B)
- `wiki_contracts` table and `WikiContract` model — standard templates (Prompt A/B)
- `contracts` table with `storage_path` field — uploaded contract files (Prompt A)

---

## Task 1: Database Migration — Redline Tables

Activate and expand the Phase 2 migration stub from Prompt K. Remove the commented-out migration file `database/migrations/XXXX_phase2_redline_schema.php` and create a proper migration in its place.

### 1.1 Create Migration

Create `database/migrations/XXXX_create_redline_tables.php` (use the next sequential timestamp):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redline_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->uuid('wiki_contract_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('created_by')->nullable();
            $table->integer('total_clauses')->default(0);
            $table->integer('reviewed_clauses')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('wiki_contract_id')->references('id')->on('wiki_contracts')->nullOnDelete();
            $table->index('contract_id');
            $table->index('status');
        });

        Schema::create('redline_clauses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('session_id');
            $table->unsignedSmallInteger('clause_number');
            $table->string('clause_heading')->nullable();
            $table->longText('original_text');
            $table->longText('suggested_text')->nullable();
            $table->enum('change_type', ['unchanged', 'addition', 'deletion', 'modification']);
            $table->text('ai_rationale')->nullable();
            $table->double('confidence')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'modified'])->default('pending');
            $table->longText('final_text')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('redline_sessions')->cascadeOnDelete();
            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redline_clauses');
        Schema::dropIfExists('redline_sessions');
    }
};
```

### 1.2 Create `RedlineSession` Model

Create `app/Models/RedlineSession.php`:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedlineSession extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'contract_id',
        'wiki_contract_id',
        'status',
        'created_by',
        'total_clauses',
        'reviewed_clauses',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'summary' => 'array',
        'total_clauses' => 'integer',
        'reviewed_clauses' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function wikiContract(): BelongsTo
    {
        return $this->belongsTo(WikiContract::class);
    }

    public function clauses(): HasMany
    {
        return $this->hasMany(RedlineClause::class, 'session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if all clauses have been reviewed.
     */
    public function isFullyReviewed(): bool
    {
        return $this->total_clauses > 0
            && $this->reviewed_clauses >= $this->total_clauses;
    }

    /**
     * Get progress as a percentage (0-100).
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_clauses === 0) {
            return 0;
        }

        return (int) round(($this->reviewed_clauses / $this->total_clauses) * 100);
    }
}
```

### 1.3 Create `RedlineClause` Model

Create `app/Models/RedlineClause.php`:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedlineClause extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'session_id',
        'clause_number',
        'clause_heading',
        'original_text',
        'suggested_text',
        'change_type',
        'ai_rationale',
        'confidence',
        'status',
        'final_text',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'clause_number' => 'integer',
        'confidence' => 'double',
        'reviewed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(RedlineSession::class, 'session_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether this clause has a material change suggested.
     */
    public function hasMaterialChange(): bool
    {
        return $this->change_type !== 'unchanged';
    }
}
```

### 1.4 Add Relationship to Contract Model

In `app/Models/Contract.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function redlineSessions(): HasMany
{
    return $this->hasMany(RedlineSession::class);
}
```

---

## Task 2: AI Worker — Redline Analysis Endpoint

### 2.1 Create `ai-worker/app/redline.py`

This module contains the Claude prompt and processing logic for clause-by-clause redline analysis.

```python
"""
Redline analysis module — compares contract clauses against WikiContract templates
using Claude AI for structured diff output.
"""

import json
import os
import logging
from typing import Any

import anthropic

logger = logging.getLogger(__name__)

REDLINE_SYSTEM_PROMPT = """You are a legal contract analyst. Your task is to compare a contract against a standard template and produce a structured clause-by-clause analysis.

For each clause in the contract:
1. Identify the clause number and heading.
2. Find the most similar clause in the template (by subject matter, not just position).
3. Compare the two and classify the difference:
   - "unchanged" — the clause is substantively identical to the template
   - "modification" — the clause exists in both but has material differences (changed terms, altered conditions, different thresholds)
   - "deletion" — a template clause has been removed from the contract entirely
   - "addition" — a clause exists in the contract but has no counterpart in the template
4. For modifications, deletions, and additions, provide:
   - The suggested text (what the template says, or what should be added)
   - A plain English rationale explaining the material difference and its business/legal impact
   - A confidence score from 0.0 to 1.0 indicating how certain you are about the comparison

Focus on material deviations: changed payment terms, altered liability caps, missing indemnification, removed cure periods, added obligations, changed termination rights, modified IP ownership, and similar substantive changes. Ignore minor formatting or stylistic differences.

You MUST respond with valid JSON only, no markdown, no explanation outside the JSON."""

REDLINE_USER_PROMPT = """Compare the following contract against the standard template.

=== CONTRACT TEXT ===
{contract_text}

=== TEMPLATE TEXT ===
{template_text}

Respond with a JSON object in exactly this format:
{{
    "clauses": [
        {{
            "clause_number": 1,
            "clause_heading": "Definitions",
            "original_text": "The exact clause text from the contract",
            "suggested_text": "The corresponding template clause text (null if unchanged)",
            "change_type": "unchanged|modification|deletion|addition",
            "ai_rationale": "Plain English explanation of the difference and its impact (null if unchanged)",
            "confidence": 0.95
        }}
    ],
    "summary": {{
        "total_clauses": 15,
        "unchanged": 10,
        "modifications": 3,
        "deletions": 1,
        "additions": 1,
        "material_risk_areas": ["Liability cap reduced from 2x to 1x annual fees", "30-day cure period removed"],
        "overall_assessment": "Contract has 5 material deviations from the standard template. The most significant are the reduced liability cap and removed cure period, which increase organizational risk."
    }}
}}"""


def analyze_redline(contract_text: str, template_text: str) -> dict[str, Any]:
    """
    Use Claude to compare a contract against a template and produce
    a structured clause-by-clause redline analysis.

    Returns a dict with 'clauses' (list) and 'summary' (dict).
    """
    client = anthropic.Anthropic(api_key=os.environ.get("ANTHROPIC_API_KEY"))

    user_prompt = REDLINE_USER_PROMPT.format(
        contract_text=contract_text,
        template_text=template_text,
    )

    logger.info("Sending redline analysis request to Claude (claude-sonnet-4-6)")

    response = client.messages.create(
        model="claude-sonnet-4-6",
        max_tokens=8192,
        system=REDLINE_SYSTEM_PROMPT,
        messages=[
            {"role": "user", "content": user_prompt},
        ],
    )

    # Extract the text content from Claude's response
    raw_text = response.content[0].text.strip()

    # Parse JSON — Claude may wrap in ```json ... ``` markers
    if raw_text.startswith("```"):
        # Strip markdown code fences
        lines = raw_text.split("\n")
        lines = [l for l in lines if not l.strip().startswith("```")]
        raw_text = "\n".join(lines)

    try:
        result = json.loads(raw_text)
    except json.JSONDecodeError as e:
        logger.error(f"Failed to parse Claude response as JSON: {e}")
        logger.error(f"Raw response: {raw_text[:500]}")
        raise ValueError(f"AI returned invalid JSON: {e}") from e

    # Validate expected structure
    if "clauses" not in result:
        raise ValueError("AI response missing 'clauses' key")
    if "summary" not in result:
        raise ValueError("AI response missing 'summary' key")

    return result
```

### 2.2 Update `ai-worker/app/main.py`

Add the `/analyze-redline` endpoint to the existing FastAPI application:

```python
from fastapi import FastAPI, HTTPException, Depends, Header
from pydantic import BaseModel
import os
import logging
import mysql.connector
from datetime import datetime
import uuid

from app.redline import analyze_redline

logger = logging.getLogger(__name__)

app = FastAPI(title="CCRS AI Worker", version="1.0.0")


def verify_token(authorization: str = Header(None)):
    """Verify the shared secret token from Laravel."""
    expected = os.environ.get("AI_WORKER_SECRET", "")
    if not expected:
        return  # No secret configured — allow all (dev mode)
    if not authorization or authorization.replace("Bearer ", "") != expected:
        raise HTTPException(status_code=401, detail="Unauthorized")


def get_mysql_connection():
    """Create a MySQL connection using environment variables."""
    return mysql.connector.connect(
        host=os.environ.get("DB_HOST", "mysql"),
        port=int(os.environ.get("DB_PORT", "3306")),
        database=os.environ.get("DB_DATABASE", "ccrs"),
        user=os.environ.get("DB_USERNAME", "ccrs"),
        password=os.environ.get("DB_PASSWORD", ""),
    )


@app.get("/health")
async def health():
    return {"status": "ok", "service": "ai-worker"}


# --- Redline Analysis Endpoint ---

class RedlineRequest(BaseModel):
    contract_text: str
    template_text: str
    contract_id: str
    session_id: str


class RedlineResponse(BaseModel):
    status: str
    session_id: str
    total_clauses: int
    message: str


@app.post("/analyze-redline", response_model=RedlineResponse)
async def analyze_redline_endpoint(
    request: RedlineRequest,
    _: None = Depends(verify_token),
):
    """
    Analyze a contract against a template and store clause-by-clause
    redline results directly in MySQL.

    Called by Laravel's ProcessRedlineAnalysis job.
    """
    session_id = request.session_id

    try:
        # Update session status to 'processing'
        conn = get_mysql_connection()
        cursor = conn.cursor()
        cursor.execute(
            "UPDATE redline_sessions SET status = %s, updated_at = %s WHERE id = %s",
            ("processing", datetime.utcnow(), session_id),
        )
        conn.commit()

        # Run AI analysis
        result = analyze_redline(request.contract_text, request.template_text)

        clauses = result.get("clauses", [])
        summary = result.get("summary", {})
        total_clauses = len(clauses)

        # Insert each clause into redline_clauses
        for clause in clauses:
            clause_id = str(uuid.uuid4())
            now = datetime.utcnow()
            cursor.execute(
                """
                INSERT INTO redline_clauses
                    (id, session_id, clause_number, clause_heading, original_text,
                     suggested_text, change_type, ai_rationale, confidence,
                     status, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """,
                (
                    clause_id,
                    session_id,
                    clause.get("clause_number", 0),
                    clause.get("clause_heading"),
                    clause.get("original_text", ""),
                    clause.get("suggested_text"),
                    clause.get("change_type", "unchanged"),
                    clause.get("ai_rationale"),
                    clause.get("confidence"),
                    "pending",
                    now,
                    now,
                ),
            )

        # Update session to completed with summary
        import json as json_mod

        cursor.execute(
            """
            UPDATE redline_sessions
            SET status = %s, total_clauses = %s, summary = %s, updated_at = %s
            WHERE id = %s
            """,
            (
                "completed",
                total_clauses,
                json_mod.dumps(summary),
                datetime.utcnow(),
                session_id,
            ),
        )
        conn.commit()
        cursor.close()
        conn.close()

        logger.info(
            f"Redline analysis completed for session {session_id}: "
            f"{total_clauses} clauses analyzed"
        )

        return RedlineResponse(
            status="completed",
            session_id=session_id,
            total_clauses=total_clauses,
            message=f"Analysis complete. {total_clauses} clauses compared.",
        )

    except Exception as e:
        logger.error(f"Redline analysis failed for session {session_id}: {e}")

        # Update session status to 'failed'
        try:
            conn = get_mysql_connection()
            cursor = conn.cursor()
            cursor.execute(
                """
                UPDATE redline_sessions
                SET status = %s, error_message = %s, updated_at = %s
                WHERE id = %s
                """,
                ("failed", str(e)[:2000], datetime.utcnow(), session_id),
            )
            conn.commit()
            cursor.close()
            conn.close()
        except Exception as db_err:
            logger.error(f"Failed to update session status to failed: {db_err}")

        raise HTTPException(
            status_code=500,
            detail=f"Redline analysis failed: {str(e)}",
        )
```

### 2.3 Update `ai-worker/requirements.txt`

Ensure these packages are listed (add any that are missing):

```
fastapi>=0.104.0
uvicorn>=0.24.0
anthropic>=0.40.0
mysql-connector-python>=8.2.0
pydantic>=2.5.0
python-dotenv>=1.0.0
```

---

## Task 3: Laravel Job — ProcessRedlineAnalysis

Create `app/Jobs/ProcessRedlineAnalysis.php`:

```php
<?php

namespace App\Jobs;

use App\Models\RedlineSession;
use App\Services\AiWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessRedlineAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run before timing out.
     * Redline analysis with Claude can take several minutes for long contracts.
     */
    public int $timeout = 600;

    public function __construct(
        public string $sessionId,
    ) {}

    public function handle(AiWorkerClient $aiClient): void
    {
        $session = RedlineSession::with(['contract', 'wikiContract'])->find($this->sessionId);

        if (!$session) {
            Log::error("ProcessRedlineAnalysis: session {$this->sessionId} not found");
            return;
        }

        $contract = $session->contract;
        $wikiContract = $session->wikiContract;

        try {
            // 1. Extract contract text from uploaded file
            $contractText = $this->extractText($contract->storage_path);
            if (empty($contractText)) {
                throw new \RuntimeException(
                    "Failed to extract text from contract file: {$contract->storage_path}"
                );
            }

            // 2. Extract template text from WikiContract
            $templateText = '';
            if ($wikiContract && $wikiContract->storage_path) {
                $templateText = $this->extractText($wikiContract->storage_path);
            } elseif ($wikiContract && $wikiContract->content) {
                // WikiContract may store content as a text field
                $templateText = $wikiContract->content;
            }

            if (empty($templateText)) {
                throw new \RuntimeException(
                    'No template text available — WikiContract has no storage_path or content.'
                );
            }

            // 3. Call AI Worker /analyze-redline endpoint
            $response = $aiClient->post('/analyze-redline', [
                'contract_text' => $contractText,
                'template_text' => $templateText,
                'contract_id' => $contract->id,
                'session_id' => $session->id,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException(
                    "AI Worker returned error: HTTP {$response->status()} — " .
                    ($response->json('detail') ?? $response->body())
                );
            }

            Log::info("ProcessRedlineAnalysis: completed for session {$this->sessionId}");

        } catch (\Throwable $e) {
            Log::error("ProcessRedlineAnalysis failed: {$e->getMessage()}", [
                'session_id' => $this->sessionId,
                'exception' => $e,
            ]);

            // Update session status to failed
            $session->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e; // Re-throw so the queue system can retry if attempts remain
        }
    }

    /**
     * Extract plain text from a PDF or DOCX file stored in S3.
     *
     * Uses the same extraction pattern as ProcessAiAnalysis — if a shared
     * text extraction service exists (e.g., TextExtractorService), use that
     * instead of duplicating the logic here.
     */
    private function extractText(string $storagePath): string
    {
        $contents = Storage::disk('s3')->get($storagePath);

        if (!$contents) {
            return '';
        }

        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));

        // Write to temp file for processing
        $tempPath = tempnam(sys_get_temp_dir(), 'redline_') . '.' . $extension;
        file_put_contents($tempPath, $contents);

        try {
            if ($extension === 'pdf') {
                // Use pdftotext (poppler-utils) if available
                $output = '';
                $exitCode = 0;
                exec("pdftotext " . escapeshellarg($tempPath) . " -", $outputLines, $exitCode);
                if ($exitCode === 0) {
                    $output = implode("\n", $outputLines);
                }
                return $output;
            }

            if (in_array($extension, ['docx', 'doc'])) {
                // Use python-docx via a quick script, or antiword/docx2txt
                $output = '';
                $exitCode = 0;
                exec(
                    "python3 -c \"import docx; " .
                    "doc = docx.Document(" . escapeshellarg($tempPath) . "); " .
                    "print('\\n'.join(p.text for p in doc.paragraphs))\"",
                    $outputLines,
                    $exitCode
                );
                if ($exitCode === 0) {
                    $output = implode("\n", $outputLines);
                }
                return $output;
            }

            // Plain text fallback
            return $contents;
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $session = RedlineSession::find($this->sessionId);
        if ($session && $session->status !== 'failed') {
            $session->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
    }
}
```

---

## Task 4: Implement RedlineService (Replace Stub)

Replace the existing stub in `app/Services/RedlineService.php` with the full implementation. Remove the `RuntimeException` throw and implement all methods.

```php
<?php

namespace App\Services;

use App\Jobs\ProcessRedlineAnalysis;
use App\Models\Contract;
use App\Models\RedlineClause;
use App\Models\RedlineSession;
use App\Models\User;
use App\Models\WikiContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

/**
 * RedlineService — Phase 2: AI-assisted clause negotiation and redlining.
 *
 * Compares a contract's clauses against a WikiContract standard template
 * using Claude AI, then allows legal users to accept/reject/modify each
 * suggested change in a side-by-side diff view.
 */
class RedlineService
{
    /**
     * Start a new redline session for a contract.
     *
     * If no template is provided, auto-selects the most recent published
     * WikiContract matching the contract's region.
     */
    public function startSession(Contract $contract, ?WikiContract $template, User $actor): RedlineSession
    {
        // Auto-select template if not provided
        if (!$template) {
            $template = WikiContract::where('status', 'published')
                ->where('region_id', $contract->region_id)
                ->latest('version')
                ->first();
        }

        if (!$template) {
            throw new \RuntimeException(
                'No published WikiContract template found for region ' .
                ($contract->region?->name ?? $contract->region_id) .
                '. Upload a template before starting a redline review.'
            );
        }

        $session = RedlineSession::create([
            'contract_id' => $contract->id,
            'wiki_contract_id' => $template->id,
            'status' => 'pending',
            'created_by' => $actor->id,
        ]);

        // Dispatch the background job
        ProcessRedlineAnalysis::dispatch($session->id);

        // Audit log
        AuditService::log('redline_session.start', 'redline_session', $session->id, [
            'contract_id' => $contract->id,
            'template_id' => $template->id,
            'template_name' => $template->name ?? $template->title ?? 'Unknown',
        ], $actor);

        Log::info("Redline session {$session->id} started for contract {$contract->id}", [
            'template_id' => $template->id,
            'actor' => $actor->email,
        ]);

        return $session;
    }

    /**
     * Review a single clause — accept, reject, or modify.
     *
     * @param RedlineClause $clause   The clause to review.
     * @param string        $status   One of: 'accepted', 'rejected', 'modified'.
     * @param string|null   $finalText  Required when $status is 'modified'.
     * @param User          $actor    The user performing the review.
     */
    public function reviewClause(
        RedlineClause $clause,
        string $status,
        ?string $finalText,
        User $actor,
    ): RedlineClause {
        if (!in_array($status, ['accepted', 'rejected', 'modified'])) {
            throw new \InvalidArgumentException("Invalid review status: {$status}");
        }

        if ($status === 'modified' && empty($finalText)) {
            throw new \InvalidArgumentException(
                'Final text is required when status is "modified".'
            );
        }

        // Determine the final text based on the review action
        $resolvedFinalText = match ($status) {
            'accepted' => $clause->suggested_text ?? $clause->original_text,
            'rejected' => $clause->original_text,
            'modified' => $finalText,
        };

        $clause->update([
            'status' => $status,
            'final_text' => $resolvedFinalText,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        // Update session progress
        $session = $clause->session;
        $reviewedCount = $session->clauses()->whereNotNull('reviewed_at')->count();
        $session->update([
            'reviewed_clauses' => $reviewedCount,
        ]);

        AuditService::log('redline_clause.review', 'redline_clause', $clause->id, [
            'session_id' => $session->id,
            'clause_number' => $clause->clause_number,
            'status' => $status,
        ], $actor);

        return $clause->fresh();
    }

    /**
     * Generate a final DOCX document from all reviewed clauses.
     *
     * Compiles all final_text values in clause order into a Word document
     * and uploads it to S3.
     *
     * @return string  The S3 storage path of the generated DOCX.
     *
     * @throws \RuntimeException if not all clauses have been reviewed.
     */
    public function generateFinalDocument(RedlineSession $session): string
    {
        $session->loadMissing(['clauses', 'contract']);

        // Verify all clauses are reviewed
        $unreviewedCount = $session->clauses()
            ->whereNull('reviewed_at')
            ->count();

        if ($unreviewedCount > 0) {
            throw new \RuntimeException(
                "{$unreviewedCount} clause(s) have not been reviewed. " .
                'All clauses must be accepted, rejected, or modified before generating the final document.'
            );
        }

        // Build the DOCX
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setCreator('CCRS — Redline Engine');
        $properties->setTitle("Redlined: {$session->contract->title}");
        $properties->setDescription('Generated by CCRS clause redlining engine.');

        $section = $phpWord->addSection();

        // Title
        $section->addTitle("Redlined Contract: {$session->contract->title}", 1);
        $section->addTextBreak();

        // Add each clause in order
        $clauses = $session->clauses()
            ->orderBy('clause_number')
            ->get();

        foreach ($clauses as $clause) {
            // Clause heading
            if ($clause->clause_heading) {
                $section->addTitle(
                    "Clause {$clause->clause_number}: {$clause->clause_heading}",
                    2
                );
            } else {
                $section->addTitle("Clause {$clause->clause_number}", 2);
            }

            // Final text
            $section->addText($clause->final_text ?? $clause->original_text);

            // Add a note about the review action
            $statusLabel = match ($clause->status) {
                'accepted' => 'Accepted from template',
                'rejected' => 'Original retained',
                'modified' => 'Manually modified',
                default => 'Pending',
            };
            $section->addText(
                "[{$statusLabel}]",
                ['size' => 8, 'italic' => true, 'color' => '888888']
            );

            $section->addTextBreak();
        }

        // Write to temp file
        $tempPath = tempnam(sys_get_temp_dir(), 'redline_final_') . '.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        // Upload to S3
        $contractId = $session->contract_id;
        $s3Path = "contracts/{$contractId}/redline-final-{$session->id}.docx";

        Storage::disk('s3')->put($s3Path, file_get_contents($tempPath));
        @unlink($tempPath);

        Log::info("Redline final document generated: {$s3Path}", [
            'session_id' => $session->id,
            'contract_id' => $contractId,
            'total_clauses' => $clauses->count(),
        ]);

        return $s3Path;
    }
}
```

**Note:** The DOCX generation uses `phpoffice/phpword`. If not already in `composer.json`, add it:

```bash
composer require phpoffice/phpword
```

---

## Task 5: Filament UI — Redline Session Page

### 5.1 Create RedlineSessionPage

Create `app/Filament/Resources/ContractResource/Pages/RedlineSessionPage.php`:

```php
<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Helpers\Feature;
use App\Helpers\StorageHelper;
use App\Models\RedlineClause;
use App\Models\RedlineSession;
use App\Services\RedlineService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class RedlineSessionPage extends Page
{
    protected static string $resource = ContractResource::class;
    protected static string $view = 'filament.resources.contract-resource.pages.redline-session';
    protected static ?string $title = 'Redline Review';

    public RedlineSession $session;
    public string $sessionId;

    public function mount(string $record, string $session): void
    {
        // Feature gate
        if (Feature::disabled('redlining')) {
            abort(404, 'Redlining feature is not enabled.');
        }

        $this->sessionId = $session;
        $this->session = RedlineSession::with([
            'contract',
            'wikiContract',
            'clauses' => fn ($q) => $q->orderBy('clause_number'),
            'creator',
        ])->findOrFail($session);

        // Verify the session belongs to this contract
        if ($this->session->contract_id !== $record) {
            abort(404);
        }
    }

    public function getTitle(): string
    {
        return "Redline Review: {$this->session->contract->title}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            ContractResource::getUrl() => 'Contracts',
            ContractResource::getUrl('view', ['record' => $this->session->contract_id]) => $this->session->contract->title,
            'Redline Review',
        ];
    }

    /**
     * Accept a clause — use the suggested (template) text.
     */
    public function acceptClause(string $clauseId): void
    {
        $clause = RedlineClause::findOrFail($clauseId);
        app(RedlineService::class)->reviewClause($clause, 'accepted', null, auth()->user());

        $this->refreshSession();

        Notification::make()
            ->title("Clause {$clause->clause_number} accepted")
            ->success()
            ->send();
    }

    /**
     * Reject a clause — keep the original contract text.
     */
    public function rejectClause(string $clauseId): void
    {
        $clause = RedlineClause::findOrFail($clauseId);
        app(RedlineService::class)->reviewClause($clause, 'rejected', null, auth()->user());

        $this->refreshSession();

        Notification::make()
            ->title("Clause {$clause->clause_number} rejected")
            ->info()
            ->send();
    }

    /**
     * Modify a clause — user provides custom final text.
     */
    public function modifyClause(string $clauseId, string $finalText): void
    {
        $clause = RedlineClause::findOrFail($clauseId);
        app(RedlineService::class)->reviewClause($clause, 'modified', $finalText, auth()->user());

        $this->refreshSession();

        Notification::make()
            ->title("Clause {$clause->clause_number} modified")
            ->warning()
            ->send();
    }

    /**
     * Generate the final redlined document.
     */
    public function generateFinalDocument(): void
    {
        try {
            $path = app(RedlineService::class)->generateFinalDocument($this->session);

            Notification::make()
                ->title('Final document generated')
                ->body('The redlined document has been saved. You can download it from the contract files.')
                ->success()
                ->send();

        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Cannot generate document')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Refresh session data after a clause review action.
     */
    private function refreshSession(): void
    {
        $this->session = $this->session->fresh([
            'contract',
            'wikiContract',
            'clauses' => fn ($q) => $q->orderBy('clause_number'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateFinal')
                ->label('Generate Final Document')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->disabled(fn (): bool => !$this->session->isFullyReviewed())
                ->requiresConfirmation()
                ->modalHeading('Generate Final Document')
                ->modalDescription('This will compile all reviewed clauses into a final DOCX document and upload it to the contract\'s file storage.')
                ->action(fn () => $this->generateFinalDocument()),

            Action::make('backToContract')
                ->label('Back to Contract')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ContractResource::getUrl('view', ['record' => $this->session->contract_id])),
        ];
    }
}
```

### 5.2 Create the Blade View

Create `resources/views/filament/resources/contract-resource/pages/redline-session.blade.php`:

```blade
<x-filament-panels::page>
    {{-- Session Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                    {{ $session->contract->title }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Template: {{ $session->wikiContract?->name ?? $session->wikiContract?->title ?? 'N/A' }}
                    &middot;
                    Started by {{ $session->creator?->name ?? 'Unknown' }}
                    &middot;
                    {{ $session->created_at->diffForHumans() }}
                </p>
            </div>
            <div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    @switch($session->status)
                        @case('pending') bg-yellow-100 text-yellow-800 @break
                        @case('processing') bg-blue-100 text-blue-800 @break
                        @case('completed') bg-green-100 text-green-800 @break
                        @case('failed') bg-red-100 text-red-800 @break
                    @endswitch
                ">
                    {{ ucfirst($session->status) }}
                </span>
            </div>
        </div>

        {{-- Progress Bar --}}
        @if ($session->status === 'completed')
            <div class="mt-4">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                    <span>Review Progress</span>
                    <span>{{ $session->reviewed_clauses }} / {{ $session->total_clauses }} clauses reviewed ({{ $session->progress_percentage }}%)</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div
                        class="bg-primary-600 h-3 rounded-full transition-all duration-300"
                        style="width: {{ $session->progress_percentage }}%"
                    ></div>
                </div>
            </div>
        @endif

        {{-- Error Message --}}
        @if ($session->status === 'failed' && $session->error_message)
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-sm text-red-800 dark:text-red-200">
                    <strong>Error:</strong> {{ $session->error_message }}
                </p>
            </div>
        @endif

        {{-- Summary --}}
        @if ($session->status === 'completed' && $session->summary)
            <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">AI Analysis Summary</h4>
                @if (isset($session->summary['overall_assessment']))
                    <p class="text-sm text-blue-800 dark:text-blue-200 mb-2">{{ $session->summary['overall_assessment'] }}</p>
                @endif
                @if (isset($session->summary['material_risk_areas']) && count($session->summary['material_risk_areas']) > 0)
                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mt-2">Material Risk Areas:</p>
                    <ul class="list-disc list-inside text-sm text-blue-700 dark:text-blue-300">
                        @foreach ($session->summary['material_risk_areas'] as $risk)
                            <li>{{ $risk }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="flex gap-4 mt-3 text-xs text-blue-600 dark:text-blue-400">
                    <span>Unchanged: {{ $session->summary['unchanged'] ?? 0 }}</span>
                    <span>Modifications: {{ $session->summary['modifications'] ?? 0 }}</span>
                    <span>Deletions: {{ $session->summary['deletions'] ?? 0 }}</span>
                    <span>Additions: {{ $session->summary['additions'] ?? 0 }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Pending/Processing State --}}
    @if (in_array($session->status, ['pending', 'processing']))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-12 text-center" wire:poll.5s="$refresh">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto mb-4"></div>
            <p class="text-gray-600 dark:text-gray-400">
                AI is analyzing the contract clauses against the template...
            </p>
            <p class="text-sm text-gray-500 mt-2">
                This page will update automatically when the analysis is complete.
            </p>
        </div>
    @endif

    {{-- Clause-by-Clause Diff View --}}
    @if ($session->status === 'completed')
        <div class="space-y-6">
            @foreach ($session->clauses as $clause)
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden
                        @if ($clause->status !== 'pending') ring-1
                            @if ($clause->status === 'accepted') ring-green-300 dark:ring-green-700
                            @elseif ($clause->status === 'rejected') ring-red-300 dark:ring-red-700
                            @elseif ($clause->status === 'modified') ring-amber-300 dark:ring-amber-700
                            @endif
                        @endif"
                    id="clause-{{ $clause->clause_number }}"
                >
                    {{-- Clause Header --}}
                    <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-b dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                Clause {{ $clause->clause_number }}
                            </span>
                            @if ($clause->clause_heading)
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    — {{ $clause->clause_heading }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            {{-- Change Type Badge --}}
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                @switch($clause->change_type)
                                    @case('unchanged') bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 @break
                                    @case('addition') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 @break
                                    @case('deletion') bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 @break
                                    @case('modification') bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300 @break
                                @endswitch
                            ">
                                {{ ucfirst($clause->change_type) }}
                            </span>

                            {{-- Confidence Badge --}}
                            @if ($clause->confidence !== null)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    {{ $clause->confidence >= 0.8 ? 'bg-green-100 text-green-700' : ($clause->confidence >= 0.5 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                                    {{ number_format($clause->confidence * 100) }}% confident
                                </span>
                            @endif

                            {{-- Review Status Badge --}}
                            @if ($clause->status !== 'pending')
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                    @switch($clause->status)
                                        @case('accepted') bg-green-600 text-white @break
                                        @case('rejected') bg-red-600 text-white @break
                                        @case('modified') bg-amber-600 text-white @break
                                    @endswitch
                                ">
                                    {{ ucfirst($clause->status) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Two-Column Diff View --}}
                    @if ($clause->change_type !== 'unchanged')
                        <div class="grid grid-cols-2 divide-x dark:divide-gray-700">
                            {{-- Left: Original (Contract) --}}
                            <div class="p-4">
                                <h5 class="text-xs font-semibold uppercase text-gray-500 mb-2">Original (Contract)</h5>
                                <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap bg-red-50 dark:bg-red-900/10 p-3 rounded border border-red-100 dark:border-red-900">
                                    {{ $clause->original_text }}
                                </div>
                            </div>

                            {{-- Right: Suggested (Template) --}}
                            <div class="p-4">
                                <h5 class="text-xs font-semibold uppercase text-gray-500 mb-2">Suggested (Template)</h5>
                                <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap bg-green-50 dark:bg-green-900/10 p-3 rounded border border-green-100 dark:border-green-900">
                                    {{ $clause->suggested_text ?? '(No suggested text)' }}
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Unchanged clause — show single column --}}
                        <div class="p-4">
                            <h5 class="text-xs font-semibold uppercase text-gray-500 mb-2">Clause Text (Unchanged)</h5>
                            <div class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">
                                {{ $clause->original_text }}
                            </div>
                        </div>
                    @endif

                    {{-- AI Rationale --}}
                    @if ($clause->ai_rationale)
                        <div class="px-6 py-3 bg-yellow-50 dark:bg-yellow-900/10 border-t dark:border-gray-700">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>AI Rationale:</strong> {{ $clause->ai_rationale }}
                            </p>
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    @if ($clause->change_type !== 'unchanged' && $clause->status === 'pending')
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700 flex items-center gap-3">
                            <button
                                wire:click="acceptClause('{{ $clause->id }}')"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-green-500"
                            >
                                <x-heroicon-s-check class="w-4 h-4 mr-1" />
                                Accept
                            </button>

                            <button
                                wire:click="rejectClause('{{ $clause->id }}')"
                                class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                <x-heroicon-s-x-mark class="w-4 h-4 mr-1" />
                                Reject
                            </button>

                            <div x-data="{ editing: false, modifiedText: @js($clause->suggested_text ?? $clause->original_text) }" class="flex-1">
                                <button
                                    x-show="!editing"
                                    x-on:click="editing = true"
                                    class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-amber-500"
                                >
                                    <x-heroicon-s-pencil class="w-4 h-4 mr-1" />
                                    Modify
                                </button>

                                <div x-show="editing" x-cloak class="flex-1">
                                    <textarea
                                        x-model="modifiedText"
                                        class="w-full border border-amber-300 rounded-lg p-3 text-sm dark:bg-gray-800 dark:border-amber-700 dark:text-gray-200 focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                                        rows="5"
                                        aria-label="Modified clause text"
                                    ></textarea>
                                    <div class="flex gap-2 mt-2">
                                        <button
                                            x-on:click="$wire.modifyClause('{{ $clause->id }}', modifiedText); editing = false"
                                            class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-sm rounded-lg transition"
                                        >
                                            Save Modification
                                        </button>
                                        <button
                                            x-on:click="editing = false"
                                            class="px-3 py-1.5 bg-gray-300 hover:bg-gray-400 text-gray-700 text-sm rounded-lg transition"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @elseif ($clause->status !== 'pending')
                        {{-- Already reviewed — show final text --}}
                        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900 border-t dark:border-gray-700">
                            <p class="text-xs text-gray-500">
                                Reviewed by {{ $clause->reviewer?->name ?? 'Unknown' }}
                                {{ $clause->reviewed_at?->diffForHumans() }}
                                &middot;
                                {{ ucfirst($clause->status) }}
                            </p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
```

### 5.3 Register the Route

In `app/Filament/Resources/ContractResource.php`, add the redline session page to the pages array:

```php
public static function getPages(): array
{
    return [
        // ... existing pages ...
        'redline-session' => Pages\RedlineSessionPage::route('/{record}/redline/{session}'),
    ];
}
```

### 5.4 Add "Start Redline Review" Action on ContractResource View Page

In `app/Filament/Resources/ContractResource.php`, add a header action (or table action) for starting a redline review:

```php
use App\Helpers\Feature;
use App\Models\WikiContract;
use App\Services\RedlineService;

Tables\Actions\Action::make('startRedlineReview')
    ->label('Start Redline Review')
    ->icon('heroicon-o-scale')
    ->color('info')
    ->visible(function (Contract $record): bool {
        // Only show when feature is enabled and contract has an uploaded file
        return Feature::enabled('redlining')
            && !empty($record->storage_path);
    })
    ->form([
        Forms\Components\Select::make('wiki_contract_id')
            ->label('WikiContract Template')
            ->options(function (Contract $record) {
                return WikiContract::where('status', 'published')
                    ->where('region_id', $record->region_id)
                    ->orderByDesc('version')
                    ->pluck('name', 'id')
                    ->toArray();
            })
            ->placeholder('Auto-select (latest for this region)')
            ->helperText('Choose a template to compare against, or leave blank to auto-select the latest published template for this contract\'s region.')
            ->searchable(),
    ])
    ->requiresConfirmation()
    ->modalHeading('Start Redline Review')
    ->modalDescription('This will send the contract to the AI engine for clause-by-clause comparison against the selected WikiContract template. The analysis may take a few minutes for long contracts.')
    ->action(function (Contract $record, array $data): void {
        $template = null;
        if (!empty($data['wiki_contract_id'])) {
            $template = WikiContract::find($data['wiki_contract_id']);
        }

        $session = app(RedlineService::class)->startSession(
            $record,
            $template,
            auth()->user(),
        );

        Notification::make()
            ->title('Redline review started')
            ->body('AI analysis is processing. You will be redirected to the review page.')
            ->success()
            ->send();

        // Redirect to the RedlineSessionPage
        redirect(ContractResource::getUrl('redline-session', [
            'record' => $record->id,
            'session' => $session->id,
        ]));
    }),
```

### 5.5 Add RedlineSessionsRelationManager

Create `app/Filament/Resources/ContractResource/RelationManagers/RedlineSessionsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Filament\Resources\ContractResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RedlineSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redlineSessions';
    protected static ?string $title = 'Redline Sessions';
    protected static ?string $recordTitleAttribute = 'id';

    public function isVisible(): bool
    {
        return \App\Helpers\Feature::enabled('redlining');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('wikiContract.name')
                    ->label('Template')
                    ->default('N/A'),

                Tables\Columns\TextColumn::make('total_clauses')
                    ->label('Total'),

                Tables\Columns\TextColumn::make('reviewed_clauses')
                    ->label('Reviewed'),

                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn ($record) => $record->progress_percentage . '%')
                    ->badge()
                    ->color(fn ($record) => $record->isFullyReviewed() ? 'success' : 'warning'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Started By'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => ContractResource::getUrl('redline-session', [
                        'record' => $record->contract_id,
                        'session' => $record->id,
                    ]))
                    ->visible(fn ($record) => $record->status === 'completed'),
            ]);
    }
}
```

Register this relation manager in `ContractResource`:

```php
public static function getRelations(): array
{
    return [
        // ... existing relation managers ...
        RelationManagers\RedlineSessionsRelationManager::class,
    ];
}
```

---

## Task 6: Feature Gate

Ensure the redlining feature is fully gated by `config('features.redlining')` / `FEATURE_REDLINING`.

### 6.1 Verify Feature Flag Config

In `config/features.php` (created in Prompt K), confirm the entry exists:

```php
'redlining' => env('FEATURE_REDLINING', false),
```

### 6.2 Gate the RedlineSessionPage

Already handled in Task 5.1 — the `mount()` method checks `Feature::disabled('redlining')` and aborts with 404.

### 6.3 Gate the "Start Redline Review" Action

Already handled in Task 5.4 — the `visible()` callback checks `Feature::enabled('redlining')`.

### 6.4 Gate the RelationManager

Already handled in Task 5.5 — `isVisible()` checks `Feature::enabled('redlining')`.

### 6.5 Update `.env.example`

Confirm that `.env.example` has:

```dotenv
FEATURE_REDLINING=false
```

This should already exist from Prompt L. If missing, add it.

### 6.6 Gate the AI Worker Endpoint

The AI Worker endpoint `/analyze-redline` is internal-only (behind `AI_WORKER_SECRET` token auth), so no additional feature gating is needed at the Python layer. The Laravel job will only be dispatched when the feature is enabled.

---

## Task 7: Feature Tests

Create `tests/Feature/RedlineTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Helpers\Feature;
use App\Jobs\ProcessRedlineAnalysis;
use App\Models\Contract;
use App\Models\RedlineClause;
use App\Models\RedlineSession;
use App\Models\User;
use App\Models\WikiContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedlineTest extends TestCase
{
    use RefreshDatabase;

    private User $legalUser;
    private Contract $contract;
    private WikiContract $template;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable redlining feature for tests
        config(['features.redlining' => true]);

        $this->legalUser = User::factory()->create();
        $this->legalUser->assignRole('legal');

        $this->contract = Contract::factory()->create([
            'storage_path' => 'contracts/test-contract.pdf',
            'workflow_state' => 'in_progress',
        ]);

        $this->template = WikiContract::factory()->create([
            'status' => 'published',
            'region_id' => $this->contract->region_id,
            'storage_path' => 'templates/test-template.docx',
        ]);
    }

    public function test_start_session_creates_record_and_dispatches_job(): void
    {
        Queue::fake();

        $this->actingAs($this->legalUser);

        $service = app(\App\Services\RedlineService::class);
        $session = $service->startSession($this->contract, $this->template, $this->legalUser);

        // Session created
        $this->assertDatabaseHas('redline_sessions', [
            'id' => $session->id,
            'contract_id' => $this->contract->id,
            'wiki_contract_id' => $this->template->id,
            'status' => 'pending',
            'created_by' => $this->legalUser->id,
        ]);

        // Job dispatched
        Queue::assertPushed(ProcessRedlineAnalysis::class, function ($job) use ($session) {
            return $job->sessionId === $session->id;
        });
    }

    public function test_start_session_auto_selects_template_by_region(): void
    {
        Queue::fake();

        $this->actingAs($this->legalUser);

        $service = app(\App\Services\RedlineService::class);
        $session = $service->startSession($this->contract, null, $this->legalUser);

        $this->assertEquals($this->template->id, $session->wiki_contract_id);
    }

    public function test_start_session_throws_when_no_template_available(): void
    {
        Queue::fake();

        // Delete all templates for this region
        WikiContract::where('region_id', $this->contract->region_id)->delete();

        $this->actingAs($this->legalUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No published WikiContract template found/');

        app(\App\Services\RedlineService::class)->startSession(
            $this->contract,
            null,
            $this->legalUser,
        );
    }

    public function test_review_clause_accept_updates_status_and_progress(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'wiki_contract_id' => $this->template->id,
            'status' => 'completed',
            'total_clauses' => 3,
            'reviewed_clauses' => 0,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'original_text' => 'Original clause text.',
            'suggested_text' => 'Suggested clause text from template.',
            'change_type' => 'modification',
            'status' => 'pending',
        ]);

        $service = app(\App\Services\RedlineService::class);
        $reviewed = $service->reviewClause($clause, 'accepted', null, $this->legalUser);

        $this->assertEquals('accepted', $reviewed->status);
        $this->assertEquals('Suggested clause text from template.', $reviewed->final_text);
        $this->assertEquals($this->legalUser->id, $reviewed->reviewed_by);
        $this->assertNotNull($reviewed->reviewed_at);

        // Progress updated
        $session->refresh();
        $this->assertEquals(1, $session->reviewed_clauses);
    }

    public function test_review_clause_reject_keeps_original_text(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 1,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'original_text' => 'Keep this original text.',
            'suggested_text' => 'Different suggested text.',
            'change_type' => 'modification',
            'status' => 'pending',
        ]);

        $service = app(\App\Services\RedlineService::class);
        $reviewed = $service->reviewClause($clause, 'rejected', null, $this->legalUser);

        $this->assertEquals('rejected', $reviewed->status);
        $this->assertEquals('Keep this original text.', $reviewed->final_text);
    }

    public function test_review_clause_modify_uses_custom_text(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 1,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'original_text' => 'Original text.',
            'suggested_text' => 'Suggested text.',
            'change_type' => 'modification',
            'status' => 'pending',
        ]);

        $customText = 'My custom modified clause text that combines elements of both.';

        $service = app(\App\Services\RedlineService::class);
        $reviewed = $service->reviewClause($clause, 'modified', $customText, $this->legalUser);

        $this->assertEquals('modified', $reviewed->status);
        $this->assertEquals($customText, $reviewed->final_text);
    }

    public function test_review_clause_modify_requires_final_text(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 1,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'status' => 'pending',
            'change_type' => 'modification',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\RedlineService::class)->reviewClause(
            $clause,
            'modified',
            null,
            $this->legalUser,
        );
    }

    public function test_generate_final_document_requires_all_clauses_reviewed(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 2,
            'reviewed_clauses' => 1,
        ]);

        // One reviewed, one not
        RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'status' => 'accepted',
            'reviewed_at' => now(),
        ]);
        RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 2,
            'status' => 'pending',
            'reviewed_at' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/clause\(s\) have not been reviewed/');

        app(\App\Services\RedlineService::class)->generateFinalDocument($session);
    }

    public function test_feature_gate_hides_start_redline_action_when_disabled(): void
    {
        config(['features.redlining' => false]);

        $this->actingAs($this->legalUser);

        // The "Start Redline Review" action should not be visible
        // We test this by checking the Feature helper directly
        $this->assertFalse(Feature::enabled('redlining'));
    }

    public function test_feature_gate_returns_404_on_redline_session_page_when_disabled(): void
    {
        config(['features.redlining' => false]);

        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->legalUser);

        $response = $this->get(
            "/admin/contracts/{$this->contract->id}/redline/{$session->id}"
        );

        $response->assertStatus(404);
    }
}
```

### 7.1 Create Model Factories

Create factories for the new models if they do not already exist:

**`database/factories/RedlineSessionFactory.php`:**

```php
<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\RedlineSession;
use App\Models\WikiContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedlineSessionFactory extends Factory
{
    protected $model = RedlineSession::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'wiki_contract_id' => WikiContract::factory(),
            'status' => 'pending',
            'created_by' => null,
            'total_clauses' => 0,
            'reviewed_clauses' => 0,
            'summary' => null,
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'total_clauses' => 10,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'Test error message',
        ]);
    }
}
```

**`database/factories/RedlineClauseFactory.php`:**

```php
<?php

namespace Database\Factories;

use App\Models\RedlineClause;
use App\Models\RedlineSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedlineClauseFactory extends Factory
{
    protected $model = RedlineClause::class;

    public function definition(): array
    {
        return [
            'session_id' => RedlineSession::factory(),
            'clause_number' => $this->faker->numberBetween(1, 30),
            'clause_heading' => $this->faker->sentence(3),
            'original_text' => $this->faker->paragraphs(2, true),
            'suggested_text' => $this->faker->paragraphs(2, true),
            'change_type' => $this->faker->randomElement(['unchanged', 'addition', 'deletion', 'modification']),
            'ai_rationale' => $this->faker->sentence(),
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
            'status' => 'pending',
            'final_text' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'accepted',
            'final_text' => $attrs['suggested_text'] ?? $this->faker->paragraph(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'rejected',
            'final_text' => $attrs['original_text'] ?? $this->faker->paragraph(),
            'reviewed_at' => now(),
        ]);
    }
}
```

---

## Verification Checklist

1. **Migration runs:** `php artisan migrate` executes without error. Tables `redline_sessions` and `redline_clauses` are created with all columns, foreign keys, and indexes.

2. **Models work:** In tinker, `RedlineSession::factory()->create()` succeeds. `$session->clauses`, `$session->contract`, `$session->wikiContract` relationships all resolve correctly.

3. **Feature gate disabled:** With `FEATURE_REDLINING=false`:
   - The "Start Redline Review" action does not appear on the contract table/view.
   - The RedlineSessionsRelationManager is hidden.
   - Visiting `/admin/contracts/{id}/redline/{session_id}` returns 404.

4. **Feature gate enabled:** Set `FEATURE_REDLINING=true` in `.env` and run `php artisan config:clear`:
   - Upload a contract with a document file.
   - Create a WikiContract template in the same region with `status = 'published'`.
   - The "Start Redline Review" action appears on the contract.

5. **Start session:** Click "Start Redline Review" on a contract. Select a template (or leave blank for auto-select). Session record created in `redline_sessions` with `status = 'pending'`. `ProcessRedlineAnalysis` job dispatched to the queue.

6. **AI Worker processes:** With the AI Worker running on port 8001 and `ANTHROPIC_API_KEY` set:
   - The job calls `/analyze-redline` on the AI Worker.
   - AI Worker uses Claude claude-sonnet-4-6 to compare clauses.
   - Session status changes to `completed`.
   - `redline_clauses` rows are inserted with clause data.
   - Session `summary` JSON is populated.

7. **Redline Session Page:** Navigate to the session page. Verify:
   - Header shows contract title, template name, status, and progress bar.
   - Side-by-side diff view for each clause with changes highlighted.
   - Unchanged clauses show in a single column.
   - AI rationale displayed below each clause pair.
   - Confidence badge shows percentage.

8. **Accept clause:** Click "Accept" on a modified clause. Clause status updates to `accepted`, `final_text` set to the suggested text. Progress bar updates.

9. **Reject clause:** Click "Reject" on a modified clause. Clause status updates to `rejected`, `final_text` set to the original text. Progress bar updates.

10. **Modify clause:** Click "Modify" on a modified clause. Inline editor appears pre-populated with suggested text. Edit the text and click "Save Modification". Clause status updates to `modified`, `final_text` set to the custom text.

11. **Generate Final Document:** After all clauses are reviewed, click "Generate Final Document". A DOCX file is generated and uploaded to S3 at `contracts/{id}/redline-final-{session_id}.docx`. Success notification appears. (Button is disabled when unreviewed clauses remain.)

12. **RedlineSessionsRelationManager:** On the contract view page, the "Redline Sessions" tab shows all past sessions with status, template, progress, and an "Open Review" link for completed sessions.

13. **All tests pass:** `php artisan test --filter=RedlineTest` — all assertions pass.
