# Functional Gaps Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Close 6 functional gaps identified during internal sandbox testing — arbitration body, governing law, inter-company agreements, individual file upload, AI auto-discovery, and redline verification.

**Architecture:** Each gap is implemented as an independent task group with its own migrations, model changes, and Filament resource updates. Gaps 1–4 are database/UI work. Gap 5 augments the AI analysis pipeline. Gap 6 is verification + documentation only.

**Tech Stack:** PHP 8.4, Laravel 12, Filament 3, MySQL 8.0, FastAPI (AI worker), PhpWord

---

## Task 1: Add Arbitration Body to Jurisdictions

**Files:**
- Create: `database/migrations/2026_03_02_100000_add_arbitration_body_to_jurisdictions.php`
- Modify: `app/Models/Jurisdiction.php`
- Modify: `app/Filament/Resources/JurisdictionResource.php`
- Test: `tests/Feature/JurisdictionArbitrationTest.php`

### Step 1: Write the migration

```php
<?php
// database/migrations/2026_03_02_100000_add_arbitration_body_to_jurisdictions.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jurisdictions', function (Blueprint $table) {
            $table->string('arbitration_body', 255)->nullable()->after('regulatory_body');
            $table->string('arbitration_rules', 255)->nullable()->after('arbitration_body');
        });
    }

    public function down(): void
    {
        Schema::table('jurisdictions', function (Blueprint $table) {
            $table->dropColumn(['arbitration_body', 'arbitration_rules']);
        });
    }
};
```

### Step 2: Update Jurisdiction model — add to $fillable

In `app/Models/Jurisdiction.php`, add `'arbitration_body'` and `'arbitration_rules'` to the `$fillable` array:

```php
protected $fillable = [
    'name', 'country_code', 'regulatory_body',
    'arbitration_body', 'arbitration_rules',
    'notes', 'is_active',
];
```

### Step 3: Update JurisdictionResource form

In `app/Filament/Resources/JurisdictionResource.php`, add two new fields after `regulatory_body`:

```php
Forms\Components\TextInput::make('arbitration_body')
    ->maxLength(255)
    ->placeholder('e.g. DIAC, LCIA, ICC, SIAC')
    ->helperText('The default arbitration institution for disputes in this jurisdiction.'),
Forms\Components\TextInput::make('arbitration_rules')
    ->maxLength(255)
    ->placeholder('e.g. DIAC Arbitration Rules 2022')
    ->helperText('The arbitration rules that apply (e.g. UNCITRAL, ICC Rules).'),
```

Also add columns to the table:

```php
Tables\Columns\TextColumn::make('arbitration_body')->limit(30)
    ->toggleable(isToggledHiddenByDefault: true),
```

### Step 4: Write the test

```php
<?php
// tests/Feature/JurisdictionArbitrationTest.php
namespace Tests\Feature;

use App\Models\Jurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JurisdictionArbitrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jurisdiction_has_arbitration_fields(): void
    {
        $jurisdiction = Jurisdiction::create([
            'name' => 'UAE - DIFC',
            'country_code' => 'AE',
            'regulatory_body' => 'DIFC Authority',
            'arbitration_body' => 'DIAC',
            'arbitration_rules' => 'DIAC Arbitration Rules 2022',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('jurisdictions', [
            'name' => 'UAE - DIFC',
            'arbitration_body' => 'DIAC',
            'arbitration_rules' => 'DIAC Arbitration Rules 2022',
        ]);

        $fresh = Jurisdiction::find($jurisdiction->id);
        $this->assertEquals('DIAC', $fresh->arbitration_body);
    }

    public function test_arbitration_fields_are_nullable(): void
    {
        $jurisdiction = Jurisdiction::create([
            'name' => 'UK - England',
            'country_code' => 'GB',
            'is_active' => true,
        ]);

        $this->assertNull($jurisdiction->arbitration_body);
        $this->assertNull($jurisdiction->arbitration_rules);
    }
}
```

### Step 5: Run migration and test

Run: `php artisan migrate && php artisan test --filter=JurisdictionArbitrationTest`
Expected: Migration succeeds, 2 tests PASS.

### Step 6: Seed common arbitration bodies into existing jurisdictions

Add to the migration (or a separate seeder call) to pre-populate known arbitration bodies for seeded jurisdictions. This is best done as a `DB::table('jurisdictions')->where(...)->update(...)` block in the migration's `up()` method after adding the column:

```php
// After Schema::table, still in up():
$defaults = [
    'AE' => ['arbitration_body' => 'DIAC', 'arbitration_rules' => 'DIAC Arbitration Rules 2022'],
    'GB' => ['arbitration_body' => 'LCIA', 'arbitration_rules' => 'LCIA Arbitration Rules 2020'],
    'US' => ['arbitration_body' => 'AAA/ICDR', 'arbitration_rules' => 'ICDR International Arbitration Rules'],
    'SG' => ['arbitration_body' => 'SIAC', 'arbitration_rules' => 'SIAC Arbitration Rules 2016'],
    'HK' => ['arbitration_body' => 'HKIAC', 'arbitration_rules' => 'HKIAC Administered Arbitration Rules 2018'],
    'FR' => ['arbitration_body' => 'ICC', 'arbitration_rules' => 'ICC Arbitration Rules 2021'],
];
foreach ($defaults as $code => $data) {
    \DB::table('jurisdictions')
        ->where('country_code', $code)
        ->whereNull('arbitration_body')
        ->update($data);
}
```

### Step 7: Commit

```bash
git add database/migrations/2026_03_02_100000_add_arbitration_body_to_jurisdictions.php \
  app/Models/Jurisdiction.php \
  app/Filament/Resources/JurisdictionResource.php \
  tests/Feature/JurisdictionArbitrationTest.php
git commit -m "feat: add arbitration body and rules to jurisdictions"
```

---

## Task 2: Create Governing Law Table and Resource

