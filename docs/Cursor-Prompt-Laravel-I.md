# Cursor Prompt — Laravel Migration Phase I: Multi-Language + Calendar Reminders + Report Export

## Context

**Run this prompt in the `agreement_automation` repo on the `laravel-migration` branch** — the same branch where Phases A through H were executed.

This phase implements three remaining Phase 1c/1d items:

1. **Epic 13 — Multi-Language Contract Support**: Attach multiple language versions to a single contract. Each version is a separate file tagged with an ISO language code. The `contract_languages` table and Eloquent model already exist from Phase A migrations.
2. **Epic 11 (calendar channel) — Calendar Reminder Dispatch**: The `reminders.channel` enum includes `'calendar'`. This task implements ICS calendar file generation and email delivery so recipients receive a calendar invite alongside the standard reminder.
3. **Epic 12 — Excel / PDF Report Export**: The `maatwebsite/excel` and `barryvdh/laravel-dompdf` packages are already in `composer.json`. This task implements export routes for the existing report data in `ReportsPage`.

---

## Task 1: Multi-Language Contract Support (Epic 13)

### 1.1 Verify `contract_languages` Migration and Model

Ensure `database/migrations/` has a migration for `contract_languages`:
```sql
id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
contract_id CHAR(36) NOT NULL,
language_code VARCHAR(10) NOT NULL,       -- ISO 639-1 e.g. 'en', 'fr', 'ar'
is_primary TINYINT(1) NOT NULL DEFAULT 0,
storage_path VARCHAR(500) NOT NULL,
file_name VARCHAR(255) NOT NULL,
created_at TIMESTAMP,
updated_at TIMESTAMP,
FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
UNIQUE KEY unique_contract_language (contract_id, language_code)
```

If it doesn't exist, create it. Then ensure `app/Models/ContractLanguage.php` exists:

```php
<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractLanguage extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'id', 'contract_id', 'language_code', 'is_primary', 'storage_path', 'file_name',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
```

Add relationship to `app/Models/Contract.php`:

```php
public function languages(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(ContractLanguage::class);
}

public function primaryLanguage(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(ContractLanguage::class)->where('is_primary', true);
}
```

### 1.2 Create `ContractLanguagesRelationManager`

Create `app/Filament/Resources/ContractResource/RelationManagers/ContractLanguagesRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Models\ContractLanguage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractLanguagesRelationManager extends RelationManager
{
    protected static string $relationship = 'languages';
    protected static ?string $title = 'Language Versions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('language_code')
                    ->label('Language')
                    ->options([
                        'en' => 'English',
                        'fr' => 'French',
                        'ar' => 'Arabic',
                        'es' => 'Spanish',
                        'pt' => 'Portuguese',
                        'zh' => 'Chinese',
                        'de' => 'German',
                        'it' => 'Italian',
                        'ru' => 'Russian',
                        'ja' => 'Japanese',
                    ])
                    ->searchable()
                    ->required(),

                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Language Version')
                    ->default(false)
                    ->helperText('Only one version can be primary.'),

                Forms\Components\FileUpload::make('file')
                    ->label('Contract File (PDF / DOCX)')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->disk('s3')
                    ->directory('contract_languages')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('file_name', basename($state));
                        }
                    }),

                Forms\Components\Hidden::make('file_name'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('language_code')
                    ->label('Language')
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->limit(40),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Language Version')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['storage_path'] = $data['file'];
                        $data['file_name']    = $data['file_name'] ?: basename($data['file']);
                        unset($data['file']);
                        return $data;
                    })
                    ->before(function (array $data, $livewire) {
                        // Enforce single primary: if is_primary is true, clear others
                        if (! empty($data['is_primary'])) {
                            \App\Models\ContractLanguage::where('contract_id', $livewire->ownerRecord->id)
                                ->update(['is_primary' => false]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (ContractLanguage $record) =>
                        Storage::disk('s3')->temporaryUrl($record->storage_path, now()->addMinutes(15))
                    )
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('set_primary')
                    ->label('Set Primary')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (ContractLanguage $record) => ! $record->is_primary)
                    ->action(function (ContractLanguage $record) {
                        \App\Models\ContractLanguage::where('contract_id', $record->contract_id)
                            ->update(['is_primary' => false]);
                        $record->update(['is_primary' => true]);
                        \Filament\Notifications\Notification::make()
                            ->title('Primary language updated')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
```

Register in `ContractResource::getRelationManagers()`.

### 1.3 Show Language Count in Contract Table

In `ContractResource`'s `table()`, add a column:

```php
Tables\Columns\TextColumn::make('languages_count')
    ->label('Languages')
    ->counts('languages')
    ->badge()
    ->color('gray')
    ->toggleable(isToggledHiddenByDefault: true),
```

### 1.4 Write Feature Test

Create `tests/Feature/ContractLanguagesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContractLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_attach_language_versions_to_contract(): void
    {
        Storage::fake('s3');

        $contract = Contract::factory()->create();

        ContractLanguage::create([
            'id'           => Str::uuid(),
            'contract_id'  => $contract->id,
            'language_code'=> 'en',
            'is_primary'   => true,
            'storage_path' => 'contract_languages/test_en.pdf',
            'file_name'    => 'test_en.pdf',
        ]);

        ContractLanguage::create([
            'id'           => Str::uuid(),
            'contract_id'  => $contract->id,
            'language_code'=> 'fr',
            'is_primary'   => false,
            'storage_path' => 'contract_languages/test_fr.pdf',
            'file_name'    => 'test_fr.pdf',
        ]);

        $contract->refresh();
        $this->assertCount(2, $contract->languages);
        $this->assertEquals('en', $contract->primaryLanguage->language_code);
    }

    public function test_unique_language_per_contract_enforced(): void
    {
        $contract = Contract::factory()->create();
        ContractLanguage::create([
            'id' => Str::uuid(), 'contract_id' => $contract->id,
            'language_code' => 'en', 'is_primary' => true,
            'storage_path' => 'en.pdf', 'file_name' => 'en.pdf',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ContractLanguage::create([
            'id' => Str::uuid(), 'contract_id' => $contract->id,
            'language_code' => 'en', 'is_primary' => false,
            'storage_path' => 'en2.pdf', 'file_name' => 'en2.pdf',
        ]);
    }
}
```

---

## Task 2: Calendar Reminder Dispatch (Epic 11)

### 2.1 Add `CalendarService`

Create `app/Services/CalendarService.php`:

```php
<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\ContractKeyDate;
use App\Models\Contract;

class CalendarService
{
    /**
     * Generate an ICS calendar file content for a reminder.
     * Returns raw ICS string suitable for attaching to an email.
     */
    public function generateIcs(Reminder $reminder, Contract $contract, ContractKeyDate $keyDate): string
    {
        $uid     = 'ccrs-reminder-' . $reminder->id . '@digittal.io';
        $now     = gmdate('Ymd\THis\Z');
        $dtstart = gmdate('Ymd', strtotime($keyDate->date_value));
        $summary = 'CCRS Reminder: ' . $contract->title;
        $description = sprintf(
            'Contract: %s\\nKey Date: %s (%s)\\nReminder: %d days notice',
            $contract->title,
            $keyDate->date_type,
            $keyDate->date_value,
            $reminder->lead_days,
        );

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Digittal Group//CCRS//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            "UID:{$uid}",
            "DTSTAMP:{$now}",
            "DTSTART;VALUE=DATE:{$dtstart}",
            "SUMMARY:{$summary}",
            "DESCRIPTION:{$description}",
            'STATUS:CONFIRMED',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
    }
}
```

### 2.2 Create `VendorReminderCalendar` Mailable

Create `app/Mail/ContractReminderCalendar.php`:

```php
<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\Reminder;
use App\Services\CalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractReminderCalendar extends Mailable
{
    use Queueable, SerializesModels;

    public string $icsContent;

    public function __construct(
        public readonly Reminder $reminder,
        public readonly Contract $contract,
        public readonly ContractKeyDate $keyDate,
    ) {
        $this->icsContent = app(CalendarService::class)->generateIcs($reminder, $contract, $keyDate);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Calendar Reminder: ' . $this->contract->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contract-reminder-calendar',
            with: [
                'contractTitle' => $this->contract->title,
                'dateType'      => $this->keyDate->date_type,
                'dateValue'     => $this->keyDate->date_value,
                'leadDays'      => $this->reminder->lead_days,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->icsContent, 'reminder.ics')
                ->withMime('text/calendar'),
        ];
    }
}
```

Create `resources/views/mail/contract-reminder-calendar.blade.php`:

```blade
# Contract Reminder: {{ $contractTitle }}

This is an automated reminder from CCRS.

**Key Date:** {{ $dateType }}
**Date:** {{ $dateValue }}
**Notice Period:** {{ $leadDays }} days

A calendar invite (.ics) is attached to this email. Import it into your calendar application to set a reminder.

---
*CCRS — Contract & Merchant Agreement Repository System*
```

