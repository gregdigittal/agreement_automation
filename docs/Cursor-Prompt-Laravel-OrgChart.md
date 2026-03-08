# Feature: Interactive Organisational Structure Visualisation

## Overview

Build a full-page Filament page that renders an interactive, visual organisation chart showing the corporate group structure. Entities are displayed as nodes in a hierarchical tree. Connectors between parent and child entities are editable and represent **shareholding relationships** (ownership percentages). Each entity node shows its **region as a label**. Clicking an entity opens a **searchable, filterable popup** listing all contracts linked to that entity.

---

## Context: Existing Data Model

Read these files before starting — they define the current schema:

| What | Where |
|------|-------|
| Entity model | `app/Models/Entity.php` |
| Region model | `app/Models/Region.php` |
| Contract model | `app/Models/Contract.php` |
| Entity migration | `database/migrations/2026_02_20_000002_create_entities_table.php` |
| Entity legal fields migration | `database/migrations/2026_02_25_000003_add_legal_fields_to_entities_table.php` |
| Inter-company support migration | `database/migrations/2026_03_02_100003_add_intercompany_support_to_contracts.php` |
| Entity Filament resource | `app/Filament/Resources/EntityResource.php` |
| Contract Filament resource | `app/Filament/Resources/ContractResource.php` |

### Key Relationships Already in Place

- `Entity` has `parent_entity_id` (self-referencing FK) with `parent()` and `children()` relationships
- `Entity` belongs to `Region` via `region_id`
- `Contract` belongs to `Entity` via `entity_id` (primary signatory)
- `Contract` optionally belongs to a second `Entity` via `second_entity_id` (for Inter-Company contracts)
- `Entity` has many `Project`s; `Contract` belongs to `Project`
- `Entity` has many-to-many with `Jurisdiction` via `entity_jurisdictions` pivot (with `license_number`, `license_expiry`, `is_primary`)

### What Does NOT Exist Yet

There is **no shareholding/ownership model**. The parent-child hierarchy exists but has no percentage ownership data. You must create this.

---

## Part 1: New Data Model — Entity Shareholdings

### 1.1 Create Migration

Create a new migration: `create_entity_shareholdings_table`

```
entity_shareholdings
├── id                  UUID, primary key
├── owner_entity_id     UUID, FK → entities(id) CASCADE on delete, NOT NULL
├── owned_entity_id     UUID, FK → entities(id) CASCADE on delete, NOT NULL
├── percentage          DECIMAL(5,2) NOT NULL  — e.g. 100.00, 51.50, 33.33
├── ownership_type      ENUM('direct', 'indirect', 'beneficial') DEFAULT 'direct'
├── effective_date      DATE, nullable
├── notes               TEXT, nullable
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
├── UNIQUE(owner_entity_id, owned_entity_id, ownership_type)
└── INDEX(owned_entity_id)
```

**Validation rules:**
- `percentage` must be between 0.01 and 100.00
- `owner_entity_id` must differ from `owned_entity_id` (no self-ownership)
- The sum of all `direct` shareholdings in a single `owned_entity_id` must not exceed 100%

### 1.2 Create Model

Create `app/Models/EntityShareholding.php`:
- Fillable: all fields except id/timestamps
- Relationships:
  - `owner()` → BelongsTo(Entity, 'owner_entity_id')
  - `owned()` → BelongsTo(Entity, 'owned_entity_id')
- Cast `percentage` to `decimal:2`, `effective_date` to `date`

### 1.3 Add Relationships to Entity Model

Add to `app/Models/Entity.php`:

```php
// Entities this entity owns shares in
public function shareholdingsOwned(): HasMany
{
    return $this->hasMany(EntityShareholding::class, 'owner_entity_id');
}

// Entities that own shares in this entity
public function shareholdingsHeld(): HasMany
{
    return $this->hasMany(EntityShareholding::class, 'owned_entity_id');
}
```

---

## Part 2: Org Chart Visualisation Page

### 2.1 Create Filament Page