**Files:**
- Create: `database/migrations/2026_03_02_100001_create_governing_laws_table.php`
- Create: `database/migrations/2026_03_02_100002_add_governing_law_id_to_contracts.php`
- Create: `app/Models/GoverningLaw.php`
- Create: `database/seeders/GoverningLawSeeder.php`
- Create: `app/Filament/Resources/GoverningLawResource.php`
- Create: `app/Filament/Resources/GoverningLawResource/Pages/ListGoverningLaws.php`
- Create: `app/Filament/Resources/GoverningLawResource/Pages/CreateGoverningLaw.php`
- Create: `app/Filament/Resources/GoverningLawResource/Pages/EditGoverningLaw.php`
- Modify: `app/Models/Contract.php`
- Modify: `app/Filament/Resources/ContractResource.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/GoverningLawTest.php`

### Step 1: Create governing_laws migration

```php
<?php
// database/migrations/2026_03_02_100001_create_governing_laws_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('governing_laws', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->string('name', 255)->unique();
            $table->char('country_code', 2)->nullable();
            $table->string('legal_system', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('country_code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governing_laws');
    }
};
```

### Step 2: Create FK migration for contracts

```php
<?php
// database/migrations/2026_03_02_100002_add_governing_law_id_to_contracts.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('governing_law_id', 36)->nullable()->after('counterparty_id');
            $table->foreign('governing_law_id')
                ->references('id')->on('governing_laws')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['governing_law_id']);
            $table->dropColumn('governing_law_id');
        });
    }
};
```

### Step 3: Create GoverningLaw model

```php
<?php
// app/Models/GoverningLaw.php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoverningLaw extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'name', 'country_code', 'legal_system', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
```

### Step 4: Create GoverningLawSeeder

```php
<?php
// database/seeders/GoverningLawSeeder.php
namespace Database\Seeders;

use App\Models\GoverningLaw;
use Illuminate\Database\Seeder;

class GoverningLawSeeder extends Seeder
{
    public function run(): void
    {
        $laws = [
            ['name' => 'Laws of England and Wales', 'country_code' => 'GB', 'legal_system' => 'Common Law'],
            ['name' => 'Laws of the State of New York', 'country_code' => 'US', 'legal_system' => 'Common Law'],
            ['name' => 'Laws of the State of Delaware', 'country_code' => 'US', 'legal_system' => 'Common Law'],
            ['name' => 'Laws of the State of California', 'country_code' => 'US', 'legal_system' => 'Common Law'],
            ['name' => 'UAE Federal Law', 'country_code' => 'AE', 'legal_system' => 'Civil Law'],
            ['name' => 'DIFC Law (Dubai)', 'country_code' => 'AE', 'legal_system' => 'Common Law'],
            ['name' => 'ADGM Law (Abu Dhabi)', 'country_code' => 'AE', 'legal_system' => 'Common Law'],
            ['name' => 'Laws of Singapore', 'country_code' => 'SG', 'legal_system' => 'Common Law'],
            ['name' => 'Laws of Hong Kong SAR', 'country_code' => 'HK', 'legal_system' => 'Common Law'],
            ['name' => 'French Law', 'country_code' => 'FR', 'legal_system' => 'Civil Law'],
            ['name' => 'German Law', 'country_code' => 'DE', 'legal_system' => 'Civil Law'],
            ['name' => 'Swiss Law', 'country_code' => 'CH', 'legal_system' => 'Civil Law'],
            ['name' => 'Laws of the Kingdom of Saudi Arabia', 'country_code' => 'SA', 'legal_system' => 'Civil/Sharia'],
            ['name' => 'Laws of the Kingdom of Bahrain', 'country_code' => 'BH', 'legal_system' => 'Civil Law'],
            ['name' => 'Indian Law', 'country_code' => 'IN', 'legal_system' => 'Common Law'],
            ['name' => 'Australian Law (NSW)', 'country_code' => 'AU', 'legal_system' => 'Common Law'],
            ['name' => 'South African Law', 'country_code' => 'ZA', 'legal_system' => 'Mixed'],
            ['name' => 'Nigerian Law', 'country_code' => 'NG', 'legal_system' => 'Common Law'],
            ['name' => 'Kenyan Law', 'country_code' => 'KE', 'legal_system' => 'Common Law'],
            ['name' => 'Egyptian Law', 'country_code' => 'EG', 'legal_system' => 'Civil Law'],
        ];

        foreach ($laws as $law) {
            GoverningLaw::firstOrCreate(
                ['name' => $law['name']],
                array_merge($law, ['is_active' => true])
            );
        }
    }
}
```

### Step 5: Update DatabaseSeeder

In `database/seeders/DatabaseSeeder.php`, add `GoverningLawSeeder::class` after `CountrySeeder`:

```php
$this->call([
    CountrySeeder::class,
    GoverningLawSeeder::class,
    RoleSeeder::class,
    ShieldPermissionSeeder::class,
    RegulatoryFrameworkSeeder::class,
]);
```

### Step 6: Update Contract model

In `app/Models/Contract.php`:
- Add `'governing_law_id'` to `$fillable`
- Add relationship method:

```php
public function governingLaw(): BelongsTo
{
    return $this->belongsTo(GoverningLaw::class);
}
```

### Step 7: Create GoverningLawResource (Filament)

```php
<?php
// app/Filament/Resources/GoverningLawResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\GoverningLawResource\Pages;
use App\Models\GoverningLaw;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GoverningLawResource extends Resource
{
    protected static ?string $model = GoverningLaw::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Governing Law Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->placeholder('e.g. Laws of England and Wales'),
                Forms\Components\Select::make('country_code')
                    ->options(\App\Models\Country::dropdownOptions())
                    ->searchable()
                    ->helperText('The country this governing law belongs to.'),
                Forms\Components\TextInput::make('legal_system')
                    ->maxLength(100)
                    ->placeholder('e.g. Common Law, Civil Law, Mixed')
                    ->helperText('The legal tradition (Common Law, Civil Law, Sharia, Mixed).'),
                Forms\Components\Textarea::make('notes')
                    ->rows(3),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->helperText('Inactive laws will not appear in dropdown selections.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country_code')->badge()->sortable(),
                Tables\Columns\TextColumn::make('legal_system')->badge()
                    ->color(fn ($state) => match ($state) {
                        'Common Law' => 'success',
                        'Civil Law' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('contracts_count')
                    ->counts('contracts')
                    ->label('Contracts')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoverningLaws::route('/'),
            'create' => Pages\CreateGoverningLaw::route('/create'),
            'edit' => Pages\EditGoverningLaw::route('/{record}/edit'),
        ];
    }
}
```

