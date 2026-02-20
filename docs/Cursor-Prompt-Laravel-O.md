# Cursor Prompt — Laravel Migration Phase O: Phase 2B — Regulatory Compliance Checking + Advanced Analytics

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through N were executed.

This is Phase 2 work. Regulatory compliance checking was scoped out of Phase 1 and exists only as a documented stub (`app/Services/RegulatoryComplianceService.php` from Prompt K, Task 4.2). The requirements state: "Automated legal opinions or regulatory determinations" was out of scope for Phase 1 — but the system now provides compliance **checking** (flagging potential issues for legal review), not automated legal opinions.

Advanced analytics extends the basic reporting from Prompts B and I with more sophisticated dashboarding, scheduled reports, and expanded exports.

Both features are gated behind feature flags defined in `config/features.php` (created in Prompt K, Task 4.3). They default to `false` and must be explicitly enabled via `.env`.

---

## PART A: Regulatory Compliance Checking

---

### Task 1: Database Migration — Compliance Tables

Create `database/migrations/XXXX_create_compliance_tables.php` (replace `XXXX` with a standard Laravel timestamp):

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
        Schema::create('regulatory_frameworks', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->string('jurisdiction_code', 10); // ISO 3166-1 alpha-2
            $table->string('framework_name');
            $table->text('description')->nullable();
            $table->json('requirements'); // Array of requirement objects: {id, text, category, severity}
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->index('jurisdiction_code');
            $table->index('is_active');
        });

        Schema::create('compliance_findings', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('contract_id', 36);
            $table->char('framework_id', 36);
            $table->string('requirement_id', 100); // References a specific requirement within the framework
            $table->text('requirement_text');
            $table->enum('status', ['compliant', 'non_compliant', 'unclear', 'not_applicable'])->default('unclear');
            $table->text('evidence_clause')->nullable();
            $table->integer('evidence_page')->nullable();
            $table->text('ai_rationale')->nullable();
            $table->double('confidence')->nullable();
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('framework_id')->references('id')->on('regulatory_frameworks')->restrictOnDelete();
            $table->index('contract_id');
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_findings');
        Schema::dropIfExists('regulatory_frameworks');
    }
};
```

---

### Task 2: Eloquent Models

**Create `app/Models/RegulatoryFramework.php`:**

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegulatoryFramework extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'jurisdiction_code',
        'framework_name',
        'description',
        'requirements',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_active' => 'boolean',
    ];

    public function findings(): HasMany
    {
        return $this->hasMany(ComplianceFinding::class, 'framework_id');
    }

    /**
     * Get the count of requirements defined in the framework JSON.
     */
    public function getRequirementCountAttribute(): int
    {
        return is_array($this->requirements) ? count($this->requirements) : 0;
    }
}
```