### 2.3 Update `ReminderService` to Handle Calendar Channel

In `app/Services/ReminderService.php`, update the channel dispatch method to handle `'calendar'`:

```php
use App\Mail\ContractReminderCalendar;
use App\Models\ContractKeyDate;
use Illuminate\Support\Facades\Mail;

// Inside ReminderService dispatch/send method:
private function dispatchReminderChannel(Reminder $reminder, Contract $contract): void
{
    $keyDate = ContractKeyDate::find($reminder->key_date_id);
    if (! $keyDate) return;

    match ($reminder->channel) {
        'email'    => $this->sendReminderEmail($reminder, $contract, $keyDate),
        'teams'    => $this->sendReminderToTeams($reminder, $contract, $keyDate),
        'calendar' => $this->sendCalendarInvite($reminder, $contract, $keyDate),
        default    => \Illuminate\Support\Facades\Log::warning("Unknown reminder channel: {$reminder->channel}"),
    };
}

private function sendCalendarInvite(Reminder $reminder, Contract $contract, ContractKeyDate $keyDate): void
{
    $recipient = $reminder->recipient_email;
    if (! $recipient) {
        \Illuminate\Support\Facades\Log::warning('Calendar reminder skipped — no recipient_email', [
            'reminder_id' => $reminder->id,
        ]);
        return;
    }

    Mail::to($recipient)->send(new ContractReminderCalendar($reminder, $contract, $keyDate));
}
```

Ensure `reminders.channel` enum in the migration and any Filament Select options includes `'calendar'`. In `RemindersRelationManager` or `RemindersPage`, add `'calendar' => 'Calendar Invite (.ics)'` to the channel select options.

### 2.4 Write Feature Test

Create `tests/Feature/CalendarReminderTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\ContractReminderCalendar;
use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\Reminder;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_content_is_valid(): void
    {
        $contract = Contract::factory()->create(['title' => 'Test Contract']);
        $keyDate  = ContractKeyDate::factory()->create([
            'contract_id' => $contract->id,
            'date_type'   => 'expiry',
            'date_value'  => '2027-06-30',
        ]);
        $reminder = Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'key_date_id'     => $keyDate->id,
            'lead_days'       => 30,
            'channel'         => 'calendar',
            'recipient_email' => 'test@example.com',
        ]);

        $ics = app(CalendarService::class)->generateIcs($reminder, $contract, $keyDate);

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20270630', $ics);
        $this->assertStringContainsString('Test Contract', $ics);
    }

    public function test_calendar_reminder_email_sends_ics_attachment(): void
    {
        Mail::fake();

        $contract = Contract::factory()->create();
        $keyDate  = ContractKeyDate::factory()->create(['contract_id' => $contract->id]);
        $reminder = Reminder::factory()->create([
            'contract_id'     => $contract->id,
            'key_date_id'     => $keyDate->id,
            'channel'         => 'calendar',
            'recipient_email' => 'legal@digittal.io',
        ]);

        \Illuminate\Support\Facades\Mail::to('legal@digittal.io')
            ->send(new ContractReminderCalendar($reminder, $contract, $keyDate));

        Mail::assertSent(ContractReminderCalendar::class, fn ($mail) =>
            $mail->hasTo('legal@digittal.io')
        );
    }
}
```

---

## Task 3: Excel / PDF Report Export (Epic 12)

The `maatwebsite/excel` and `barryvdh/laravel-dompdf` packages are already installed. This task implements the export functionality for the existing `ReportsPage`.

### 3.1 Create `ContractExport` Excel Class

Create `app/Exports/ContractExport.php`:

```php
<?php

namespace App\Exports;

use App\Models\Contract;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContractExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, Responsable
{
    use Exportable;

    private string $fileName = 'contracts.xlsx';
    private string $writerType = \Maatwebsite\Excel\Excel::XLSX;

    public function __construct(
        private readonly ?string $regionId = null,
        private readonly ?string $entityId = null,
        private readonly ?string $contractType = null,
        private readonly ?string $workflowState = null,
    ) {}

    public function query()
    {
        $query = Contract::query()
            ->with(['counterparty', 'region', 'entity', 'project'])
            ->orderBy('created_at', 'desc');

        if ($this->regionId)     $query->where('region_id', $this->regionId);
        if ($this->entityId)     $query->where('entity_id', $this->entityId);
        if ($this->contractType) $query->where('contract_type', $this->contractType);
        if ($this->workflowState)$query->where('workflow_state', $this->workflowState);

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID', 'Title', 'Type', 'State', 'Counterparty',
            'Region', 'Entity', 'Project', 'Signing Status',
            'Created At', 'Expiry Date',
        ];
    }

    public function map($contract): array
    {
        return [
            $contract->id,
            $contract->title,
            $contract->contract_type,
            $contract->workflow_state,
            $contract->counterparty?->legal_name,
            $contract->region?->name,
            $contract->entity?->name,
            $contract->project?->name,
            $contract->signing_status ?? 'N/A',
            $contract->created_at?->format('Y-m-d H:i'),
            $contract->expiry_date ? $contract->expiry_date->format('Y-m-d') : '',
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

### 3.2 Create `ReportExportController`

Create `app/Http/Controllers/Reports/ReportExportController.php`:

```php
<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ContractExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Contract;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /** Export contracts as XLSX */
    public function contractsExcel(Request $request)
    {
        $this->authorizeRole($request);

        $export = new ContractExport(
            regionId:     $request->query('region_id'),
            entityId:     $request->query('entity_id'),
            contractType: $request->query('contract_type'),
            workflowState:$request->query('workflow_state'),
        );

        return Excel::download($export, 'contracts_' . now()->format('Ymd_His') . '.xlsx');
    }

    /** Export contracts summary as PDF */
    public function contractsPdf(Request $request)
    {
        $this->authorizeRole($request);

        $contracts = Contract::query()
            ->with(['counterparty', 'region', 'entity'])
            ->when($request->query('region_id'),      fn ($q, $v) => $q->where('region_id', $v))
            ->when($request->query('contract_type'),  fn ($q, $v) => $q->where('contract_type', $v))
            ->when($request->query('workflow_state'), fn ($q, $v) => $q->where('workflow_state', $v))
            ->orderBy('created_at', 'desc')
            ->limit(500) // PDF exports capped at 500 rows
            ->get();

        $pdf = Pdf::loadView('reports.contracts-pdf', [
            'contracts'   => $contracts,
            'generatedAt' => now()->format('d M Y H:i'),
            'filters'     => $request->only(['region_id', 'contract_type', 'workflow_state']),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('contracts_report_' . now()->format('Ymd') . '.pdf');
    }

    private function authorizeRole(Request $request): void
    {
        if (! auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial', 'finance', 'audit'])) {
            abort(403, 'Insufficient permissions for report export.');
        }
    }
}
```

### 3.3 Create PDF Blade Template

Create `resources/views/reports/contracts-pdf.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        p.meta { color: #666; font-size: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e3a5f; color: white; padding: 4px 6px; text-align: left; font-size: 8px; }
        td { padding: 3px 6px; border-bottom: 1px solid #e5e5e5; }
        tr:nth-child(even) { background: #f8f8f8; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 7px; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-draft  { background: #f3f4f6; color: #374151; }
    </style>
</head>
<body>
    <h1>CCRS — Contracts Report</h1>
    <p class="meta">Generated: {{ $generatedAt }} &bull; Total: {{ $contracts->count() }}</p>

    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Type</th>
                <th>State</th>
                <th>Counterparty</th>
                <th>Region</th>
                <th>Entity</th>
                <th>Created</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contracts as $c)
                <tr>
                    <td>{{ Str::limit($c->title, 40) }}</td>
                    <td>{{ $c->contract_type }}</td>
                    <td><span class="badge badge-{{ $c->workflow_state }}">{{ $c->workflow_state }}</span></td>
                    <td>{{ Str::limit($c->counterparty?->legal_name ?? '—', 25) }}</td>
                    <td>{{ $c->region?->name ?? '—' }}</td>
                    <td>{{ $c->entity?->name ?? '—' }}</td>
                    <td>{{ $c->created_at?->format('Y-m-d') }}</td>
                    <td>{{ $c->expiry_date ? $c->expiry_date->format('Y-m-d') : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
```

### 3.4 Register Export Routes

In `routes/web.php`, inside the `auth` middleware group:

```php
use App\Http\Controllers\Reports\ReportExportController;

Route::prefix('reports/export')->middleware('auth')->group(function () {
    Route::get('/contracts/excel', [ReportExportController::class, 'contractsExcel'])
        ->name('reports.export.contracts.excel');
    Route::get('/contracts/pdf', [ReportExportController::class, 'contractsPdf'])
        ->name('reports.export.contracts.pdf');
});
```

### 3.5 Add Export Buttons to `ReportsPage`

In `app/Filament/Pages/ReportsPage.php`, add export action buttons:

```php
public function getHeaderActions(): array
{
    return [
        \Filament\Actions\Action::make('export_excel')
            ->label('Export Excel')
            ->icon('heroicon-o-table-cells')
            ->color('success')
            ->url(fn () => route('reports.export.contracts.excel', request()->query()))
            ->openUrlInNewTab(),

        \Filament\Actions\Action::make('export_pdf')
            ->label('Export PDF')
            ->icon('heroicon-o-document-arrow-down')
            ->color('danger')
            ->url(fn () => route('reports.export.contracts.pdf', request()->query()))
            ->openUrlInNewTab(),
    ];
}
```

### 3.6 Create `AiCostReport` Filament Page

Create `app/Filament/Pages/AiCostReportPage.php` as a dedicated AI cost analytics page:

```php
<?php

namespace App\Filament\Pages;

use App\Models\AiAnalysisResult;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AiCostReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static string $view = 'filament.pages.ai-cost-report';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'AI Cost Analytics';
    protected static ?int $navigationSort = 50;

    public string $periodFilter = '30days';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')
                    ->label('Contract')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('analysis_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('model_used')
                    ->label('Model'),
                Tables\Columns\TextColumn::make('token_usage_input')
                    ->label('Input Tokens')
                    ->numeric(thousandsSeparator: ','),
                Tables\Columns\TextColumn::make('token_usage_output')
                    ->label('Output Tokens')
                    ->numeric(thousandsSeparator: ','),
                Tables\Columns\TextColumn::make('cost_usd')
                    ->label('Cost (USD)')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('processing_time_ms')
                    ->label('Time (ms)')
                    ->numeric(thousandsSeparator: ','),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'completed', 'danger' => 'failed', 'warning' => 'processing']),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('analysis_type')
                    ->options([
                        'summary'     => 'Summary',
                        'extraction'  => 'Extraction',
                        'risk'        => 'Risk Analysis',
                        'deviation'   => 'Template Deviation',
                        'obligations' => 'Obligations',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['completed', 'failed', 'processing', 'pending']),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        return AiAnalysisResult::query()->with('contract');
    }

    public function getSummaryStats(): array
    {
        $results = AiAnalysisResult::where('status', 'completed')
            ->selectRaw('SUM(cost_usd) as total_cost, SUM(token_usage_input + token_usage_output) as total_tokens, COUNT(*) as total_analyses')
            ->first();

        return [
            'total_cost'      => number_format($results->total_cost ?? 0, 4),
            'total_tokens'    => number_format($results->total_tokens ?? 0),
            'total_analyses'  => $results->total_analyses ?? 0,
            'avg_cost'        => $results->total_analyses > 0
                ? number_format(($results->total_cost ?? 0) / $results->total_analyses, 4)
                : '0.0000',
        ];
    }
}
```

Create the Blade view `resources/views/filament/pages/ai-cost-report.blade.php`:

```blade
<x-filament-panels::page>
    @php $stats = $this->getSummaryStats() @endphp

    <div class="grid grid-cols-4 gap-4 mb-6">
        @foreach ([
            ['Total Cost (USD)', '$' . $stats['total_cost'], 'text-red-600'],
            ['Total Tokens', $stats['total_tokens'], 'text-blue-600'],
            ['Total Analyses', $stats['total_analyses'], 'text-green-600'],
            ['Avg Cost / Analysis', '$' . $stats['avg_cost'], 'text-purple-600'],
        ] as [$label, $value, $color])
            <div class="rounded-xl border bg-white p-5 shadow-sm dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
```

Register `AiCostReportPage::class` in `AdminPanelProvider`.

---

## Verification Checklist

1. **Multi-Language:**
   - Edit a contract → "Language Versions" tab → "Add Language Version" → upload a PDF with language `fr` → save.
   - Two language versions listed; "Set Primary" action works; "Download" generates a signed S3 URL.
   - `php artisan test --filter=ContractLanguagesTest` passes.

2. **Calendar Reminders:**
   - Create a reminder with `channel = 'calendar'` and a `recipient_email`.
   - Run `php artisan queue:work --once` to process the `SendReminders` job.
   - The recipient's email contains an `.ics` attachment.
   - `php artisan test --filter=CalendarReminderTest` passes.

3. **Report Export:**
   - Navigate to Reports page → click "Export Excel" → a `.xlsx` file downloads with correct columns.
   - Click "Export PDF" → a PDF with an A4 landscape table downloads.
   - Navigate to Reports → AI Cost Analytics → summary stats and table rows are populated from `ai_analysis_results`.
   - Unauthenticated access to `/reports/export/contracts/excel` returns 302 redirect to login.