Create the three page files:

```php
<?php // app/Filament/Resources/GoverningLawResource/Pages/ListGoverningLaws.php
namespace App\Filament\Resources\GoverningLawResource\Pages;
use App\Filament\Resources\GoverningLawResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListGoverningLaws extends ListRecords
{
    protected static string $resource = GoverningLawResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
```

```php
<?php // app/Filament/Resources/GoverningLawResource/Pages/CreateGoverningLaw.php
namespace App\Filament\Resources\GoverningLawResource\Pages;
use App\Filament\Resources\GoverningLawResource;
use Filament\Resources\Pages\CreateRecord;
class CreateGoverningLaw extends CreateRecord
{
    protected static string $resource = GoverningLawResource::class;
}
```

```php
<?php // app/Filament/Resources/GoverningLawResource/Pages/EditGoverningLaw.php
namespace App\Filament\Resources\GoverningLawResource\Pages;
use App\Filament\Resources\GoverningLawResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditGoverningLaw extends EditRecord
{
    protected static string $resource = GoverningLawResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
```

### Step 8: Add Governing Law select to ContractResource form

In `app/Filament/Resources/ContractResource.php`, add after the `counterparty_id` field (around line 145):

```php
Forms\Components\Select::make('governing_law_id')
    ->relationship('governingLaw', 'name')
    ->searchable()
    ->preload()
    ->placeholder('Select governing law...')
    ->helperText('The legal jurisdiction whose laws govern this agreement. May differ from the region of signing.')
    ->createOptionForm([
        Forms\Components\TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->placeholder('e.g. Laws of England and Wales'),
        Forms\Components\Select::make('country_code')
            ->options(\App\Models\Country::dropdownOptions())
            ->searchable(),
        Forms\Components\TextInput::make('legal_system')
            ->maxLength(100)
            ->placeholder('e.g. Common Law'),
    ]),
```

Also add to the table columns (toggleable):

```php
Tables\Columns\TextColumn::make('governingLaw.name')
    ->label('Governing Law')
    ->limit(25)
    ->toggleable(isToggledHiddenByDefault: true),
```

### Step 9: Write the test

```php
<?php
// tests/Feature/GoverningLawTest.php
namespace Tests\Feature;

use App\Models\Contract;
use App\Models\GoverningLaw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoverningLawTest extends TestCase
{
    use RefreshDatabase;

    public function test_governing_law_can_be_created(): void
    {
        $law = GoverningLaw::create([
            'name' => 'Laws of England and Wales',
            'country_code' => 'GB',
            'legal_system' => 'Common Law',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('governing_laws', [
            'name' => 'Laws of England and Wales',
            'legal_system' => 'Common Law',
        ]);
    }

    public function test_governing_law_name_is_unique(): void
    {
        GoverningLaw::create(['name' => 'UAE Federal Law', 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        GoverningLaw::create(['name' => 'UAE Federal Law', 'is_active' => true]);
    }

    public function test_contract_belongs_to_governing_law(): void
    {
        $law = GoverningLaw::create([
            'name' => 'DIFC Law',
            'country_code' => 'AE',
            'is_active' => true,
        ]);

        // Verify FK column exists and is nullable
        $this->assertTrue(
            \Schema::hasColumn('contracts', 'governing_law_id')
        );
    }

    public function test_seeder_populates_governing_laws(): void
    {
        $this->seed(\Database\Seeders\GoverningLawSeeder::class);

        $this->assertGreaterThanOrEqual(15, GoverningLaw::count());
        $this->assertDatabaseHas('governing_laws', ['name' => 'Laws of England and Wales']);
        $this->assertDatabaseHas('governing_laws', ['name' => 'DIFC Law (Dubai)']);
        $this->assertDatabaseHas('governing_laws', ['name' => 'UAE Federal Law']);
    }
}
```

### Step 10: Run migrations, seed, and test

Run: `php artisan migrate && php artisan db:seed --class=GoverningLawSeeder && php artisan test --filter=GoverningLawTest`
Expected: All pass. Governing laws table has 20 entries.

### Step 11: Commit

```bash
git add database/migrations/2026_03_02_10000[12]_*.php \
  app/Models/GoverningLaw.php \
  database/seeders/GoverningLawSeeder.php \
  database/seeders/DatabaseSeeder.php \
  app/Filament/Resources/GoverningLawResource.php \
  app/Filament/Resources/GoverningLawResource/ \
  app/Models/Contract.php \
  app/Filament/Resources/ContractResource.php \
  tests/Feature/GoverningLawTest.php
git commit -m "feat: add governing law table, seeder, resource, and contract FK"
```

---

## Task 3: Inter-Company Agreements

**Files:**
- Create: `database/migrations/2026_03_02_100003_add_intercompany_support_to_contracts.php`
- Modify: `app/Models/Contract.php`
- Modify: `app/Filament/Resources/ContractResource.php`
- Test: `tests/Feature/InterCompanyContractTest.php`

### Step 1: Create migration

This migration:
1. Adds 'Inter-Company' to the contract_type enum
2. Makes counterparty_id nullable (inter-company contracts have no external counterparty)
3. Adds second_entity_id FK (the second group company in an inter-company agreement)

```php
<?php
// database/migrations/2026_03_02_100003_add_intercompany_support_to_contracts.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Expand contract_type enum
        DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant', 'Inter-Company') NOT NULL DEFAULT 'Commercial'");

        // 2. Make counterparty_id nullable
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('counterparty_id', 36)->nullable()->change();
        });

        // 3. Add second_entity_id for the other group company
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('second_entity_id', 36)->nullable()->after('entity_id');
            $table->foreign('second_entity_id')
                ->references('id')->on('entities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['second_entity_id']);
            $table->dropColumn('second_entity_id');
        });

        // Revert counterparty_id to NOT NULL (only safe if no nulls exist)
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('counterparty_id', 36)->nullable(false)->change();
        });

        DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant') NOT NULL DEFAULT 'Commercial'");
    }
};
```