**Create `app/Models/ComplianceFinding.php`:**

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceFinding extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'id',
        'contract_id',
        'framework_id',
        'requirement_id',
        'requirement_text',
        'status',
        'evidence_clause',
        'evidence_page',
        'ai_rationale',
        'confidence',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => 'double',
        'evidence_page' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(RegulatoryFramework::class, 'framework_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

Add the inverse relationship in `app/Models/Contract.php`:

```php
public function complianceFindings(): HasMany
{
    return $this->hasMany(ComplianceFinding::class);
}
```

---

### Task 3: Filament Admin — RegulatoryFrameworkResource

Create `app/Filament/Resources/RegulatoryFrameworkResource.php`:

- Only accessible to `system_admin` and `legal` roles (use Shield: `public static function canViewAny(): bool` check)
- Navigation icon: `heroicon-o-shield-exclamation`, group: `"Compliance"`, sort: `36`
- **Visibility gate**: The entire resource should be hidden when `config('features.regulatory_compliance')` is `false`. Override `shouldRegisterNavigation()`:

```php
public static function shouldRegisterNavigation(): bool
{
    return config('features.regulatory_compliance', false);
}
```

And gate `canViewAny()`:

```php
public static function canViewAny(): bool
{
    if (! config('features.regulatory_compliance', false)) {
        return false;
    }

    $user = auth()->user();
    return $user && $user->hasAnyRole(['system_admin', 'legal']);
}
```

**Form:**

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Section::make('Framework Details')
            ->schema([
                Forms\Components\Select::make('jurisdiction_code')
                    ->label('Jurisdiction')
                    ->options([
                        'EU' => 'European Union',
                        'US' => 'United States',
                        'GB' => 'United Kingdom',
                        'AE' => 'United Arab Emirates',
                        'SA' => 'Saudi Arabia',
                        'SG' => 'Singapore',
                        'HK' => 'Hong Kong',
                        'JP' => 'Japan',
                        'AU' => 'Australia',
                        'CA' => 'Canada',
                        'IN' => 'India',
                        'GLOBAL' => 'Global / Multi-jurisdictional',
                    ])
                    ->required()
                    ->searchable()
                    ->helperText('ISO 3166-1 alpha-2 code or GLOBAL for multi-jurisdictional frameworks.'),

                Forms\Components\TextInput::make('framework_name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. GDPR — Data Processing Requirements'),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->placeholder('Brief description of what this framework covers...'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive frameworks will not appear in compliance check options.'),
            ])
            ->columns(2),

        Forms\Components\Section::make('Requirements')
            ->description('Define individual requirements that contracts will be checked against.')
            ->schema([
                Forms\Components\Repeater::make('requirements')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('Requirement ID')
                            ->required()
                            ->placeholder('e.g. gdpr-1')
                            ->helperText('Unique identifier within this framework.')
                            ->maxLength(100),

                        Forms\Components\Textarea::make('text')
                            ->label('Requirement Text')
                            ->required()
                            ->rows(2)
                            ->placeholder('Describe the specific requirement the contract must address...'),

                        Forms\Components\Select::make('category')
                            ->options([
                                'data_protection' => 'Data Protection',
                                'financial' => 'Financial',
                                'employment' => 'Employment',
                                'intellectual_property' => 'Intellectual Property',
                                'dispute_resolution' => 'Dispute Resolution',
                                'liability' => 'Liability & Indemnification',
                                'confidentiality' => 'Confidentiality',
                                'termination' => 'Termination',
                                'other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\Select::make('severity')
                            ->options([
                                'critical' => 'Critical',
                                'high' => 'High',
                                'medium' => 'Medium',
                                'low' => 'Low',
                            ])
                            ->required()
                            ->default('medium'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => ($state['id'] ?? '') . ' — ' . ($state['text'] ?? ''))
                    ->defaultItems(0)
                    ->addActionLabel('Add Requirement'),
            ]),
    ]);
}
```

**Table:**

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('jurisdiction_code')
                ->label('Jurisdiction')
                ->badge()
                ->sortable()
                ->searchable(),

            Tables\Columns\TextColumn::make('framework_name')
                ->label('Framework')
                ->searchable()
                ->sortable()
                ->limit(50),

            Tables\Columns\TextColumn::make('requirement_count')
                ->label('Requirements')
                ->getStateUsing(fn (RegulatoryFramework $record): int => $record->requirement_count)
                ->sortable(false),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->sortable(),

            Tables\Columns\TextColumn::make('findings_count')
                ->label('Findings')
                ->counts('findings')
                ->sortable(),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Last Updated')
                ->dateTime()
                ->sortable(),
        ])
        ->defaultSort('framework_name')
        ->filters([
            Tables\Filters\SelectFilter::make('jurisdiction_code')
                ->label('Jurisdiction')
                ->options(fn () => RegulatoryFramework::query()
                    ->distinct()
                    ->pluck('jurisdiction_code', 'jurisdiction_code')
                    ->toArray()
                ),
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
}
```

Create the standard Resource pages: `ListRegulatoryFrameworks`, `CreateRegulatoryFramework`, `EditRegulatoryFramework`.

---

### Task 4: Database Seeder — Seed 3 Regulatory Frameworks

Create `database/seeders/RegulatoryFrameworkSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\RegulatoryFramework;
use Illuminate\Database\Seeder;

class RegulatoryFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $frameworks = [
            [
                'jurisdiction_code' => 'EU',
                'framework_name' => 'GDPR — Data Processing in Contracts',
                'description' => 'General Data Protection Regulation requirements for contracts that involve processing of personal data within the European Union.',
                'is_active' => true,
                'requirements' => [
                    [
                        'id' => 'gdpr-1',
                        'text' => 'Contract must include a data processing agreement (DPA) or data processing clauses if personal data is processed on behalf of a controller.',
                        'category' => 'data_protection',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'gdpr-2',
                        'text' => 'Contract must specify the subject matter, duration, nature, and purpose of data processing.',
                        'category' => 'data_protection',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'gdpr-3',
                        'text' => 'Contract must define the types of personal data processed and categories of data subjects.',
                        'category' => 'data_protection',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'gdpr-4',
                        'text' => 'Contract must require the processor to implement appropriate technical and organisational security measures (Article 32).',
                        'category' => 'data_protection',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'gdpr-5',
                        'text' => 'Contract must include provisions for data breach notification without undue delay (Article 33).',
                        'category' => 'data_protection',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'gdpr-6',
                        'text' => 'Contract must address sub-processor engagement — either general or specific written authorisation required.',
                        'category' => 'data_protection',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'gdpr-7',
                        'text' => 'Contract must include provisions for data subject rights assistance (access, rectification, erasure, portability).',
                        'category' => 'data_protection',
                        'severity' => 'medium',
                    ],
                    [
                        'id' => 'gdpr-8',
                        'text' => 'Contract must address international data transfers and applicable safeguards (SCCs, adequacy decisions, or BCRs).',
                        'category' => 'data_protection',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'gdpr-9',
                        'text' => 'Contract must require deletion or return of personal data upon termination of the processing relationship.',
                        'category' => 'data_protection',
                        'severity' => 'medium',
                    ],
                    [
                        'id' => 'gdpr-10',
                        'text' => 'Contract must provide for audit rights — the controller must be able to audit processor compliance.',
                        'category' => 'data_protection',
                        'severity' => 'medium',
                    ],
                ],
            ],
            [
                'jurisdiction_code' => 'GLOBAL',
                'framework_name' => 'PCI DSS v4.0 — Merchant Agreement Requirements',
                'description' => 'Payment Card Industry Data Security Standard requirements for merchant agreements involving payment card data handling.',
                'is_active' => true,
                'requirements' => [
                    [
                        'id' => 'pci-1',
                        'text' => 'Agreement must require the service provider to maintain PCI DSS compliance for the duration of the engagement.',
                        'category' => 'data_protection',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'pci-2',
                        'text' => 'Agreement must define which PCI DSS requirements are the responsibility of the service provider vs. the merchant.',
                        'category' => 'financial',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'pci-3',
                        'text' => 'Agreement must require the service provider to acknowledge responsibility for the security of cardholder data it possesses, stores, processes, or transmits.',
                        'category' => 'data_protection',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'pci-4',
                        'text' => 'Agreement must include provisions for incident response and breach notification specific to cardholder data compromise.',
                        'category' => 'data_protection',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'pci-5',
                        'text' => 'Agreement must require periodic evidence of PCI DSS compliance (AOC, SAQ, or ROC) from the service provider.',
                        'category' => 'financial',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'pci-6',
                        'text' => 'Agreement must address data retention and destruction requirements for cardholder data.',
                        'category' => 'data_protection',
                        'severity' => 'medium',
                    ],
                    [
                        'id' => 'pci-7',
                        'text' => 'Agreement must include right-to-audit clauses for PCI DSS compliance verification.',
                        'category' => 'financial',
                        'severity' => 'medium',
                    ],
                ],
            ],
            [
                'jurisdiction_code' => 'AE',
                'framework_name' => 'UAE Federal Law No. 5/2012 — Electronic Transactions',
                'description' => 'UAE Federal Law on Combating Cybercrimes and Electronic Transactions requirements relevant to merchant and commercial agreements operating in the UAE/MENA region.',
                'is_active' => true,
                'requirements' => [
                    [
                        'id' => 'uae-1',
                        'text' => 'Electronic contracts must include clear identification of the contracting parties, including legal names and registered addresses.',
                        'category' => 'other',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'uae-2',
                        'text' => 'Electronic signatures used must comply with UAE recognition standards for electronic authentication.',
                        'category' => 'other',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'uae-3',
                        'text' => 'Contract must specify the governing law and jurisdiction — UAE law requires explicit choice of law in commercial agreements.',
                        'category' => 'dispute_resolution',
                        'severity' => 'critical',
                    ],
                    [
                        'id' => 'uae-4',
                        'text' => 'Contract must include a dispute resolution clause specifying arbitration (DIAC/ADCCAC) or court jurisdiction.',
                        'category' => 'dispute_resolution',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'uae-5',
                        'text' => 'If the contract involves personal data, it must comply with UAE Personal Data Protection Law (Federal Decree-Law No. 45/2021) data handling requirements.',
                        'category' => 'data_protection',
                        'severity' => 'high',
                    ],
                    [
                        'id' => 'uae-6',
                        'text' => 'Contract records must be retained in a form that allows verification of their integrity and is accessible for inspection by regulatory authorities.',
                        'category' => 'other',
                        'severity' => 'medium',
                    ],
                    [
                        'id' => 'uae-7',
                        'text' => 'Contract must include provisions for force majeure that align with UAE Civil Code interpretations.',
                        'category' => 'liability',
                        'severity' => 'medium',
                    ],
                ],
            ],
        ];

        foreach ($frameworks as $data) {
            RegulatoryFramework::firstOrCreate(
                [
                    'jurisdiction_code' => $data['jurisdiction_code'],
                    'framework_name' => $data['framework_name'],
                ],
                $data
            );
        }
    }
}
```

Call this seeder from `DatabaseSeeder.php`:

```php
$this->call(RegulatoryFrameworkSeeder::class);
```

---

### Task 5: AI Worker — Compliance Check Endpoint

Add `POST /check-compliance` to the AI worker (`ai-worker/`).

**In `ai-worker/app/main.py` (or the appropriate router file), add the endpoint:**

```python
from pydantic import BaseModel, Field
from typing import Optional
import json

class ComplianceRequirement(BaseModel):
    id: str
    text: str
    category: str
    severity: str

class ComplianceFramework(BaseModel):
    id: str
    name: str
    jurisdiction_code: str
    requirements: list[ComplianceRequirement]

class ComplianceCheckRequest(BaseModel):
    contract_text: str
    contract_id: str
    framework: ComplianceFramework

class ComplianceFindingResult(BaseModel):
    requirement_id: str
    status: str = Field(description="One of: compliant, non_compliant, unclear, not_applicable")
    evidence_clause: Optional[str] = None
    evidence_page: Optional[int] = None
    rationale: str
    confidence: float = Field(ge=0.0, le=1.0)

class ComplianceCheckResponse(BaseModel):
    contract_id: str
    framework_id: str
    findings: list[ComplianceFindingResult]


@app.post("/check-compliance", response_model=ComplianceCheckResponse)
async def check_compliance(request: ComplianceCheckRequest):
    """
    Evaluate a contract against a regulatory framework's requirements.

    This endpoint checks whether specific clauses or provisions in the contract
    appear to address each requirement. It does NOT provide legal advice or
    automated legal opinions.
    """
    import anthropic

    client = anthropic.Anthropic(api_key=settings.anthropic_api_key)

    # Build the requirements list for the prompt
    requirements_text = "\n".join([
        f"- [{req.id}] (Category: {req.category}, Severity: {req.severity}): {req.text}"
        for req in request.framework.requirements
    ])

    system_prompt = (
        "You are a regulatory compliance checking assistant for contract review. "
        "IMPORTANT: You are NOT providing legal advice or legal opinions. "
        "You are checking whether specific clauses or provisions in the contract text "
        "appear to address each regulatory requirement. Flag issues for human legal review. "
        "You must NOT make definitive legal determinations. "
        "For each requirement, provide:\n"
        "1. status: 'compliant' if the contract clearly addresses the requirement, "
        "'non_compliant' if the requirement is clearly not addressed, "
        "'unclear' if the contract partially addresses it or the language is ambiguous, "
        "'not_applicable' if the requirement does not apply to this type of contract.\n"
        "2. evidence_clause: A direct quote from the contract that relates to this requirement (if any).\n"
        "3. evidence_page: The approximate page number where the evidence was found (if determinable).\n"
        "4. rationale: A brief explanation of your assessment.\n"
        "5. confidence: A float between 0.0 and 1.0 indicating your confidence in this assessment.\n\n"
        "Respond with a JSON array of findings. Each finding must have the fields: "
        "requirement_id, status, evidence_clause, evidence_page, rationale, confidence."
    )

    user_prompt = (
        f"## Regulatory Framework: {request.framework.name}\n"
        f"## Jurisdiction: {request.framework.jurisdiction_code}\n\n"
        f"## Requirements to check:\n{requirements_text}\n\n"
        f"## Contract Text:\n{request.contract_text}\n\n"
        "Evaluate the contract against each requirement and return a JSON array of findings."
    )

    response = client.messages.create(
        model="claude-sonnet-4-6",
        max_tokens=4096,
        system=system_prompt,
        messages=[{"role": "user", "content": user_prompt}],
    )

    # Parse the AI response
    response_text = response.content[0].text

    # Extract JSON from the response (handle markdown code blocks)
    if "```json" in response_text:
        response_text = response_text.split("```json")[1].split("```")[0].strip()
    elif "```" in response_text:
        response_text = response_text.split("```")[1].split("```")[0].strip()

    findings_raw = json.loads(response_text)

    findings = []
    for finding in findings_raw:
        findings.append(ComplianceFindingResult(
            requirement_id=finding["requirement_id"],
            status=finding.get("status", "unclear"),
            evidence_clause=finding.get("evidence_clause"),
            evidence_page=finding.get("evidence_page"),
            rationale=finding.get("rationale", ""),
            confidence=float(finding.get("confidence", 0.5)),
        ))

    # Track token usage (consistent with existing AI worker pattern)
    usage = {
        "input_tokens": response.usage.input_tokens,
        "output_tokens": response.usage.output_tokens,
        "model": "claude-sonnet-4-6",
        "analysis_type": "compliance_check",
    }
    # Log or store usage as per existing ai-worker conventions
    logger.info(f"Compliance check completed for contract {request.contract_id}: {len(findings)} findings", extra=usage)

    return ComplianceCheckResponse(
        contract_id=request.contract_id,
        framework_id=request.framework.id,
        findings=findings,
    )
```

---

### Task 6: Laravel Job — ProcessComplianceCheck

Create `app/Jobs/ProcessComplianceCheck.php`:

```php
<?php

namespace App\Jobs;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessComplianceCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300; // 5 minutes — AI analysis can be slow

    public function __construct(
        public Contract $contract,
        public RegulatoryFramework $framework,
    ) {}

    public function handle(): void
    {
        $contractText = $this->contract->extracted_text;
        if (empty($contractText)) {
            Log::warning("Compliance check skipped: no extracted text for contract {$this->contract->id}");
            return;
        }

        // Build the request payload
        $payload = [
            'contract_text' => $contractText,
            'contract_id' => $this->contract->id,
            'framework' => [
                'id' => $this->framework->id,
                'name' => $this->framework->framework_name,
                'jurisdiction_code' => $this->framework->jurisdiction_code,
                'requirements' => $this->framework->requirements,
            ],
        ];

        // Call the AI worker
        $aiWorkerUrl = config('services.ai_worker.url', 'http://ai-worker:8001');
        $aiWorkerSecret = config('services.ai_worker.secret');

        $response = Http::timeout(280)
            ->withHeaders([
                'Authorization' => "Bearer {$aiWorkerSecret}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$aiWorkerUrl}/check-compliance", $payload);

        if (! $response->successful()) {
            Log::error("AI worker compliance check failed for contract {$this->contract->id}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("AI worker returned HTTP {$response->status()}");
        }

        $data = $response->json();

        // Delete existing findings for this contract + framework (re-check replaces previous results)
        ComplianceFinding::where('contract_id', $this->contract->id)
            ->where('framework_id', $this->framework->id)
            ->delete();

        // Store findings
        foreach ($data['findings'] ?? [] as $finding) {
            ComplianceFinding::create([
                'id' => Str::uuid()->toString(),
                'contract_id' => $this->contract->id,
                'framework_id' => $this->framework->id,
                'requirement_id' => $finding['requirement_id'],
                'requirement_text' => $this->getRequirementText($finding['requirement_id']),
                'status' => $finding['status'] ?? 'unclear',
                'evidence_clause' => $finding['evidence_clause'] ?? null,
                'evidence_page' => $finding['evidence_page'] ?? null,
                'ai_rationale' => $finding['rationale'] ?? null,
                'confidence' => $finding['confidence'] ?? null,
            ]);
        }

        Log::info("Compliance check completed for contract {$this->contract->id} against framework {$this->framework->framework_name}", [
            'findings_count' => count($data['findings'] ?? []),
        ]);
    }

    /**
     * Look up the requirement text from the framework's requirements JSON.
     */
    private function getRequirementText(string $requirementId): string
    {
        $requirements = $this->framework->requirements ?? [];
        foreach ($requirements as $req) {
            if (($req['id'] ?? '') === $requirementId) {
                return $req['text'] ?? '';
            }
        }
        return '';
    }
}
```

---

### Task 7: Implement RegulatoryComplianceService (Replace Stub)

Replace the stub in `app/Services/RegulatoryComplianceService.php` with the full implementation:

```php
<?php

namespace App\Services;

use App\Jobs\ProcessComplianceCheck;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * RegulatoryComplianceService — Phase 2: Regulatory compliance checking.
 *
 * This service orchestrates compliance checks by dispatching AI analysis jobs
 * against regulatory frameworks. It does NOT provide legal advice — it flags
 * potential compliance issues for human legal review.
 */
class RegulatoryComplianceService
{
    /**
     * Run a compliance check for a contract against a specific framework.
     * If no framework is specified, auto-detect based on the contract's entity/region jurisdiction.
     */
    public function runComplianceCheck(Contract $contract, ?RegulatoryFramework $framework = null): void
    {
        if (! config('features.regulatory_compliance', false)) {
            throw new \RuntimeException('Regulatory compliance checking is not enabled. Set FEATURE_REGULATORY_COMPLIANCE=true in .env.');
        }

        if ($framework) {
            ProcessComplianceCheck::dispatch($contract, $framework);
            return;
        }

        // Auto-detect frameworks based on the contract's region/entity jurisdiction
        $frameworks = $this->detectApplicableFrameworks($contract);

        if ($frameworks->isEmpty()) {
            Log::info("No applicable regulatory frameworks found for contract {$contract->id}");
            return;
        }

        foreach ($frameworks as $fw) {
            ProcessComplianceCheck::dispatch($contract, $fw);
        }
    }

    /**
     * Get all compliance findings for a contract, grouped by framework.
     */
    public function getFindings(Contract $contract): Collection
    {
        return ComplianceFinding::where('contract_id', $contract->id)
            ->with('framework')
            ->orderBy('framework_id')
            ->orderBy('requirement_id')
            ->get()
            ->groupBy('framework_id');
    }

    /**
     * Get a compliance score summary for a contract per framework.
     * Returns: [framework_id => ['total' => int, 'compliant' => int, 'non_compliant' => int, 'unclear' => int, 'not_applicable' => int, 'score' => float]]
     */
    public function getScoreSummary(Contract $contract): Collection
    {
        $findings = ComplianceFinding::where('contract_id', $contract->id)
            ->get()
            ->groupBy('framework_id');

        return $findings->map(function (Collection $group) {
            $total = $group->count();
            $compliant = $group->where('status', 'compliant')->count();
            $nonCompliant = $group->where('status', 'non_compliant')->count();
            $unclear = $group->where('status', 'unclear')->count();
            $notApplicable = $group->where('status', 'not_applicable')->count();

            // Score = compliant / (total - not_applicable) * 100
            $scorable = $total - $notApplicable;
            $score = $scorable > 0 ? round(($compliant / $scorable) * 100, 1) : 0.0;

            return [
                'total' => $total,
                'compliant' => $compliant,
                'non_compliant' => $nonCompliant,
                'unclear' => $unclear,
                'not_applicable' => $notApplicable,
                'score' => $score,
            ];
        });
    }

    /**
     * Review a finding — legal user overrides the AI-determined status.
     */
    public function reviewFinding(ComplianceFinding $finding, string $status, User $actor): ComplianceFinding
    {
        $validStatuses = ['compliant', 'non_compliant', 'unclear', 'not_applicable'];
        if (! in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid compliance status: {$status}");
        }

        $finding->update([
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        Log::info("Compliance finding {$finding->id} reviewed", [
            'contract_id' => $finding->contract_id,
            'requirement_id' => $finding->requirement_id,
            'old_status' => $finding->getOriginal('status'),
            'new_status' => $status,
            'reviewer' => $actor->id,
        ]);

        return $finding->fresh();
    }

    /**
     * Auto-detect applicable frameworks based on contract's entity → region → jurisdiction mapping.
     */
    private function detectApplicableFrameworks(Contract $contract): Collection
    {
        $contract->loadMissing('entity.region');

        $jurisdictionCode = null;

        // Try to determine jurisdiction from entity's region
        if ($contract->entity && $contract->entity->region) {
            // Assume region has a `code` or `jurisdiction_code` field
            $jurisdictionCode = $contract->entity->region->code
                ?? $contract->entity->region->jurisdiction_code
                ?? null;
        }

        $query = RegulatoryFramework::where('is_active', true);

        if ($jurisdictionCode) {
            // Match specific jurisdiction + GLOBAL frameworks
            $query->where(function ($q) use ($jurisdictionCode) {
                $q->where('jurisdiction_code', $jurisdictionCode)
                  ->orWhere('jurisdiction_code', 'GLOBAL');
            });
        } else {
            // If jurisdiction unknown, only apply GLOBAL frameworks
            $query->where('jurisdiction_code', 'GLOBAL');
        }

        return $query->get();
    }
}
```

---

### Task 8: Filament UI — Compliance Tab on ContractResource

#### 8.1 Add ComplianceFindingsRelationManager

Create `app/Filament/Resources/ContractResource/RelationManagers/ComplianceFindingsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Services\RegulatoryComplianceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ComplianceFindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'complianceFindings';

    protected static ?string $title = 'Compliance Findings';

    protected static ?string $icon = 'heroicon-o-shield-check';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return config('features.regulatory_compliance', false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('framework.framework_name')
                    ->label('Framework')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('requirement_id')
                    ->label('Req. ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requirement_text')
                    ->label('Requirement')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->requirement_text)
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'compliant' => 'success',
                        'non_compliant' => 'danger',
                        'unclear' => 'warning',
                        'not_applicable' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('evidence_clause')
                    ->label('Evidence')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->evidence_clause)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ai_rationale')
                    ->label('AI Rationale')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->ai_rationale)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->formatStateUsing(fn (?float $state): string => $state !== null ? round($state * 100) . '%' : '—')
                    ->badge()
                    ->color(fn (?float $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 0.8 => 'success',
                        $state >= 0.5 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('Not reviewed')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requirement_id')
            ->groups([
                Tables\Grouping\Group::make('framework.framework_name')
                    ->label('Framework')
                    ->collapsible(),
            ])
            ->defaultGroup('framework.framework_name')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'compliant' => 'Compliant',
                        'non_compliant' => 'Non-Compliant',
                        'unclear' => 'Unclear',
                        'not_applicable' => 'Not Applicable',
                    ]),
                Tables\Filters\SelectFilter::make('framework_id')
                    ->label('Framework')
                    ->relationship('framework', 'framework_name'),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Review')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['system_admin', 'legal']))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Override Status')
                            ->options([
                                'compliant' => 'Compliant',
                                'non_compliant' => 'Non-Compliant',
                                'unclear' => 'Unclear',
                                'not_applicable' => 'Not Applicable',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $service = app(RegulatoryComplianceService::class);
                        $service->reviewFinding($record, $data['status'], auth()->user());

                        Notification::make()
                            ->title('Finding reviewed')
                            ->body("Status updated to: {$data['status']}")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runComplianceCheck')
                    ->label('Run Compliance Check')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['system_admin', 'legal']))
                    ->form([
                        Forms\Components\Select::make('framework_id')
                            ->label('Regulatory Framework')
                            ->options(
                                \App\Models\RegulatoryFramework::where('is_active', true)
                                    ->pluck('framework_name', 'id')
                            )
                            ->placeholder('Auto-detect based on jurisdiction')
                            ->helperText('Leave empty to automatically select frameworks based on the contract\'s region/jurisdiction.')
                            ->searchable(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Run Compliance Check')
                    ->modalDescription('This will send the contract text to the AI worker for compliance analysis. Results will appear here once processing is complete.')
                    ->action(function (array $data): void {
                        $contract = $this->getOwnerRecord();
                        $framework = null;

                        if (! empty($data['framework_id'])) {
                            $framework = \App\Models\RegulatoryFramework::find($data['framework_id']);
                        }

                        $service = app(RegulatoryComplianceService::class);
                        $service->runComplianceCheck($contract, $framework);

                        Notification::make()
                            ->title('Compliance check dispatched')
                            ->body('The AI worker is analysing this contract. Findings will appear shortly.')
                            ->info()
                            ->send();
                    }),
            ]);
    }
}
```

#### 8.2 Register the RelationManager on ContractResource

In `app/Filament/Resources/ContractResource.php`, add the relation manager to `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        // ... existing relation managers ...
        RelationManagers\ComplianceFindingsRelationManager::class,
    ];
}
```

#### 8.3 Add Compliance Score InfoList on Contract View Page

On the contract view/edit page, add a Section showing compliance scores per framework. In the Infolist (or Form view page), add:

```php
// In ContractResource::infolist() or the view page, add a compliance summary section:
Components\Section::make('Compliance Overview')
    ->visible(fn () => config('features.regulatory_compliance', false))
    ->schema(function ($record) {
        $service = app(\App\Services\RegulatoryComplianceService::class);
        $scores = $service->getScoreSummary($record);

        if ($scores->isEmpty()) {
            return [
                Components\TextEntry::make('no_findings')
                    ->label('')
                    ->default('No compliance checks have been run for this contract.')
                    ->columnSpanFull(),
            ];
        }

        $entries = [];
        foreach ($scores as $frameworkId => $score) {
            $framework = \App\Models\RegulatoryFramework::find($frameworkId);
            $entries[] = Components\TextEntry::make("compliance_score_{$frameworkId}")
                ->label($framework?->framework_name ?? 'Unknown Framework')
                ->default("{$score['score']}% compliant ({$score['compliant']}/{$score['total']} — {$score['non_compliant']} non-compliant, {$score['unclear']} unclear)")
                ->badge()
                ->color(match (true) {
                    $score['score'] >= 80 => 'success',
                    $score['score'] >= 50 => 'warning',
                    default => 'danger',
                });
        }

        return $entries;
    })
    ->collapsible(),