Create a new Filament page at `app/Filament/Pages/OrganisationStructurePage.php`:
- Route: `/admin/organisation-structure`
- Navigation group: same group as Entity resource
- Navigation icon: `heroicon-o-building-office-2`
- Navigation label: "Organisation Structure"
- Restrict access to `system_admin` and `legal` roles (same pattern as EntityResource)

### 2.2 Visual Requirements

**Layout:** A full-width, full-height interactive canvas showing the entity hierarchy as an org chart / tree diagram.

**Technology choice — use one of these approaches (in order of preference):**

1. **Recommended: Livewire + Alpine.js + D3.js** — Render a Blade view with a D3.js tree layout. Use Alpine.js for interactivity (click handlers, modals). Use Livewire for data loading and saving edits.
2. **Alternative: Livewire + Alpine.js + dagre/dagre-d3** — If D3 is too heavy, dagre provides automatic tree layout with simpler API.
3. **Fallback: Filament custom widget with vanilla JS** — A custom Filament widget that renders the chart via a Blade view with embedded JS.

Do NOT use heavy commercial libraries (GoJS, yFiles). Stick to open-source.

### 2.3 Entity Nodes

Each entity node in the chart must display:

```
┌─────────────────────────────────┐
│  [Region Badge]                 │  ← Region name as a coloured label/badge
│                                 │     (colour-coded per region for visual grouping)
│  Entity Name                    │  ← Primary display name (bold)
│  ABC-XX                         │  ← Entity code (muted)
│                                 │
│  📄 12 Contracts  👥 3 Projects │  ← Summary counts
│                                 │
│  [View Details]                 │  ← Clickable — opens the popup (Part 3)
└─────────────────────────────────┘
```

**Node styling:**
- Rounded rectangle with subtle shadow
- Region badge uses a consistent colour per region (derive from region code or assign palette)
- Hover state: slight elevation increase
- The node should be draggable for manual layout adjustment (optional, nice-to-have)

### 2.4 Connectors (Editable Shareholding Lines)

The lines connecting parent → child entities must:

1. **Display the shareholding percentage** as a label on the connector (e.g., "100%", "51.5%")
2. **Display the ownership type** as a smaller sub-label (e.g., "direct", "beneficial")
3. **Be colour-coded by percentage range:**
   - 100% → solid dark line (full ownership)
   - 50.01%–99.99% → solid medium line (majority)
   - 25.01%–50% → dashed line (significant minority)
   - 0.01%–25% → dotted light line (minority)
4. **Be clickable/editable:**
   - Clicking a connector opens an inline popover or small modal to edit:
     - Percentage (number input, 0.01–100.00)
     - Ownership type (select: direct, indirect, beneficial)
     - Effective date (date picker)
     - Notes (textarea)
   - Save triggers a Livewire call to persist the `EntityShareholding` record
   - New connectors: if a parent-child relationship exists in `parent_entity_id` but no shareholding record exists, show a greyed-out "Add Shareholding" prompt on the connector
5. **Handle entities with no `parent_entity_id`:** Top-level entities (no parent) appear at the root of the tree. If shareholding data exists between entities that are NOT in a parent-child hierarchy, show them as a separate "Shareholding Links" overlay (dashed cross-links).

### 2.5 Tree Layout

- Orientation: **top-down** (root at top, children below) — with a toggle to switch to left-to-right
- Auto-layout: entities are positioned automatically based on hierarchy depth
- Zoom & pan: mouse wheel to zoom, click-drag on background to pan
- Fit-to-screen button to reset the view
- Collapse/expand: clicking the expand icon on a node collapses or expands its subtree
- **Group by Region:** Optional toggle that visually groups entities by region using coloured background swim lanes

### 2.6 Data Loading

Load the tree data via a Livewire method that returns:

```php
public function getChartData(): array
{
    $entities = Entity::with([
        'region',
        'children.region',
        'shareholdingsOwned',
        'shareholdingsHeld',
    ])
    ->withCount(['contracts', 'projects'])
    ->get();

    // Transform into tree structure for the JS chart
    // Root nodes: entities where parent_entity_id is null
    // Build recursive tree with shareholding data on edges
}
```

---

## Part 3: Entity Detail Popup (Contracts Viewer)

### 3.1 Trigger

Clicking "View Details" on an entity node (or clicking the entity node itself) opens a **slide-over panel** or **modal dialog**. Use Filament's built-in `Action` modal system or a custom Alpine.js modal — whichever integrates better with the chart page.

### 3.2 Popup Content

The popup must contain:

**Header:**
- Entity name (large)
- Entity code + region badge
- Legal name, registration number, registered address (collapsible details section)
- Parent entity name (if any) with shareholding percentage
- Quick link to the full Entity edit page (pencil icon → routes to EntityResource edit)

**Contracts Table (main content):**

A fully searchable, filterable, sortable table of ALL contracts linked to this entity. This includes:
- Contracts where `entity_id` = this entity (primary signatory)
- Contracts where `second_entity_id` = this entity (inter-company second party)

**Table columns:**
| Column | Source | Filterable | Sortable |
|--------|--------|-----------|----------|
| Title | `contracts.title` | Text search | Yes |
| Type | `contracts.contract_type` | Select filter (Commercial, Merchant, Inter-Company) | Yes |
| Region | `region.name` (via contract's region_id) | Select filter | Yes |
| Counterparty | `counterparty.legal_name` | Text search | Yes |
| Second Entity | `secondEntity.name` (for Inter-Company) | Select filter | Yes |
| Project | `project.name` | Select filter | Yes |
| Governing Law | `governingLaw.name` | Select filter | Yes |
| Workflow State | `contracts.workflow_state` | Select filter | Yes |
| Signing Status | `contracts.signing_status` | Select filter | Yes |
| Expiry Date | `contracts.expiry_date` | Date range filter | Yes |
| Created | `contracts.created_at` | Date range filter | Yes |

**Search:** A global text search input at the top that searches across title, counterparty name, project name simultaneously.

**Filters:** Collapsible filter panel (Filament-style) with all the filterable columns listed above.

**Row actions:**
- Click a row → navigate to the Contract view/edit page (ContractResource)
- Download icon → trigger the signed URL download for the contract file (if `storage_path` exists)

**Summary stats at the top of the table:**
- Total contracts count
- Breakdown by type (Commercial: X, Merchant: Y, Inter-Company: Z)
- Breakdown by workflow state (Draft: X, Active: Y, etc.)

### 3.3 Implementation Approach

**Preferred:** Use Livewire for the popup content. When the JS chart dispatches a "show entity details" event (via `$wire.showEntityDetails(entityId)` or Alpine `$dispatch`), a Livewire component loads the contract data and renders a Filament-style table inside the modal/slide-over.

This keeps the table fully server-rendered with proper pagination, search, and filters — no need to rebuild Filament's table features in JS.

**The Livewire component** should be a separate component (e.g., `app/Livewire/EntityContractsViewer.php` or `app/Filament/Pages/Partials/EntityContractsPanel.php`) that:
- Accepts an `entityId` parameter
- Queries contracts with eager loading
- Renders a Filament Table (use `InteractsWithTable` trait)
- Supports all the filters and search described above

---

## Part 4: Shareholding Management (CRUD)

### 4.1 Inline Editing (on the chart)

As described in 2.4 — clicking a connector opens a small edit form to modify the shareholding.

### 4.2 Bulk Management via Filament Resource

Also create a simple Filament resource for admin-level management:

**`app/Filament/Resources/EntityShareholdingResource.php`**

Table columns: Owner Entity, Owned Entity, Percentage, Type, Effective Date
Filters: Owner Entity, Owned Entity, Type
Form: Select (Owner Entity), Select (Owned Entity), Number (Percentage), Select (Type), DatePicker (Effective Date), Textarea (Notes)

This gives admins a spreadsheet-like view for bulk data entry, complementing the visual chart editor.

Restrict access to `system_admin` role only.

### 4.3 Validation

Implement these validations both in the Filament resource form and in the chart's inline edit:

1. `owner_entity_id !== owned_entity_id`
2. `percentage` between 0.01 and 100.00
3. Sum of all `direct` type shareholdings for a given `owned_entity_id` must not exceed 100.00%
4. No duplicate `(owner_entity_id, owned_entity_id, ownership_type)` combinations

---

## Part 5: Technical Notes

### NPM Dependencies

You may need to install a chart library. Recommended:

```bash
npm install d3@7 --save
# or
npm install dagre dagre-d3 --save
```

Add to `resources/js/app.js` or create a dedicated `resources/js/org-chart.js` entry point.
Update `vite.config.js` if adding a new entry point.

### Blade View

Create `resources/views/filament/pages/organisation-structure.blade.php` with:
- A full-height `<div id="org-chart-container">` for the D3/dagre canvas
- Alpine.js component wrapping the chart logic
- Wire integration for Livewire data and actions

### Colour Palette for Regions

Generate region colours deterministically from region code or ID so they're consistent:

```js
// Example: hash-based colour assignment
function regionColor(regionCode) {
    const colors = [
        '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
        '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
    ];
    let hash = 0;
    for (let i = 0; i < regionCode.length; i++) {
        hash = regionCode.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}
```

### Performance Considerations

- Expect 10–50 entities initially, scaling to ~200 max
- Eager load relationships to avoid N+1
- The contract popup table should be paginated (25 per page)
- Consider caching the tree structure (invalidate on entity/shareholding save)

### Access Control

- **View the org chart:** `system_admin`, `legal`, `commercial` roles
- **Edit shareholdings (inline on chart):** `system_admin` only
- **Manage EntityShareholding resource:** `system_admin` only
- **View contract popup:** same roles that can view the org chart (respect existing `is_restricted` contract logic)

---

## Acceptance Criteria

1. A new "Organisation Structure" page appears in the Filament sidebar for authorised users
2. The page renders an interactive tree chart showing all entities with their region labels
3. Parent-child relationships are shown as connectors with shareholding percentage labels
4. Clicking a connector allows editing the shareholding data (percentage, type, date, notes)
5. Clicking an entity opens a popup/slide-over with a fully searchable, filterable contracts table
6. The contracts table shows all contracts where the entity is either primary or secondary signatory
7. Entity nodes display summary counts (contracts, projects)
8. The chart supports zoom, pan, and collapse/expand
9. A separate EntityShareholding Filament resource exists for bulk management
10. All CRUD operations validate shareholding constraints (no self-ownership, percentages sum to <=100%)
11. The chart uses the existing `parent_entity_id` hierarchy as the tree structure
12. The feature works correctly on the `database` storage disk (respect `visibility('private')` for any file URLs)

---

## File Checklist

New files to create:
- [ ] `database/migrations/YYYY_MM_DD_create_entity_shareholdings_table.php`
- [ ] `app/Models/EntityShareholding.php`
- [ ] `app/Filament/Pages/OrganisationStructurePage.php`
- [ ] `app/Filament/Resources/EntityShareholdingResource.php`
- [ ] `app/Livewire/EntityContractsViewer.php` (or equivalent)
- [ ] `resources/views/filament/pages/organisation-structure.blade.php`
- [ ] `resources/js/org-chart.js` (if separate entry point)

Files to modify:
- [ ] `app/Models/Entity.php` — add `shareholdingsOwned()` and `shareholdingsHeld()` relationships
- [ ] `vite.config.js` — add new JS entry point if needed
- [ ] `package.json` — add D3 or dagre dependency

Files you MUST NOT modify (CTO-owned — see CLAUDE.md):
- `.github/workflows/deploy.yml`
- `Jenkinsfile`
- `deploy/k8s/*`
- `Dockerfile`
- `docker/`
- `docker-compose.yml`
- `CLAUDE.md`