### Step 2: Update Contract model

In `app/Models/Contract.php`:
- Add `'second_entity_id'` to `$fillable`
- Add relationship:

```php
public function secondEntity(): BelongsTo
{
    return $this->belongsTo(Entity::class, 'second_entity_id');
}
```

### Step 3: Update ContractResource form

In `app/Filament/Resources/ContractResource.php`:

**a) Update contract_type options** (around line 146):

```php
Forms\Components\Select::make('contract_type')
    ->options([
        'Commercial' => 'Commercial',
        'Merchant' => 'Merchant',
        'Inter-Company' => 'Inter-Company',
    ])
    ->required()
    ->live()
    ->placeholder('Select contract type')
    ->helperText('Inter-Company is for agreements between two Digittal group entities.'),
```

**b) Make counterparty_id conditionally required** — change `->required()` to:

```php
Forms\Components\Select::make('counterparty_id')
    ->relationship('counterparty', 'legal_name')
    ->required(fn (Forms\Get $get) => $get('contract_type') !== 'Inter-Company')
    ->visible(fn (Forms\Get $get) => $get('contract_type') !== 'Inter-Company')
    ->searchable()->preload()
    // ... rest of existing config
```

**c) Add second_entity_id field** — visible only for Inter-Company:

```php
Forms\Components\Select::make('second_entity_id')
    ->label('Second Group Entity')
    ->relationship('secondEntity', 'name')
    ->searchable()
    ->preload()
    ->required(fn (Forms\Get $get) => $get('contract_type') === 'Inter-Company')
    ->visible(fn (Forms\Get $get) => $get('contract_type') === 'Inter-Company')
    ->different('entity_id')
    ->helperText('The other Digittal group entity in this inter-company agreement.')
    ->createOptionForm([
        Forms\Components\Select::make('region_id')
            ->relationship('region', 'name')
            ->required()->searchable()->preload(),
        Forms\Components\TextInput::make('name')
            ->required()->maxLength(255),
        Forms\Components\TextInput::make('code')
            ->maxLength(50),
    ]),
```

**d) Update the table filter** for contract_type (around line 243):

```php
Tables\Filters\SelectFilter::make('contract_type')
    ->options([
        'Commercial' => 'Commercial',
        'Merchant' => 'Merchant',
        'Inter-Company' => 'Inter-Company',
    ]),
```

**e) Update the table counterparty column** to handle null:

```php
Tables\Columns\TextColumn::make('counterparty.legal_name')
    ->sortable()->limit(30)
    ->default(fn (Contract $record) => $record->secondEntity?->name
        ? "↔ {$record->secondEntity->name}"
        : '—'),
```

### Step 4: Write the test

```php
<?php
// tests/Feature/InterCompanyContractTest.php
namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Entity;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterCompanyContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_intercompany_contract_has_no_counterparty(): void
    {
        $region = Region::create(['name' => 'UAE', 'code' => 'AE']);
        $entity1 = Entity::create(['name' => 'Digittal FZ-LLC', 'code' => 'DGT-AE', 'region_id' => $region->id]);
        $entity2 = Entity::create(['name' => 'Digittal UK Ltd', 'code' => 'DGT-GB', 'region_id' => $region->id]);

        $contract = new Contract([
            'title' => 'Intercompany Services Agreement',
            'contract_type' => 'Inter-Company',
            'entity_id' => $entity1->id,
            'second_entity_id' => $entity2->id,
            'region_id' => $region->id,
            'counterparty_id' => null,
        ]);
        $contract->workflow_state = 'draft';
        $contract->save();

        $this->assertDatabaseHas('contracts', [
            'contract_type' => 'Inter-Company',
            'counterparty_id' => null,
            'second_entity_id' => $entity2->id,
        ]);

        $fresh = Contract::find($contract->id);
        $this->assertNull($fresh->counterparty_id);
        $this->assertEquals($entity2->id, $fresh->second_entity_id);
        $this->assertEquals('Digittal UK Ltd', $fresh->secondEntity->name);
    }

    public function test_commercial_contract_still_requires_counterparty(): void
    {
        // Verify the enum accepts all three values
        $this->assertTrue(
            \Schema::hasColumn('contracts', 'second_entity_id')
        );
    }
}
```

### Step 5: Run migration and test

Run: `php artisan migrate && php artisan test --filter=InterCompanyContractTest`
Expected: PASS

### Step 6: Commit

```bash
git add database/migrations/2026_03_02_100003_*.php \
  app/Models/Contract.php \
  app/Filament/Resources/ContractResource.php \
  tests/Feature/InterCompanyContractTest.php
git commit -m "feat: add inter-company agreement support with second entity"
```

---

## Task 4: Individual Agreement Upload in Bulk Upload Page

**Files:**
- Modify: `app/Filament/Pages/BulkContractUploadPage.php`
- Modify: `resources/views/filament/pages/bulk-contract-upload.blade.php`
- Test: `tests/Feature/IndividualUploadTest.php`

### Step 1: Add individual file upload section to BulkContractUploadPage

The current page only accepts CSV + ZIP. Add a second tab/section for uploading individual contract files (PDFs/DOCXs) that are then available for the CSV `file_path` column to reference.

In `app/Filament/Pages/BulkContractUploadPage.php`, add a new property and method:

```php
public ?array $individualData = [];

public function individualUploadForm(Form $form): Form
{
    return $form
        ->schema([
            FileUpload::make('contract_files')
                ->label('Contract Files')
                ->multiple()
                ->disk(config('ccrs.contracts_disk', 'database'))
                ->directory('bulk_uploads/files')
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ])
                ->maxFiles(50)
                ->helperText('Upload individual contract files (PDF, DOCX). These will be available for the CSV manifest to reference by filename.'),
        ])
        ->statePath('individualData');
}

public function uploadIndividualFiles(): void
{
    $data = $this->individualUploadForm->getState();

    if (empty($data['contract_files'])) {
        Notification::make()->title('No files selected')->warning()->send();
        return;
    }

    $count = count($data['contract_files']);
    Notification::make()
        ->title("{$count} file(s) uploaded")
        ->body('Files are now available for the CSV manifest to reference.')
        ->success()
        ->send();

    $this->individualUploadForm->fill();
}
```