```

---

### Task 9: Feature Gate for Regulatory Compliance

Ensure all compliance-related code is gated by `config('features.regulatory_compliance')`:

1. **RegulatoryFrameworkResource**: `shouldRegisterNavigation()` and `canViewAny()` — already done in Task 3.
2. **ComplianceFindingsRelationManager**: `canViewForRecord()` — already done in Task 8.1.
3. **Compliance Overview section on ContractResource**: `->visible()` — already done in Task 8.3.
4. **RegulatoryComplianceService::runComplianceCheck()**: throws if flag is off — already done in Task 7.
5. **API routes** (if any are added for compliance): wrap in middleware or route group:

```php
Route::middleware('feature:regulatory_compliance')->group(function () {
    // compliance routes
});
```

Create `app/Http/Middleware/FeatureGate.php` if it does not already exist:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureGate
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! config("features.{$feature}", false)) {
            abort(404);
        }

        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'feature' => \App\Http\Middleware\FeatureGate::class,
    ]);
})
```

---

## PART B: Advanced Analytics

---

### Task 10: Executive Analytics Dashboard Page

Create `app/Filament/Pages/AnalyticsDashboardPage.php`:

```php
<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AnalyticsDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationLabel = 'Analytics Dashboard';
    protected static ?string $title = 'Executive Analytics Dashboard';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 32;
    protected static string $view = 'filament.pages.analytics-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return config('features.advanced_analytics', false);
    }

    public static function canAccess(): bool
    {
        if (! config('features.advanced_analytics', false)) {
            return false;
        }

        $user = auth()->user();
        return $user && $user->hasAnyRole(['system_admin', 'legal', 'finance', 'audit']);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\ContractPipelineFunnelWidget::class,
            Widgets\RiskDistributionWidget::class,
            Widgets\ComplianceOverviewWidget::class,
            Widgets\ObligationTrackerWidget::class,
            Widgets\AiUsageCostWidget::class,
            Widgets\WorkflowPerformanceWidget::class,
        ];
    }

    protected function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
```