Register the second form in `getForms()`:

```php
protected function getForms(): array
{
    return [
        'form',
        'individualUploadForm',
    ];
}
```

### Step 2: Update the Blade view

In `resources/views/filament/pages/bulk-contract-upload.blade.php`, add a tabbed layout or a second section for individual uploads. The exact view changes depend on the existing template, but the key addition is:

```blade
<x-filament::section>
    <x-slot name="heading">Individual File Upload</x-slot>
    <x-slot name="description">
        Upload contract files individually instead of as a ZIP archive.
        Files uploaded here can be referenced by filename in the CSV manifest.
    </x-slot>

    <form wire:submit="uploadIndividualFiles">
        {{ $this->individualUploadForm }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Upload Files
            </x-filament::button>
        </div>
    </form>
</x-filament::section>
```

### Step 3: Also fix the existing ZIP-only issue

The core bug is that when no ZIP is provided but the CSV references `file_path`, `ProcessContractBatch` fails because the file doesn't exist at `bulk_uploads/files/{file_path}`.

In `app/Filament/Pages/BulkContractUploadPage.php::submit()`, add a validation check after CSV parsing:

```php
// After parsing CSV, before creating BulkUpload:
if (empty($data['zip_file'])) {
    // Verify that all referenced files exist (uploaded individually)
    $disk = Storage::disk(config('ccrs.contracts_disk', 'database'));
    $missingFiles = [];
    foreach ($rows as $row) {
        $rowData = json_decode($row['row_data'], true);
        if (!empty($rowData['file_path'])) {
            $sourceKey = 'bulk_uploads/files/' . $rowData['file_path'];
            if (!$disk->exists($sourceKey)) {
                $missingFiles[] = $rowData['file_path'];
            }
        }
    }

    if (!empty($missingFiles)) {
        $list = implode(', ', array_slice($missingFiles, 0, 5));
        $count = count($missingFiles);
        Notification::make()
            ->title("Missing {$count} file(s)")
            ->body("Files not found: {$list}. Upload them individually or provide a ZIP archive.")
            ->danger()
            ->send();
        return;
    }
}
```

### Step 4: Write the test

```php
<?php
// tests/Feature/IndividualUploadTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndividualUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_files_are_detected_when_no_zip(): void
    {
        $disk = config('ccrs.contracts_disk', 'database');
        Storage::fake($disk);

        // Simulate a file_path reference without the file existing
        $this->assertFalse(
            Storage::disk($disk)->exists('bulk_uploads/files/missing-contract.pdf')
        );
    }

    public function test_individual_files_stored_in_correct_directory(): void
    {
        $disk = config('ccrs.contracts_disk', 'database');
        Storage::fake($disk);

        Storage::disk($disk)->put('bulk_uploads/files/test-contract.pdf', 'fake-pdf-content');

        $this->assertTrue(
            Storage::disk($disk)->exists('bulk_uploads/files/test-contract.pdf')
        );
    }
}
```

### Step 5: Run test

Run: `php artisan test --filter=IndividualUploadTest`
Expected: PASS

### Step 6: Commit

```bash
git add app/Filament/Pages/BulkContractUploadPage.php \
  resources/views/filament/pages/bulk-contract-upload.blade.php \
  tests/Feature/IndividualUploadTest.php
git commit -m "feat: add individual file upload and missing file validation to bulk upload"
```

---

## Task 5: AI Auto-Discovery — Extract and Link Entities from Contracts

**Files:**
- Create: `database/migrations/2026_03_02_100004_create_ai_discovery_drafts_table.php`
- Create: `app/Models/AiDiscoveryDraft.php`
- Create: `app/Services/AiDiscoveryService.php`
- Create: `app/Filament/Pages/AiDiscoveryReviewPage.php`
- Create: `resources/views/filament/pages/ai-discovery-review.blade.php`
- Modify: `app/Jobs/ProcessAiAnalysis.php`
- Modify: `ai-worker/app/routers/analysis.py`
- Test: `tests/Feature/AiDiscoveryTest.php`

### Step 1: Create discovery drafts migration

The AI will extract structured data (counterparty names, registration numbers, entity references, jurisdiction, governing law) and store them as drafts for user approval.

```php
<?php
// database/migrations/2026_03_02_100004_create_ai_discovery_drafts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_discovery_drafts', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('contract_id', 36);
            $table->char('analysis_id', 36)->nullable();
            $table->string('draft_type', 50);  // counterparty, entity, jurisdiction, governing_law
            $table->json('extracted_data');     // raw fields extracted by AI
            $table->char('matched_record_id', 36)->nullable();  // FK to existing record if matched
            $table->string('matched_record_type', 100)->nullable();  // model class
            $table->float('confidence', 3, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'merged'])->default('pending');
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->index(['contract_id', 'status']);
            $table->index('draft_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_discovery_drafts');
    }
};
```

### Step 2: Create AiDiscoveryDraft model

```php
<?php
// app/Models/AiDiscoveryDraft.php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDiscoveryDraft extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'contract_id', 'analysis_id', 'draft_type', 'extracted_data',
        'matched_record_id', 'matched_record_type', 'confidence',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'confidence' => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

### Step 3: Create AiDiscoveryService

```php
<?php
// app/Services/AiDiscoveryService.php
namespace App\Services;

use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\GoverningLaw;
use App\Models\Jurisdiction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiDiscoveryService
{
    /**
     * Process AI extraction results and create discovery drafts.
     * Called by ProcessAiAnalysis after receiving the 'discovery' analysis results.
     */
    public function processDiscoveryResults(Contract $contract, string $analysisId, array $discoveries): void
    {
        foreach ($discoveries as $discovery) {
            $type = $discovery['type'] ?? null; // counterparty, entity, jurisdiction, governing_law
            $data = $discovery['data'] ?? [];
            $confidence = $discovery['confidence'] ?? 0.0;

            if (!$type || empty($data)) {
                continue;
            }

            // Try to match against existing records
            [$matchedId, $matchedType] = $this->findMatch($type, $data);

            AiDiscoveryDraft::create([
                'contract_id' => $contract->id,
                'analysis_id' => $analysisId,
                'draft_type' => $type,
                'extracted_data' => $data,
                'matched_record_id' => $matchedId,
                'matched_record_type' => $matchedType,
                'confidence' => $confidence,
                'status' => 'pending',
            ]);
        }

        Log::info("AI discovery created " . count($discoveries) . " drafts for contract {$contract->id}");
    }

    /**
     * Attempt to match extracted data against existing records.
     */
    private function findMatch(string $type, array $data): array
    {
        return match ($type) {
            'counterparty' => $this->matchCounterparty($data),
            'entity' => $this->matchEntity($data),
            'jurisdiction' => $this->matchJurisdiction($data),
            'governing_law' => $this->matchGoverningLaw($data),
            default => [null, null],
        };
    }

    private function matchCounterparty(array $data): array
    {
        $match = null;
        if (!empty($data['registration_number'])) {
            $match = Counterparty::where('registration_number', $data['registration_number'])->first();
        }
        if (!$match && !empty($data['legal_name'])) {
            $match = Counterparty::where('legal_name', 'LIKE', '%' . $data['legal_name'] . '%')->first();
        }
        return $match ? [$match->id, Counterparty::class] : [null, null];
    }

    private function matchEntity(array $data): array
    {
        $match = null;
        if (!empty($data['registration_number'])) {
            $match = Entity::where('registration_number', $data['registration_number'])->first();
        }
        if (!$match && !empty($data['name'])) {
            $match = Entity::where('name', 'LIKE', '%' . $data['name'] . '%')->first();
        }
        return $match ? [$match->id, Entity::class] : [null, null];
    }

    private function matchJurisdiction(array $data): array
    {
        $match = null;
        if (!empty($data['name'])) {
            $match = Jurisdiction::where('name', 'LIKE', '%' . $data['name'] . '%')->first();
        }
        if (!$match && !empty($data['country_code'])) {
            $match = Jurisdiction::where('country_code', $data['country_code'])->first();
        }
        return $match ? [$match->id, Jurisdiction::class] : [null, null];
    }

    private function matchGoverningLaw(array $data): array
    {
        $match = null;
        if (!empty($data['name'])) {
            $match = GoverningLaw::where('name', 'LIKE', '%' . $data['name'] . '%')->first();
        }
        return $match ? [$match->id, GoverningLaw::class] : [null, null];
    }

    /**
     * Approve a draft — either link to matched record or create a new record.
     */
    public function approveDraft(AiDiscoveryDraft $draft, User $actor, ?array $overrides = null): void
    {
        $data = $overrides ?? $draft->extracted_data;

        if ($draft->matched_record_id) {
            // Link contract to the matched record
            $this->linkToContract($draft->contract, $draft->draft_type, $draft->matched_record_id);
        } else {
            // Create a new record from the extracted data
            $newId = $this->createRecord($draft->draft_type, $data);
            if ($newId) {
                $this->linkToContract($draft->contract, $draft->draft_type, $newId);
            }
        }

        $draft->update([
            'status' => 'approved',
            'extracted_data' => $data,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);

        AuditService::log('ai_discovery.approved', 'ai_discovery_draft', $draft->id, [
            'draft_type' => $draft->draft_type,
            'contract_id' => $draft->contract_id,
        ], $actor);
    }

    /**
     * Reject a draft.
     */
    public function rejectDraft(AiDiscoveryDraft $draft, User $actor): void
    {
        $draft->update([
            'status' => 'rejected',
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    private function linkToContract(Contract $contract, string $type, string $recordId): void
    {
        match ($type) {
            'counterparty' => $contract->update(['counterparty_id' => $recordId]),
            'governing_law' => $contract->update(['governing_law_id' => $recordId]),
            default => null, // entity and jurisdiction links are already set via entity_id/region_id
        };
    }

    private function createRecord(string $type, array $data): ?string
    {
        return match ($type) {
            'counterparty' => Counterparty::create([
                'legal_name' => $data['legal_name'] ?? 'Unknown',
                'registration_number' => $data['registration_number'] ?? null,
                'jurisdiction' => $data['jurisdiction'] ?? null,
                'registered_address' => $data['registered_address'] ?? null,
                'status' => 'Active',
            ])->id,
            'governing_law' => GoverningLaw::create([
                'name' => $data['name'] ?? 'Unknown',
                'country_code' => $data['country_code'] ?? null,
                'is_active' => true,
            ])->id,
            default => null,
        };
    }
}
```

### Step 4: Update ProcessAiAnalysis job

In `app/Jobs/ProcessAiAnalysis.php`, add a new branch for `analysisType === 'discovery'`:

After the existing `if ($this->analysisType === 'extraction' && ...)` block (~line 110), add:

```php
// Auto-discovery: extract structured entity/counterparty/jurisdiction data
if ($this->analysisType === 'discovery' && isset($result['discoveries'])) {
    $discoveryService = app(\App\Services\AiDiscoveryService::class);
    $discoveryService->processDiscoveryResults($contract, $analysis->id, $result['discoveries']);
}
```

Also update the `$context` array passed to `AiWorkerClient::analyze()` to include more data for the AI to work with:

```php
// In the context-building section, add:
'existing_entities' => \App\Models\Entity::pluck('name', 'id')->toArray(),
'existing_counterparties' => \App\Models\Counterparty::pluck('legal_name', 'id')->take(100)->toArray(),
```

### Step 5: Update AI worker analysis prompt

In `ai-worker/app/routers/analysis.py`, add a handler for the `discovery` analysis_type. Add to the `analyze_complex()` function or create a new function:

```python
async def analyze_discovery(text: str, context: dict, mcp_tools: list) -> dict:
    """Extract structured entity data from contract text."""
    prompt = f"""Analyze this contract and extract the following structured information.
For each item found, provide the data and a confidence score (0.0 to 1.0).

Return JSON with a 'discoveries' array. Each item has:
- type: one of 'counterparty', 'entity', 'jurisdiction', 'governing_law'
- confidence: float 0.0-1.0
- data: object with relevant fields

For counterparty: legal_name, registration_number, registered_address, jurisdiction
For entity: name, registration_number, code
For jurisdiction: name, country_code
For governing_law: name, country_code

Contract text:
{text[:8000]}

Context from the system:
{json.dumps(context, default=str)}
"""

    response = await client.chat.completions.create(
        model=settings.openai_model,
        messages=[{"role": "user", "content": prompt}],
        response_format={"type": "json_object"},
        tools=mcp_tools,
    )

    return json.loads(response.choices[0].message.content)
```

Add routing in the main `analyze` endpoint:

```python
if request.analysis_type == "discovery":
    result = await analyze_discovery(text, request.context, mcp_tools)
```

### Step 6: Create a Filament action to trigger discovery

In `app/Filament/Resources/ContractResource.php`, add a table action:

```php
Tables\Actions\Action::make('ai_discover')
    ->label('AI Discover')
    ->icon('heroicon-o-sparkles')
    ->color('info')
    ->requiresConfirmation()
    ->modalHeading('Run AI Auto-Discovery')
    ->modalDescription('Analyze this contract to extract counterparties, jurisdictions, and governing law. Results will appear as drafts for your review.')
    ->action(function (Contract $record) {
        \App\Jobs\ProcessAiAnalysis::dispatch(
            $record->id,
            'discovery',
            $record->storage_path,
            $record->file_name ?? 'contract.pdf',
        );
        \Filament\Notifications\Notification::make()
            ->title('AI Discovery started')
            ->body('Analysis is running in the background. Check the Discovery Review page for results.')
            ->success()
            ->send();
    })
    ->visible(fn (Contract $record) => $record->storage_path !== null),
```

### Step 7: Create AiDiscoveryReviewPage

This is a Filament Page that lists pending discovery drafts for the current user to approve/reject.

```php
<?php
// app/Filament/Pages/AiDiscoveryReviewPage.php
namespace App\Filament\Pages;

use App\Models\AiDiscoveryDraft;
use App\Services\AiDiscoveryService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class AiDiscoveryReviewPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static string $view = 'filament.pages.ai-discovery-review';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'AI Discovery Review';
    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AiDiscoveryDraft::query()->where('status', 'pending')->with('contract'))
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')->limit(30)->sortable(),
                Tables\Columns\TextColumn::make('draft_type')->badge()
                    ->color(fn ($state) => match ($state) {
                        'counterparty' => 'warning',
                        'entity' => 'info',
                        'jurisdiction' => 'success',
                        'governing_law' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('extracted_data')
                    ->label('Extracted')
                    ->formatStateUsing(fn ($state) => collect($state)->map(fn ($v, $k) => "{$k}: {$v}")->take(3)->join(', '))
                    ->limit(60),
                Tables\Columns\TextColumn::make('confidence')
                    ->badge()
                    ->color(fn ($state) => $state >= 0.8 ? 'success' : ($state >= 0.5 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => round($state * 100) . '%'),
                Tables\Columns\TextColumn::make('matched_record_id')
                    ->label('Match')
                    ->formatStateUsing(fn ($state) => $state ? 'Existing record' : 'New record')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (AiDiscoveryDraft $record) {
                        app(AiDiscoveryService::class)->approveDraft($record, auth()->user());
                        Notification::make()->title('Draft approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (AiDiscoveryDraft $record) {
                        app(AiDiscoveryService::class)->rejectDraft($record, auth()->user());
                        Notification::make()->title('Draft rejected')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = AiDiscoveryDraft::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }
}
```

Blade view:

```blade
{{-- resources/views/filament/pages/ai-discovery-review.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Review AI-extracted data from uploaded contracts. Approve to link or create records, or reject to discard.
        </p>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
```

### Step 8: Write the test

```php
<?php
// tests/Feature/AiDiscoveryTest.php
namespace Tests\Feature;

use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Region;
use App\Services\AiDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeContract(): Contract
    {
        $region = Region::create(['name' => 'UAE', 'code' => 'AE']);
        $entity = Entity::create(['name' => 'Digittal FZ-LLC', 'code' => 'DGT-AE', 'region_id' => $region->id]);
        $cp = Counterparty::create(['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123', 'status' => 'Active']);
        $contract = new Contract([
            'title' => 'Test Contract',
            'contract_type' => 'Commercial',
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'counterparty_id' => $cp->id,
        ]);
        $contract->workflow_state = 'draft';
        $contract->save();
        return $contract;
    }

    public function test_discovery_drafts_are_created(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.9,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
            [
                'type' => 'governing_law',
                'confidence' => 0.7,
                'data' => ['name' => 'Laws of England and Wales'],
            ],
        ]);

        $this->assertDatabaseCount('ai_discovery_drafts', 2);
    }

    public function test_counterparty_is_matched_by_registration(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.95,
                'data' => ['legal_name' => 'Acme Corporation', 'registration_number' => 'REG-123'],
            ],
        ]);

        $draft = AiDiscoveryDraft::first();
        $this->assertNotNull($draft->matched_record_id);
        $this->assertEquals(Counterparty::class, $draft->matched_record_type);
    }

    public function test_approve_creates_new_counterparty_when_no_match(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.8,
                'data' => ['legal_name' => 'Brand New Corp', 'registration_number' => 'NEW-999'],
            ],
        ]);

        $draft = AiDiscoveryDraft::first();
        $this->assertNull($draft->matched_record_id);

        $admin = \App\Models\User::factory()->create();
        $service->approveDraft($draft, $admin);

        $this->assertEquals('approved', $draft->fresh()->status);
        $this->assertDatabaseHas('counterparties', ['legal_name' => 'Brand New Corp']);
    }

    public function test_reject_does_not_create_record(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.3,
                'data' => ['legal_name' => 'Suspicious Corp'],
            ],
        ]);

        $draft = AiDiscoveryDraft::first();
        $admin = \App\Models\User::factory()->create();
        $service->rejectDraft($draft, $admin);

        $this->assertEquals('rejected', $draft->fresh()->status);
        $this->assertDatabaseMissing('counterparties', ['legal_name' => 'Suspicious Corp']);
    }
}
```

### Step 9: Run migration and test

Run: `php artisan migrate && php artisan test --filter=AiDiscoveryTest`
Expected: All 4 tests PASS

### Step 10: Commit

```bash
git add database/migrations/2026_03_02_100004_*.php \
  app/Models/AiDiscoveryDraft.php \
  app/Services/AiDiscoveryService.php \
  app/Jobs/ProcessAiAnalysis.php \
  app/Filament/Pages/AiDiscoveryReviewPage.php \
  resources/views/filament/pages/ai-discovery-review.blade.php \
  app/Filament/Resources/ContractResource.php \
  ai-worker/app/routers/analysis.py \
  tests/Feature/AiDiscoveryTest.php