**Create the Blade view** at `resources/views/filament/pages/analytics-dashboard.blade.php`:

```blade
<x-filament-panels::page>
    @if (! config('features.advanced_analytics', false))
        <div class="text-center text-gray-500 py-12">
            Advanced Analytics is not enabled. Set <code>FEATURE_ADVANCED_ANALYTICS=true</code> in your environment.
        </div>
    @else
        <x-filament-widgets::widgets
            :widgets="$this->getVisibleWidgets()"
            :columns="$this->getColumns()"
        />
    @endif
</x-filament-panels::page>
```

---

### Task 11: Dashboard Widgets

Create the following widget classes under `app/Filament/Widgets/` (or `app/Filament/Pages/Widgets/` if scoped to the page):

#### 11.1 ContractPipelineFunnelWidget

Create `app/Filament/Widgets/ContractPipelineFunnelWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Widgets\ChartWidget;

class ContractPipelineFunnelWidget extends ChartWidget
{
    protected static ?string $heading = 'Contract Pipeline';
    protected static ?string $description = 'Contract counts by workflow stage';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $stages = ['draft', 'in_review', 'pending_approval', 'signing', 'executed', 'archived'];

        $counts = [];
        foreach ($stages as $stage) {
            $counts[] = Contract::where('workflow_state', $stage)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Contracts',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#94a3b8', // draft — slate
                        '#60a5fa', // in_review — blue
                        '#fbbf24', // pending_approval — amber
                        '#a78bfa', // signing — violet
                        '#34d399', // executed — green
                        '#9ca3af', // archived — gray
                    ],
                ],
            ],
            'labels' => array_map(fn ($s) => ucwords(str_replace('_', ' ', $s)), $stages),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    /**
     * Accessible description for screen readers.
     */
    protected function getExtraBodyAttributes(): array
    {
        return [
            'role' => 'img',
            'aria-label' => $this->getAccessibleDescription(),
        ];
    }

    protected function getAccessibleDescription(): string
    {
        $data = $this->getData();
        $labels = $data['labels'] ?? [];
        $values = $data['datasets'][0]['data'] ?? [];
        $parts = [];
        foreach ($labels as $i => $label) {
            $parts[] = "{$label}: " . ($values[$i] ?? 0);
        }
        return $this->getHeading() . '. ' . implode(', ', $parts) . '.';
    }
}
```

#### 11.2 RiskDistributionWidget

Create `app/Filament/Widgets/RiskDistributionWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\DB;
use Filament\Widgets\ChartWidget;

class RiskDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Risk Distribution';
    protected static ?string $description = 'Contracts by AI risk score grouped by region';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Query contracts with their AI risk analysis, grouped by region
        $results = DB::table('contracts')
            ->join('regions', 'contracts.region_id', '=', 'regions.id')
            ->leftJoin('ai_analysis_results', function ($join) {
                $join->on('ai_analysis_results.contract_id', '=', 'contracts.id')
                     ->where('ai_analysis_results.analysis_type', '=', 'risk');
            })
            ->select(
                'regions.name as region_name',
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ai_analysis_results.result, '$.risk_level')), 'unscored') as risk_level"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotIn('contracts.workflow_state', ['cancelled'])
            ->groupBy('regions.name', 'risk_level')
            ->orderBy('regions.name')
            ->get();

        $regions = $results->pluck('region_name')->unique()->values();
        $riskLevels = ['high', 'medium', 'low', 'unscored'];
        $colors = [
            'high' => '#ef4444',
            'medium' => '#f59e0b',
            'low' => '#22c55e',
            'unscored' => '#9ca3af',
        ];

        $datasets = [];
        foreach ($riskLevels as $level) {
            $data = [];
            foreach ($regions as $region) {
                $match = $results->where('region_name', $region)->where('risk_level', $level)->first();
                $data[] = $match ? $match->count : 0;
            }
            $datasets[] = [
                'label' => ucfirst($level),
                'data' => $data,
                'backgroundColor' => $colors[$level],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $regions->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Horizontal bar
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'x' => ['stacked' => true, 'beginAtZero' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }
}
```

#### 11.3 ComplianceOverviewWidget

Create `app/Filament/Widgets/ComplianceOverviewWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\ComplianceFinding;
use Filament\Widgets\ChartWidget;

class ComplianceOverviewWidget extends ChartWidget
{
    protected static ?string $heading = 'Compliance Overview';
    protected static ?string $description = 'Aggregate compliance findings across all active contracts';
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return config('features.regulatory_compliance', false);
    }

    protected function getData(): array
    {
        $statuses = ['compliant', 'non_compliant', 'unclear', 'not_applicable'];
        $colors = [
            'compliant' => '#22c55e',
            'non_compliant' => '#ef4444',
            'unclear' => '#f59e0b',
            'not_applicable' => '#9ca3af',
        ];

        $counts = [];
        foreach ($statuses as $status) {
            $counts[] = ComplianceFinding::where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => array_values($colors),
                ],
            ],
            'labels' => array_map(fn ($s) => ucwords(str_replace('_', ' ', $s)), $statuses),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
        ];
    }
}
```

#### 11.4 ObligationTrackerWidget

Create `app/Filament/Widgets/ObligationTrackerWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ObligationTrackerWidget extends Widget
{
    protected static ?string $heading = 'Obligation Tracker';
    protected static ?int $sort = 4;
    protected static string $view = 'filament.widgets.obligation-tracker';
    protected int | string | array $columnSpan = 'full';

    public function getObligations(): array
    {
        return DB::table('obligations_register')
            ->join('contracts', 'obligations_register.contract_id', '=', 'contracts.id')
            ->select(
                'obligations_register.id',
                'obligations_register.obligation_type',
                'obligations_register.description',
                'obligations_register.due_date',
                'obligations_register.status',
                'contracts.title as contract_title',
                'contracts.id as contract_id'
            )
            ->where('obligations_register.status', '!=', 'completed')
            ->where('obligations_register.due_date', '>=', now()->subDays(30))
            ->where('obligations_register.due_date', '<=', now()->addDays(90))
            ->orderBy('obligations_register.due_date')
            ->limit(50)
            ->get()
            ->map(fn ($ob) => (array) $ob)
            ->toArray();
    }
}
```

**Create the Blade view** at `resources/views/filament/widgets/obligation-tracker.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Obligation Tracker" description="Upcoming and overdue obligations (next 90 days)">
        @php $obligations = $this->getObligations(); @endphp

        @if (empty($obligations))
            <p class="text-gray-500 italic text-sm">No upcoming obligations found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Upcoming obligations">
                    <thead>
                        <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                            <th class="py-2 px-2">Contract</th>
                            <th class="py-2 px-2">Type</th>
                            <th class="py-2 px-2">Description</th>
                            <th class="py-2 px-2">Due Date</th>
                            <th class="py-2 px-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($obligations as $ob)
                            @php
                                $isOverdue = \Carbon\Carbon::parse($ob['due_date'])->isPast() && $ob['status'] !== 'completed';
                            @endphp
                            <tr class="border-b dark:border-gray-700 {{ $isOverdue ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                                <td class="py-2 px-2 text-gray-900 dark:text-gray-100">
                                    {{ \Illuminate\Support\Str::limit($ob['contract_title'], 40) }}
                                </td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        {{ ucwords(str_replace('_', ' ', $ob['obligation_type'])) }}
                                    </span>
                                </td>
                                <td class="py-2 px-2 text-gray-600 dark:text-gray-400">
                                    {{ \Illuminate\Support\Str::limit($ob['description'] ?? '', 60) }}
                                </td>
                                <td class="py-2 px-2 {{ $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-900 dark:text-gray-100' }}">
                                    {{ \Carbon\Carbon::parse($ob['due_date'])->format('d M Y') }}
                                    @if ($isOverdue)
                                        <span class="text-xs text-red-500 ml-1">(OVERDUE)</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded
                                        {{ $ob['status'] === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $ob['status'] === 'in_progress' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $ob['status'] === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $ob['status'] === 'overdue' ? 'bg-red-100 text-red-700' : '' }}
                                    ">
                                        {{ ucfirst($ob['status']) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

#### 11.5 AiUsageCostWidget

Create `app/Filament/Widgets/AiUsageCostWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\DB;
use Filament\Widgets\ChartWidget;