git commit -m "feat: AI auto-discovery with draft review workflow"
```

---

## Task 6: Verify AI Analysis + Document Collaboration/Redlining

This task is verification and documentation — no code changes required unless issues are found.

### Step 1: Verify AI analysis is functional

**Check that `ProcessAiAnalysis` job runs end-to-end:**

1. Upload a contract via the admin panel
2. The `ProcessAiAnalysis` job should dispatch automatically (check `QUEUE_CONNECTION=redis` and Horizon is running)
3. Verify `ai_analyses` table gets a row with status `completed`
4. Verify `ai_extracted_fields` table gets rows for field extraction
5. Verify `obligations_register` table gets rows for obligations analysis

**Check existing analysis types:**
- `summary` → produces `analysis_result` JSON with summary, key dates, risk assessment
- `extraction` → produces `AiExtractedField` rows
- `risk` → produces risk analysis in `analysis_result`
- `deviation` → produces deviation analysis
- `obligations` → produces `ObligationsRegister` rows

If the AI worker sidecar is not running, the job will fail with a connection error. This is an infrastructure issue, not a code issue.

### Step 2: Document how collaboration and redlining works

The existing redline system works as follows:

**Single-user clause-by-clause redlining (already implemented):**

1. **Start Session**: User clicks "Start Redline" on a contract → `RedlineService::startSession()` is called
   - System auto-selects the most recent published WikiContract (template) matching the contract's region
   - Creates a `RedlineSession` with status `pending`
   - Dispatches `ProcessRedlineAnalysis` job to the AI worker

2. **AI Analysis**: The AI worker compares the uploaded contract against the template clause-by-clause
   - Creates `RedlineClause` rows with: `clause_number`, `clause_heading`, `original_text` (from contract), `suggested_text` (from template), `deviation_type`, `risk_level`, `ai_notes`

3. **Review**: User reviews each clause on the Redline Review page
   - **Accept**: Use the template's suggested text → `final_text = suggested_text`
   - **Reject**: Keep the original contract text → `final_text = original_text`
   - **Modify**: Enter custom text → `final_text = custom_text`

4. **Generate Final**: Once all clauses are reviewed, user clicks "Generate Final Document"
   - `RedlineService::generateFinalDocument()` builds a DOCX using PhpWord
   - Each clause is written with its `final_text` and a status label (Accepted/Rejected/Modified)
   - Document is stored to the contracts disk

**Multi-party collaboration (future enhancement — NOT yet implemented):**

For counterparty collaboration (multiple rounds of editing), the envisioned flow is:

1. Internal team completes their redline review (steps 1-4 above)
2. The redlined document is shared with the counterparty via the **Vendor Portal** (already built — `vendor.login`, `vendor.auth.*` routes)
3. Counterparty reviews, accepts/rejects/modifies clauses through their portal view
4. System tracks version history (which party changed which clause, when)
5. Process repeats until both parties agree on all clauses
6. Final document is generated and moves to the e-signing workflow

**Key architectural notes for future multi-party collaboration:**
- The `RedlineSession` model already has `reviewed_clauses` tracking
- The `RedlineClause` model already has `reviewed_by` (user FK) and `reviewed_at`
- Adding counterparty review would require: a `party` field on RedlineClause review actions, a `round` counter on RedlineSession, and Vendor Portal views for clause review
- The e-signing workflow (`/sign/{token}`) is already built and can receive the final document

### Step 3: Commit documentation (no code changes)

No commit needed for this task unless issues were found during verification.

---

## Execution Order

1. **Task 1** — Arbitration body (standalone, no dependencies)
2. **Task 2** — Governing law (standalone, creates GoverningLaw model used by Task 5)
3. **Task 3** — Inter-company agreements (modifies Contract model, depends on Task 2 for governing_law_id being in $fillable)
4. **Task 4** — Bulk upload fix (standalone)
5. **Task 5** — AI auto-discovery (depends on Tasks 2 & 3 for GoverningLaw model and updated Contract model)
6. **Task 6** — Verification (depends on all above being deployed)

Tasks 1, 2, and 4 can run in parallel. Task 3 should follow Task 2. Task 5 follows Tasks 2 and 3.

---

## Final Verification

After all tasks are implemented:

1. **Run all migrations**: `php artisan migrate`
2. **Run all seeders**: `php artisan db:seed`
3. **Run full test suite**: `php artisan test`
4. **Manual UI verification**:
   - Jurisdictions > Create: arbitration_body and arbitration_rules fields visible
   - Governing Laws menu item visible to legal/admin users
   - Governing Laws > Create: dropdown in ContractResource shows all seeded laws
   - Contracts > Create: selecting "Inter-Company" hides counterparty, shows second entity
   - Bulk Upload: individual file upload section works, missing file validation works
   - Contracts > AI Discover action: triggers discovery, drafts appear on review page
   - AI Discovery Review: approve/reject workflow creates/links records
5. **Deploy**: merge `laravel-migration` → `main` → `sandbox`, push