class AiUsageCostWidget extends ChartWidget
{
    protected static ?string $heading = 'AI Usage & Cost';
    protected static ?string $description = 'Daily token usage and estimated cost over last 30 days';
    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $results = DB::table('ai_analysis_results')
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(JSON_UNQUOTE(JSON_EXTRACT(result, "$.usage.input_tokens"))) as input_tokens'),
                DB::raw('SUM(JSON_UNQUOTE(JSON_EXTRACT(result, "$.usage.output_tokens"))) as output_tokens'),
                DB::raw('COUNT(*) as analysis_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $labels = [];
        $tokenData = [];
        $costData = [];

        foreach ($results as $row) {
            $labels[] = \Carbon\Carbon::parse($row->day)->format('M d');
            $inputTokens = (int) ($row->input_tokens ?? 0);
            $outputTokens = (int) ($row->output_tokens ?? 0);
            $tokenData[] = $inputTokens + $outputTokens;

            // Estimate cost: Claude Sonnet pricing (~$3/MTok input, ~$15/MTok output)
            $cost = ($inputTokens / 1_000_000 * 3.0) + ($outputTokens / 1_000_000 * 15.0);
            $costData[] = round($cost, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Tokens',
                    'data' => $tokenData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'yAxisID' => 'y',
                    'fill' => true,
                ],
                [
                    'label' => 'Estimated Cost ($)',
                    'data' => $costData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'yAxisID' => 'y1',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => 'Tokens'],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => ['display' => true, 'text' => 'Cost ($)'],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
        ];
    }
}
```

#### 11.6 WorkflowPerformanceWidget

Create `app/Filament/Widgets/WorkflowPerformanceWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class WorkflowPerformanceWidget extends Widget
{
    protected static ?string $heading = 'Workflow Performance';
    protected static ?int $sort = 6;
    protected static string $view = 'filament.widgets.workflow-performance';
    protected int | string | array $columnSpan = 'full';

    public function getPerformanceData(): array
    {
        // Calculate average time per workflow stage from workflow_stage_actions timestamps
        $stageMetrics = DB::table('workflow_stage_actions')
            ->select(
                'stage_name',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours'),
                DB::raw('MAX(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as max_hours'),
                DB::raw('MIN(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as min_hours'),
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('SUM(CASE WHEN completed_at > sla_deadline THEN 1 ELSE 0 END) as sla_breaches')
            )
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('stage_name')
            ->orderByRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) DESC')
            ->get()
            ->map(fn ($row) => [
                'stage_name' => $row->stage_name,
                'avg_hours' => round($row->avg_hours ?? 0, 1),
                'max_hours' => round($row->max_hours ?? 0, 1),
                'min_hours' => round($row->min_hours ?? 0, 1),
                'total_actions' => (int) $row->total_actions,
                'sla_breaches' => (int) $row->sla_breaches,
                'sla_breach_rate' => $row->total_actions > 0
                    ? round(($row->sla_breaches / $row->total_actions) * 100, 1)
                    : 0.0,
            ])
            ->toArray();

        return $stageMetrics;
    }
}
```

**Create the Blade view** at `resources/views/filament/widgets/workflow-performance.blade.php`:

```blade
<x-filament-widgets::widget>
    <x-filament::section heading="Workflow Performance" description="Average stage durations and SLA breach rates (last 90 days)">
        @php $metrics = $this->getPerformanceData(); @endphp

        @if (empty($metrics))
            <p class="text-gray-500 italic text-sm">No workflow performance data available.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" aria-label="Workflow performance metrics">
                    <thead>
                        <tr class="text-left text-gray-500 border-b dark:border-gray-700">
                            <th class="py-2 px-2">Stage</th>
                            <th class="py-2 px-2 text-right">Avg Duration (hrs)</th>
                            <th class="py-2 px-2 text-right">Min (hrs)</th>
                            <th class="py-2 px-2 text-right">Max (hrs)</th>
                            <th class="py-2 px-2 text-right">Actions</th>
                            <th class="py-2 px-2 text-right">SLA Breaches</th>
                            <th class="py-2 px-2 text-right">Breach Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($metrics as $metric)
                            @php
                                $isBottleneck = $metric['avg_hours'] > 48; // Flag stages >48h avg as bottlenecks
                            @endphp
                            <tr class="border-b dark:border-gray-700 {{ $isBottleneck ? 'bg-amber-50 dark:bg-amber-900/20' : '' }}">
                                <td class="py-2 px-2 text-gray-900 dark:text-gray-100 font-medium">
                                    {{ ucwords(str_replace('_', ' ', $metric['stage_name'])) }}
                                    @if ($isBottleneck)
                                        <span class="text-xs text-amber-600 ml-1" title="Potential bottleneck">(bottleneck)</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2 text-right text-gray-900 dark:text-gray-100">{{ $metric['avg_hours'] }}</td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['min_hours'] }}</td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['max_hours'] }}</td>
                                <td class="py-2 px-2 text-right text-gray-600 dark:text-gray-400">{{ $metric['total_actions'] }}</td>
                                <td class="py-2 px-2 text-right {{ $metric['sla_breaches'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-600 dark:text-gray-400' }}">
                                    {{ $metric['sla_breaches'] }}
                                </td>
                                <td class="py-2 px-2 text-right">
                                    <span class="inline-flex px-2 py-0.5 text-xs rounded
                                        {{ $metric['sla_breach_rate'] > 20 ? 'bg-red-100 text-red-700' : '' }}
                                        {{ $metric['sla_breach_rate'] > 5 && $metric['sla_breach_rate'] <= 20 ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $metric['sla_breach_rate'] <= 5 ? 'bg-green-100 text-green-700' : '' }}
                                    ">
                                        {{ $metric['sla_breach_rate'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

---

### Task 12: API Endpoints for Analytics Data

Create `app/Http/Controllers/Api/AnalyticsController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Contract counts by workflow_state with optional filters.
     */
    public function pipeline(Request $request): JsonResponse
    {
        $query = Contract::query();

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('region_id')) {
            $query->where('region_id', $request->input('region_id'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }
        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->input('contract_type'));
        }

        $pipeline = $query->select('workflow_state', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_state')
            ->orderByRaw("FIELD(workflow_state, 'draft', 'in_review', 'pending_approval', 'signing', 'executed', 'archived', 'cancelled', 'expired')")
            ->get();

        return response()->json(['data' => $pipeline]);
    }

    /**
     * Contracts grouped by risk score and region.
     */
    public function riskDistribution(): JsonResponse
    {
        $results = DB::table('contracts')
            ->join('regions', 'contracts.region_id', '=', 'regions.id')
            ->leftJoin('ai_analysis_results', function ($join) {
                $join->on('ai_analysis_results.contract_id', '=', 'contracts.id')
                     ->where('ai_analysis_results.analysis_type', '=', 'risk');
            })
            ->select(
                'regions.name as region_name',
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ai_analysis_results.result, '$.risk_level')), 'unscored') as risk_level"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotIn('contracts.workflow_state', ['cancelled'])
            ->groupBy('regions.name', 'risk_level')
            ->orderBy('regions.name')
            ->get();

        return response()->json(['data' => $results]);
    }

    /**
     * Compliance finding aggregates.
     */
    public function complianceOverview(): JsonResponse
    {
        if (! config('features.regulatory_compliance', false)) {
            return response()->json(['error' => 'Regulatory compliance feature is not enabled'], 404);
        }

        $aggregates = ComplianceFinding::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $frameworkStats = DB::table('compliance_findings')
            ->join('regulatory_frameworks', 'compliance_findings.framework_id', '=', 'regulatory_frameworks.id')
            ->select(
                'regulatory_frameworks.framework_name',
                'regulatory_frameworks.id as framework_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN compliance_findings.status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant_count"),
                DB::raw("SUM(CASE WHEN compliance_findings.status = 'compliant' THEN 1 ELSE 0 END) as compliant_count")
            )
            ->groupBy('regulatory_frameworks.id', 'regulatory_frameworks.framework_name')
            ->orderByRaw("SUM(CASE WHEN compliance_findings.status = 'non_compliant' THEN 1 ELSE 0 END) DESC")
            ->get();

        return response()->json([
            'data' => [
                'aggregates' => $aggregates,
                'framework_stats' => $frameworkStats,
            ],
        ]);
    }

    /**
     * Upcoming obligations with due dates.
     */
    public function obligationsTimeline(Request $request): JsonResponse
    {
        $query = DB::table('obligations_register')
            ->join('contracts', 'obligations_register.contract_id', '=', 'contracts.id')
            ->select(
                'obligations_register.id',
                'obligations_register.obligation_type',
                'obligations_register.description',
                'obligations_register.due_date',
                'obligations_register.status',
                'contracts.title as contract_title'
            );

        if ($request->filled('obligation_type')) {
            $query->where('obligations_register.obligation_type', $request->input('obligation_type'));
        }
        if ($request->filled('status')) {
            $query->where('obligations_register.status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->where('obligations_register.due_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('obligations_register.due_date', '<=', $request->input('date_to'));
        }

        $obligations = $query->orderBy('obligations_register.due_date')->limit(100)->get();

        return response()->json(['data' => $obligations]);
    }

    /**
     * Daily token/cost aggregation for AI usage.
     */
    public function aiCosts(): JsonResponse
    {
        $dailyUsage = DB::table('ai_analysis_results')
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(JSON_UNQUOTE(JSON_EXTRACT(result, "$.usage.input_tokens"))) as input_tokens'),
                DB::raw('SUM(JSON_UNQUOTE(JSON_EXTRACT(result, "$.usage.output_tokens"))) as output_tokens'),
                DB::raw('COUNT(*) as analysis_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $totalInputTokens = $dailyUsage->sum('input_tokens');
        $totalOutputTokens = $dailyUsage->sum('output_tokens');
        $totalAnalyses = $dailyUsage->sum('analysis_count');
        $totalCost = ($totalInputTokens / 1_000_000 * 3.0) + ($totalOutputTokens / 1_000_000 * 15.0);

        return response()->json([
            'data' => [
                'daily' => $dailyUsage,
                'summary' => [
                    'total_input_tokens' => (int) $totalInputTokens,
                    'total_output_tokens' => (int) $totalOutputTokens,
                    'total_analyses' => (int) $totalAnalyses,
                    'total_cost_usd' => round($totalCost, 2),
                    'avg_cost_per_analysis' => $totalAnalyses > 0 ? round($totalCost / $totalAnalyses, 3) : 0,
                ],
            ],
        ]);
    }

    /**
     * Average stage durations and SLA breach rates.
     */
    public function workflowPerformance(): JsonResponse
    {
        $metrics = DB::table('workflow_stage_actions')
            ->select(
                'stage_name',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours'),
                DB::raw('MAX(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as max_hours'),
                DB::raw('MIN(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as min_hours'),
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('SUM(CASE WHEN completed_at > sla_deadline THEN 1 ELSE 0 END) as sla_breaches')
            )
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('stage_name')
            ->orderByRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) DESC')
            ->get();

        return response()->json(['data' => $metrics]);
    }
}
```

**Register the routes** in `routes/api.php`:

```php
use App\Http\Controllers\Api\AnalyticsController;

Route::middleware(['auth:sanctum', 'feature:advanced_analytics'])->prefix('analytics')->group(function () {
    Route::get('/pipeline', [AnalyticsController::class, 'pipeline']);
    Route::get('/risk-distribution', [AnalyticsController::class, 'riskDistribution']);
    Route::get('/compliance-overview', [AnalyticsController::class, 'complianceOverview']);
    Route::get('/obligations-timeline', [AnalyticsController::class, 'obligationsTimeline']);
    Route::get('/ai-costs', [AnalyticsController::class, 'aiCosts']);
    Route::get('/workflow-performance', [AnalyticsController::class, 'workflowPerformance']);
});
```

---

### Task 13: Scheduled Report Generation

Create `app/Jobs/GenerateWeeklyReport.php`:

```php
<?php

namespace App\Jobs;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateWeeklyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function handle(): void
    {
        if (! config('features.advanced_analytics', false)) {
            Log::info('Weekly report skipped: advanced_analytics feature is disabled.');
            return;
        }

        $reportData = $this->compileReportData();

        // Generate PDF
        $pdf = Pdf::loadView('reports.weekly-summary', $reportData);
        $pdfContent = $pdf->output();

        // Store in S3
        $filename = 'reports/weekly/ccrs-weekly-report-' . now()->format('Y-m-d') . '.pdf';
        Storage::disk('s3')->put($filename, $pdfContent);

        // Send to admins and legal users (respecting notification preferences)
        $recipients = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['system_admin', 'legal']);
        })->get();

        foreach ($recipients as $user) {
            // Check notification preferences (Prompt L)
            $prefs = $user->notification_preferences ?? [];
            $emailEnabled = $prefs['email'] ?? true;

            if ($emailEnabled) {
                Mail::send('emails.weekly-report', $reportData, function ($message) use ($user, $pdfContent, $filename) {
                    $message->to($user->email)
                        ->subject('CCRS Weekly Report — ' . now()->format('d M Y'))
                        ->attachData($pdfContent, basename($filename), [
                            'mime' => 'application/pdf',
                        ]);
                });
            }
        }

        Log::info("Weekly report generated and sent to {$recipients->count()} recipients.", [
            'storage_path' => $filename,
        ]);
    }

    private function compileReportData(): array
    {
        $now = now();
        $weekStart = $now->copy()->subWeek()->startOfWeek();
        $weekEnd = $now->copy()->subWeek()->endOfWeek();

        return [
            'report_date' => $now->format('d M Y'),
            'period_start' => $weekStart->format('d M Y'),
            'period_end' => $weekEnd->format('d M Y'),

            // New contracts this week
            'new_contracts' => Contract::whereBetween('created_at', [$weekStart, $weekEnd])
                ->count(),
            'new_contracts_by_type' => Contract::whereBetween('created_at', [$weekStart, $weekEnd])
                ->select('contract_type', DB::raw('COUNT(*) as count'))
                ->groupBy('contract_type')
                ->pluck('count', 'contract_type')
                ->toArray(),

            // Expiring contracts (next 30 days)
            'expiring_contracts' => Contract::where('end_date', '>=', $now)
                ->where('end_date', '<=', $now->copy()->addDays(30))
                ->whereNotIn('workflow_state', ['cancelled', 'expired'])
                ->count(),

            // Overdue obligations
            'overdue_obligations' => DB::table('obligations_register')
                ->where('due_date', '<', $now)
                ->where('status', '!=', 'completed')
                ->count(),

            // Open escalations
            'open_escalations' => DB::table('escalations')
                ->where('status', 'open')
                ->count(),

            // Compliance issues (if feature enabled)
            'compliance_issues' => config('features.regulatory_compliance', false)
                ? ComplianceFinding::where('status', 'non_compliant')->count()
                : null,

            // Pipeline summary
            'pipeline_summary' => Contract::select('workflow_state', DB::raw('COUNT(*) as count'))
                ->groupBy('workflow_state')
                ->pluck('count', 'workflow_state')
                ->toArray(),
        ];
    }
}
```

**Create the PDF Blade template** at `resources/views/reports/weekly-summary.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCRS Weekly Report — {{ $report_date }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { color: #1e40af; font-size: 20px; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 16px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #d1d5db; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .metric { font-size: 28px; font-weight: bold; color: #1e40af; }
        .metric-label { font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .metric-box { display: inline-block; width: 22%; text-align: center; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin: 4px; }
        .warning { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>CCRS Weekly Report</h1>
    <p>Period: {{ $period_start }} &mdash; {{ $period_end }} | Generated: {{ $report_date }}</p>

    <h2>Key Metrics</h2>
    <div>
        <div class="metric-box">
            <div class="metric">{{ $new_contracts }}</div>
            <div class="metric-label">New Contracts</div>
        </div>
        <div class="metric-box">
            <div class="metric {{ $expiring_contracts > 0 ? 'warning' : '' }}">{{ $expiring_contracts }}</div>
            <div class="metric-label">Expiring (30d)</div>
        </div>
        <div class="metric-box">
            <div class="metric {{ $overdue_obligations > 0 ? 'warning' : '' }}">{{ $overdue_obligations }}</div>
            <div class="metric-label">Overdue Obligations</div>
        </div>
        <div class="metric-box">
            <div class="metric {{ $open_escalations > 0 ? 'warning' : '' }}">{{ $open_escalations }}</div>
            <div class="metric-label">Open Escalations</div>
        </div>
    </div>

    @if (! empty($new_contracts_by_type))
        <h2>New Contracts by Type</h2>
        <table>
            <thead><tr><th>Type</th><th>Count</th></tr></thead>
            <tbody>
                @foreach ($new_contracts_by_type as $type => $count)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $type)) }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($pipeline_summary))
        <h2>Contract Pipeline</h2>
        <table>
            <thead><tr><th>State</th><th>Count</th></tr></thead>
            <tbody>
                @foreach ($pipeline_summary as $state => $count)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $state)) }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($compliance_issues !== null)
        <h2>Compliance</h2>
        <p>Non-compliant findings across all contracts: <strong class="{{ $compliance_issues > 0 ? 'warning' : '' }}">{{ $compliance_issues }}</strong></p>
    @endif

    <div class="footer">
        CCRS — Contract & Merchant Agreement Repository System | Digittal Group
    </div>
</body>
</html>
```

**Create the email template** at `resources/views/emails/weekly-report.blade.php`:

```blade
@component('mail::message')
# CCRS Weekly Report

**Period:** {{ $period_start }} -- {{ $period_end }}

## Summary

- **New Contracts:** {{ $new_contracts }}
- **Expiring Contracts (next 30 days):** {{ $expiring_contracts }}
- **Overdue Obligations:** {{ $overdue_obligations }}
- **Open Escalations:** {{ $open_escalations }}
@if ($compliance_issues !== null)
- **Non-Compliant Findings:** {{ $compliance_issues }}
@endif

The full PDF report is attached to this email.

@component('mail::button', ['url' => config('app.url') . '/admin/analytics-dashboard'])
View Dashboard
@endcomponent

Regards,
CCRS System
@endcomponent
```

**Register the scheduled job** in `routes/console.php` (or `app/Console/Kernel.php` if using the traditional approach):

```php
use App\Jobs\GenerateWeeklyReport;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new GenerateWeeklyReport)->weekly()->mondays()->at('07:00')->name('ccrs-weekly-report');
```

---

### Task 14: Export Enhancements

Extend the existing `ReportExportController` (from Prompt I) with additional export endpoints.

**Add these methods to `app/Http/Controllers/ReportExportController.php`:**

```php
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Full analytics dashboard as a PDF snapshot.
 */
public function analyticsPdf(Request $request)
{
    if (! config('features.advanced_analytics', false)) {
        abort(404);
    }

    $pipeline = Contract::select('workflow_state', DB::raw('COUNT(*) as count'))
        ->groupBy('workflow_state')
        ->pluck('count', 'workflow_state')
        ->toArray();

    $riskDistribution = DB::table('contracts')
        ->join('regions', 'contracts.region_id', '=', 'regions.id')
        ->leftJoin('ai_analysis_results', function ($join) {
            $join->on('ai_analysis_results.contract_id', '=', 'contracts.id')
                 ->where('ai_analysis_results.analysis_type', '=', 'risk');
        })
        ->select(
            'regions.name as region_name',
            DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ai_analysis_results.result, '$.risk_level')), 'unscored') as risk_level"),
            DB::raw('COUNT(*) as count')
        )
        ->whereNotIn('contracts.workflow_state', ['cancelled'])
        ->groupBy('regions.name', 'risk_level')
        ->get();

    $pdf = Pdf::loadView('reports.analytics-snapshot', [
        'pipeline' => $pipeline,
        'risk_distribution' => $riskDistribution,
        'generated_at' => now()->format('d M Y H:i'),
    ]);

    return $pdf->download('ccrs-analytics-snapshot-' . now()->format('Y-m-d') . '.pdf');
}

/**
 * Compliance report for a specific contract.
 */
public function compliancePdf(Request $request, string $contractId)
{
    if (! config('features.regulatory_compliance', false)) {
        abort(404);
    }

    $contract = Contract::with(['counterparty', 'entity', 'region'])->findOrFail($contractId);
    $findings = ComplianceFinding::where('contract_id', $contractId)
        ->with('framework')
        ->orderBy('framework_id')
        ->orderBy('requirement_id')
        ->get()
        ->groupBy('framework_id');

    $service = app(\App\Services\RegulatoryComplianceService::class);
    $scores = $service->getScoreSummary($contract);

    $pdf = Pdf::loadView('reports.compliance-report', [
        'contract' => $contract,
        'findings' => $findings,
        'scores' => $scores,
        'generated_at' => now()->format('d M Y H:i'),
    ]);

    return $pdf->download('ccrs-compliance-report-' . $contract->id . '.pdf');
}

/**
 * Obligations register filtered export as Excel.
 */
public function obligationsExcel(Request $request)
{
    $query = DB::table('obligations_register')
        ->join('contracts', 'obligations_register.contract_id', '=', 'contracts.id')
        ->select(
            'contracts.title as contract_title',
            'obligations_register.obligation_type',
            'obligations_register.description',
            'obligations_register.due_date',
            'obligations_register.status',
            'obligations_register.created_at'
        );

    if ($request->filled('status')) {
        $query->where('obligations_register.status', $request->input('status'));
    }
    if ($request->filled('obligation_type')) {
        $query->where('obligations_register.obligation_type', $request->input('obligation_type'));
    }
    if ($request->filled('date_from')) {
        $query->where('obligations_register.due_date', '>=', $request->input('date_from'));
    }
    if ($request->filled('date_to')) {
        $query->where('obligations_register.due_date', '<=', $request->input('date_to'));
    }

    $data = $query->orderBy('obligations_register.due_date')->get();

    return Excel::download(
        new \App\Exports\ObligationsExport($data),
        'ccrs-obligations-' . now()->format('Y-m-d') . '.xlsx'
    );
}
```

**Create `app/Exports/ObligationsExport.php`:**

```php
<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ObligationsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        private Collection $data,
    ) {}

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Contract',
            'Obligation Type',
            'Description',
            'Due Date',
            'Status',
            'Created At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->contract_title,
            ucwords(str_replace('_', ' ', $row->obligation_type)),
            $row->description,
            $row->due_date,
            ucfirst($row->status),
            $row->created_at,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

**Create the PDF views:**

`resources/views/reports/analytics-snapshot.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCRS Analytics Snapshot</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { color: #1e40af; font-size: 20px; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 16px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #d1d5db; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 32px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>CCRS Analytics Snapshot</h1>
    <p>Generated: {{ $generated_at }}</p>

    <h2>Contract Pipeline</h2>
    <table>
        <thead><tr><th>State</th><th>Count</th></tr></thead>
        <tbody>
            @foreach ($pipeline as $state => $count)
                <tr><td>{{ ucwords(str_replace('_', ' ', $state)) }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Risk Distribution by Region</h2>
    <table>
        <thead><tr><th>Region</th><th>Risk Level</th><th>Count</th></tr></thead>
        <tbody>
            @foreach ($risk_distribution as $row)
                <tr>
                    <td>{{ $row->region_name }}</td>
                    <td>{{ ucfirst($row->risk_level) }}</td>
                    <td>{{ $row->count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">CCRS — Contract & Merchant Agreement Repository System | Digittal Group</div>
</body>
</html>
```

`resources/views/reports/compliance-report.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Compliance Report — {{ $contract->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        h1 { color: #1e40af; font-size: 18px; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 14px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; text-align: left; padding: 6px; border-bottom: 2px solid #d1d5db; font-size: 10px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .compliant { color: #16a34a; font-weight: bold; }
        .non_compliant { color: #dc2626; font-weight: bold; }
        .unclear { color: #d97706; font-weight: bold; }
        .not_applicable { color: #6b7280; }
        .score { font-size: 24px; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>Compliance Report</h1>
    <p><strong>Contract:</strong> {{ $contract->title }}</p>
    <p><strong>Counterparty:</strong> {{ $contract->counterparty?->name ?? 'N/A' }}</p>
    <p><strong>Entity:</strong> {{ $contract->entity?->name ?? 'N/A' }} | <strong>Region:</strong> {{ $contract->region?->name ?? 'N/A' }}</p>
    <p><strong>Generated:</strong> {{ $generated_at }}</p>

    @foreach ($findings as $frameworkId => $frameworkFindings)
        @php
            $framework = $frameworkFindings->first()?->framework;
            $score = $scores[$frameworkId] ?? null;
        @endphp

        <h2>{{ $framework?->framework_name ?? 'Unknown Framework' }} ({{ $framework?->jurisdiction_code ?? '' }})</h2>

        @if ($score)
            <p>
                Compliance Score: <span class="score {{ $score['score'] >= 80 ? 'compliant' : ($score['score'] >= 50 ? 'unclear' : 'non_compliant') }}">{{ $score['score'] }}%</span>
                &mdash; {{ $score['compliant'] }} compliant, {{ $score['non_compliant'] }} non-compliant, {{ $score['unclear'] }} unclear, {{ $score['not_applicable'] }} N/A out of {{ $score['total'] }} requirements
            </p>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width:8%">ID</th>
                    <th style="width:25%">Requirement</th>
                    <th style="width:10%">Status</th>
                    <th style="width:25%">Evidence</th>
                    <th style="width:25%">Rationale</th>
                    <th style="width:7%">Conf.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($frameworkFindings as $finding)
                    <tr>
                        <td>{{ $finding->requirement_id }}</td>
                        <td>{{ $finding->requirement_text }}</td>
                        <td class="{{ $finding->status }}">{{ ucwords(str_replace('_', ' ', $finding->status)) }}</td>
                        <td>{{ $finding->evidence_clause ?? '—' }}</td>
                        <td>{{ $finding->ai_rationale ?? '—' }}</td>
                        <td>{{ $finding->confidence !== null ? round($finding->confidence * 100) . '%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($findings->isEmpty())
        <p>No compliance findings available. Run a compliance check to generate findings.</p>
    @endif

    <div class="footer">
        CCRS — Contract & Merchant Agreement Repository System | Digittal Group<br>
        This report is for informational purposes only and does not constitute legal advice.
    </div>
</body>
</html>
```

**Register the export routes** in `routes/web.php` (or wherever existing report exports are registered):

```php
use App\Http\Controllers\ReportExportController;

Route::middleware(['auth'])->prefix('reports/export')->group(function () {
    // ... existing export routes from Prompt I ...

    Route::get('/analytics/pdf', [ReportExportController::class, 'analyticsPdf'])
        ->name('reports.export.analytics.pdf');

    Route::get('/compliance/{contract_id}/pdf', [ReportExportController::class, 'compliancePdf'])
        ->name('reports.export.compliance.pdf');

    Route::get('/obligations/excel', [ReportExportController::class, 'obligationsExcel'])
        ->name('reports.export.obligations.excel');
});
```

---

### Task 15: Feature Flags Configuration

Update `config/features.php` to ensure `advanced_analytics` is present (it may already exist from Prompt K):

```php
// In config/features.php, ensure these entries exist:
'regulatory_compliance' => env('FEATURE_REGULATORY_COMPLIANCE', false),
'advanced_analytics'    => env('FEATURE_ADVANCED_ANALYTICS', false),
```

Update `.env.example` to include:

```dotenv
# --- Phase 2 Feature Flags ---
FEATURE_REGULATORY_COMPLIANCE=false
FEATURE_ADVANCED_ANALYTICS=false
```

---

### Task 16: Feature Tests

Create `tests/Feature/ComplianceCheckTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use App\Models\User;
use App\Services\RegulatoryComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComplianceCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.regulatory_compliance' => true]);
    }

    public function test_compliance_check_dispatches_job(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = Contract::factory()->create([
            'extracted_text' => 'This is a sample contract with data processing clauses...',
        ]);

        $framework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'EU',
            'framework_name' => 'Test GDPR',
            'requirements' => [
                ['id' => 'test-1', 'text' => 'Must include DPA', 'category' => 'data_protection', 'severity' => 'critical'],
            ],
        ]);

        $this->expectsJobs(\App\Jobs\ProcessComplianceCheck::class);

        $service = app(RegulatoryComplianceService::class);
        $service->runComplianceCheck($contract, $framework);
    }

    public function test_compliance_check_blocked_when_feature_disabled(): void
    {
        config(['features.regulatory_compliance' => false]);

        $contract = Contract::factory()->create();

        $service = app(RegulatoryComplianceService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not enabled/');

        $service->runComplianceCheck($contract);
    }

    public function test_review_finding_updates_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $finding = ComplianceFinding::factory()->create([
            'status' => 'unclear',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $service = app(RegulatoryComplianceService::class);
        $updated = $service->reviewFinding($finding, 'compliant', $user);

        $this->assertEquals('compliant', $updated->status);
        $this->assertEquals($user->id, $updated->reviewed_by);
        $this->assertNotNull($updated->reviewed_at);
    }

    public function test_get_findings_grouped_by_framework(): void
    {
        $contract = Contract::factory()->create();
        $fw1 = RegulatoryFramework::factory()->create();
        $fw2 = RegulatoryFramework::factory()->create();

        ComplianceFinding::factory()->count(3)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw1->id,
        ]);
        ComplianceFinding::factory()->count(2)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw2->id,
        ]);

        $service = app(RegulatoryComplianceService::class);
        $findings = $service->getFindings($contract);

        $this->assertCount(2, $findings); // 2 groups
        $this->assertCount(3, $findings[$fw1->id]);
        $this->assertCount(2, $findings[$fw2->id]);
    }
}
```

Create `tests/Feature/AnalyticsApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.advanced_analytics' => true]);
    }

    public function test_pipeline_endpoint_returns_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        Contract::factory()->count(3)->create(['workflow_state' => 'draft']);
        Contract::factory()->count(2)->create(['workflow_state' => 'executed']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/pipeline');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_analytics_blocked_when_feature_disabled(): void
    {
        config(['features.advanced_analytics' => false]);

        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/pipeline');

        $response->assertNotFound();
    }

    public function test_ai_costs_endpoint_returns_summary(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/ai-costs');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['daily', 'summary']]);
    }

    public function test_obligations_timeline_accepts_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/obligations-timeline?status=pending');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
```

---

### Task 17: Model Factories

Create factories for the new models if they do not already exist.

**Create `database/factories/RegulatoryFrameworkFactory.php`:**

```php
<?php

namespace Database\Factories;

use App\Models\RegulatoryFramework;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RegulatoryFrameworkFactory extends Factory
{
    protected $model = RegulatoryFramework::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'jurisdiction_code' => $this->faker->randomElement(['EU', 'US', 'AE', 'GB', 'GLOBAL']),
            'framework_name' => $this->faker->words(3, true) . ' Compliance Framework',
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'requirements' => [
                [
                    'id' => 'req-1',
                    'text' => $this->faker->sentence(),
                    'category' => $this->faker->randomElement(['data_protection', 'financial', 'employment', 'other']),
                    'severity' => $this->faker->randomElement(['critical', 'high', 'medium', 'low']),
                ],
                [
                    'id' => 'req-2',
                    'text' => $this->faker->sentence(),
                    'category' => 'data_protection',
                    'severity' => 'medium',
                ],
            ],
        ];
    }
}
```

**Create `database/factories/ComplianceFindingFactory.php`:**

```php
<?php

namespace Database\Factories;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ComplianceFindingFactory extends Factory
{
    protected $model = ComplianceFinding::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'framework_id' => RegulatoryFramework::factory(),
            'requirement_id' => 'req-' . $this->faker->numberBetween(1, 100),
            'requirement_text' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['compliant', 'non_compliant', 'unclear', 'not_applicable']),
            'evidence_clause' => $this->faker->optional()->sentence(),
            'evidence_page' => $this->faker->optional()->numberBetween(1, 50),
            'ai_rationale' => $this->faker->sentence(),
            'confidence' => $this->faker->randomFloat(2, 0.3, 1.0),
        ];
    }
}
```

---

## Verification Checklist

### Regulatory Compliance (Part A)

1. **Migration runs**: `php artisan migrate` creates `regulatory_frameworks` and `compliance_findings` tables without error.
2. **Seeder populates frameworks**: `php artisan db:seed --class=RegulatoryFrameworkSeeder` creates 3 frameworks (GDPR, PCI DSS, UAE Federal Law). Verify: `php artisan tinker` then `RegulatoryFramework::count()` returns `3`.
3. **RegulatoryFrameworkResource accessible**: Navigate to `/admin/regulatory-frameworks` as a `system_admin` user — the list page loads with the 3 seeded frameworks. Verify requirement counts display correctly.
4. **Edit framework**: Edit the GDPR framework — the Repeater field shows all 10 requirements. Add a new requirement and save — the requirements array grows.
5. **Compliance check dispatches**: Create a contract with `extracted_text` populated, then run `app(RegulatoryComplianceService::class)->runComplianceCheck($contract, $framework)` — `ProcessComplianceCheck` job is dispatched to the queue.
6. **AI worker endpoint**: Send a `POST /check-compliance` request to the AI worker with test data — the response contains a `findings` array with `requirement_id`, `status`, `evidence_clause`, `rationale`, and `confidence` for each requirement.
7. **Findings populated**: After processing the job, `ComplianceFinding::where('contract_id', $contract->id)->count()` equals the number of requirements in the framework.
8. **Review finding**: As a `legal` user, review a finding with status `unclear` and override it to `compliant` — the `reviewed_by` and `reviewed_at` fields are set. Status changes to `compliant`.
9. **Compliance tab on contract**: View a contract with compliance findings — the Compliance Findings relation manager table shows findings grouped by framework with status badges (green/red/amber/gray).
10. **Compliance score displays**: The Compliance Overview section on the contract view page shows a percentage score per framework.
11. **Feature gate enforcement**: Set `FEATURE_REGULATORY_COMPLIANCE=false` in `.env` and clear config cache — the Compliance navigation item disappears, the compliance tab is hidden on contracts, and `runComplianceCheck()` throws a `RuntimeException`.

### Advanced Analytics (Part B)

12. **Analytics dashboard renders**: Navigate to `/admin/analytics-dashboard` as `system_admin` — the page loads with all 6 widget sections.
13. **Pipeline funnel**: The Contract Pipeline widget shows correct counts matching `SELECT workflow_state, COUNT(*) FROM contracts GROUP BY workflow_state`.
14. **Risk distribution**: The Risk Distribution widget shows horizontal bar chart with contracts grouped by risk level and region.
15. **Compliance overview**: (With `regulatory_compliance` enabled) The Compliance Overview widget shows a donut chart with aggregated finding counts.
16. **Obligation tracker**: The Obligation Tracker widget shows a table of upcoming/overdue obligations. Overdue rows are highlighted in red.
17. **AI cost chart**: The AI Usage & Cost widget shows a line chart with daily token usage and estimated cost over the last 30 days.
18. **Workflow performance**: The Workflow Performance widget shows a table with average stage durations. Stages >48h average are flagged as bottlenecks.
19. **API endpoints**: All 6 `/api/analytics/*` endpoints return valid JSON with appropriate data structures when accessed with authentication.
20. **Weekly report generates**: Run `php artisan schedule:run` (or dispatch `GenerateWeeklyReport` manually) — a PDF is generated, stored in S3 under `reports/weekly/`, and emails are sent to admin/legal users.
21. **Analytics PDF export**: `GET /reports/export/analytics/pdf` downloads a valid PDF with pipeline and risk distribution tables.
22. **Compliance PDF export**: `GET /reports/export/compliance/{contract_id}/pdf` downloads a PDF with compliance findings, scores, and evidence for the specified contract.
23. **Obligations Excel export**: `GET /reports/export/obligations/excel` downloads a valid `.xlsx` file with obligation data. Filters work (status, obligation_type, date range).
24. **Feature gate**: Set `FEATURE_ADVANCED_ANALYTICS=false` — the Analytics Dashboard page returns 404, all `/api/analytics/*` endpoints return 404, weekly report job skips execution.
25. **All feature tests pass**: `php artisan test --filter=ComplianceCheckTest` and `php artisan test --filter=AnalyticsApiTest` — all assertions pass.
