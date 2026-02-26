# 11. Organization Setup

CCRS organizes contracts using a three-level hierarchy: **Regions**, **Entities**, and **Projects**. This structure determines how workflow templates are matched to contracts, how reports can be filtered, and how access is scoped across the organisation. Only **System Admin** users can create, edit, or delete organisation structure records.

---

## Organizational Hierarchy

The hierarchy flows from broad geographic groupings down to specific operational units.

```
Region (e.g. MENA, EMEA, APAC)
  └── Entity (e.g. Digittal AE, Digittal UK)
        └── Project (e.g. PRJ-001, PRJ-002)
```

Every contract in CCRS is associated with a **Project**, which belongs to an **Entity**, which belongs to a **Region**. This chain gives you consistent, multi-level filtering and reporting throughout the system.

### Why This Matters

- **Workflow template matching** -- templates can be scoped to a specific Region, Entity, or Project. When a new contract is created, CCRS selects the most specific matching template.
- **Report filtering** -- dashboards and reports can be filtered by any combination of Region, Entity, and Project.
- **Access scoping** -- signing authorities and permissions can be restricted to specific parts of the hierarchy.

---

## Setup Order

You must create records in dependency order. Each level depends on the one above it.

| Step | Record Type | Depends On |
|---|---|---|
| 1 | **Regions** | Nothing -- create these first. |
| 2 | **Entities** | A Region must exist before you can create an Entity. |
| 3 | **Projects** | An Entity must exist before you can create a Project. |

If you attempt to create an Entity without first creating a Region, you will have no Region to select in the form. The same applies to Projects and Entities.

---

## Regions

Regions are the top level of the hierarchy. They typically represent geographic areas or business divisions.

### Creating a Region

1. Navigate to **Org Structure** in the left sidebar and click **Regions**.
2. Click the **"New"** button in the top-right corner.
3. Fill in the fields:
   - **Name** -- the display name for the region (e.g. "Middle East & North Africa").
   - **Code** -- a short, unique identifier (e.g. "MENA", "EMEA", "APAC"). This code is used in CSV imports, reports, and system filters. It does not appear on contracts themselves.
   - **Description** -- an optional note explaining the scope or purpose of the region.
4. Click **Save**.

### Region Fields

| Field | Required | Description |
|---|---|---|
| **Name** | Yes | Display name shown throughout the application. |
| **Code** | Yes | Unique short code used in imports, reports, and filters. |
| **Description** | No | Free-text description of the region's scope. |

---

## Entities

Entities represent legal or organisational units within a Region -- companies, subsidiaries, or divisions.

### Creating an Entity

1. Navigate to **Org Structure** in the left sidebar and click **Entities**.
2. Click the **"New"** button.
3. Fill in the fields:
   - **Name** -- the display name (e.g. "Digittal AE").
   - **Code** -- a unique short code (e.g. "DGT-AE"). Used in the same contexts as the Region code.
   - **Legal Name** -- the full legal name as it appears on official registration documents.
   - **Registration Number** -- the company registration or incorporation number.
   - **Region** -- select the Region this entity belongs to (dropdown populated from existing Regions).
   - **Parent Entity** -- optionally select another Entity as the parent, creating a hierarchical relationship (for example, a holding company as the parent and its subsidiary as the child).
4. Click **Save**.

### Entity Fields

| Field | Required | Description |
|---|---|---|
| **Name** | Yes | Display name shown throughout the application. |
| **Code** | Yes | Unique short code used in imports, reports, and filters. |
| **Legal Name** | No | Full legal name as registered. |
| **Registration Number** | No | Company registration / incorporation number. |
| **Region** | Yes | The Region this entity belongs to. |
| **Parent Entity** | No | Another Entity that serves as this entity's parent in the hierarchy. |

### Entity Hierarchy (Parent/Child Relationships)

The **Parent Entity** field allows you to model corporate structures beyond the three-level Region/Entity/Project chain. For example:

```
Region: MENA
  └── Entity: Digittal Holdings (parent)
        ├── Entity: Digittal AE (child — parent = Digittal Holdings)
        └── Entity: Digittal SA (child — parent = Digittal Holdings)
              └── Project: PRJ-010
```

This is useful when a holding company has multiple operating subsidiaries, each with its own projects and contracts. The parent-child relationship is displayed in the **Organization Visualization** page (see below).

### Assigning Jurisdictions to an Entity

Entities can operate in one or more legal jurisdictions. To associate jurisdictions with an entity:

1. Open the Entity record.
2. Navigate to the **Jurisdictions** tab (relation manager).
3. Click **"Attach"** and select one or more Jurisdiction records.

Jurisdictions assigned to an entity are used in compliance tracking and regulatory reporting. See the Jurisdictions section below for details on creating Jurisdiction records.

---

## Projects

Projects are the most granular level of the hierarchy. Each project belongs to exactly one Entity and serves as the organisational bucket to which contracts are assigned.

### Creating a Project

1. Navigate to **Org Structure** in the left sidebar and click **Projects**.
2. Click the **"New"** button.
3. Fill in the fields:
   - **Name** -- the project's display name.
   - **Code** -- a unique short code (e.g. "PRJ-001").
   - **Entity** -- select the Entity this project belongs to (dropdown populated from existing Entities).
   - **Description** -- an optional description of the project's scope or purpose.
4. Click **Save**.

### Project Fields

| Field | Required | Description |
|---|---|---|
| **Name** | Yes | Display name shown throughout the application. |
| **Code** | Yes | Unique short code used in imports, reports, and filters. |
| **Entity** | Yes | The Entity this project belongs to. |
| **Description** | No | Free-text description of the project. |

---

## Jurisdictions

Jurisdictions represent legal territories and regulatory environments. They are assigned to Entities to indicate where those entities operate.

### Creating a Jurisdiction

1. Navigate to **Administration** in the left sidebar and click **Jurisdictions**.
2. Click the **"New"** button.
3. Fill in the fields:
   - **Name** -- the jurisdiction's display name (e.g. "United Arab Emirates", "United Kingdom").
   - **Country Code** -- the ISO country code (e.g. "AE", "GB", "US").
   - **Regulatory Body** -- the name of the primary regulatory body in this jurisdiction (e.g. "Securities and Commodities Authority", "Financial Conduct Authority").
   - **Description** -- optional additional context about the jurisdiction's regulatory environment.
4. Click **Save**.

### Jurisdiction Fields

| Field | Required | Description |
|---|---|---|
| **Name** | Yes | Display name of the jurisdiction. |
| **Country Code** | Yes | ISO country code. |
| **Regulatory Body** | No | Name of the primary regulatory authority. |
| **Description** | No | Free-text description of the regulatory context. |

### How Jurisdictions Are Used

- **Entity compliance** -- when an Entity has one or more Jurisdictions attached, the system can track regulatory compliance requirements specific to those territories.
- **Contract context** -- contracts created under an Entity inherit awareness of the Entity's jurisdictions, which informs compliance checks and reporting.

---

## Signing Authorities

Signing Authorities define who is authorised to sign contracts and up to what value. They provide a critical governance control -- ensuring that contracts above a certain value require sign-off from appropriately senior personnel.

### Creating a Signing Authority

1. Navigate to **Administration** in the left sidebar and click **Signing Authorities**.
2. Click the **"New"** button.
3. Fill in the fields:
   - **User** -- select the user who is being granted signing authority.
   - **Entity** -- optionally select an Entity to scope this authority to a specific entity. Leave blank for broader authority.
   - **Project** -- optionally select a Project to scope this authority to a specific project. Leave blank for entity-wide or global authority.
   - **Authority Level** -- the level of signing authority (used to differentiate tiers of authorisation).
   - **Max Contract Value** -- the maximum contract value (in the specified currency) that this user is authorised to sign.
   - **Currency** -- the currency for the max contract value threshold.
4. Click **Save**.

### Signing Authority Fields

| Field | Required | Description |
|---|---|---|
| **User** | Yes | The user being granted signing authority. |
| **Entity** | No | Scopes the authority to a specific entity. |
| **Project** | No | Scopes the authority to a specific project. |
| **Authority Level** | Yes | The tier of signing authority. |
| **Max Contract Value** | Yes | Maximum value this user can authorise. |
| **Currency** | Yes | Currency for the max contract value. |

### Scoping Rules

Signing authority can be scoped at different levels of specificity:

| Entity | Project | Scope |
|---|---|---|
| Set | Set | Authority applies only to the specified project within the specified entity. |
| Set | Blank | Authority applies to all projects within the specified entity. |
| Blank | Blank | Authority applies across the entire organisation (global). |

When a contract is submitted for signing, CCRS checks whether the proposed signer has a Signing Authority record that covers the contract's entity (or project) and that the contract's value does not exceed the signer's **Max Contract Value** threshold.

---

## Organization Visualization

The **Organization Visualization** page provides a visual, tree-based display of your complete organisational structure.

### Accessing the Visualization

1. Navigate to **Org Structure** in the left sidebar and click **Organization Visualization**.
2. The page renders a hierarchical tree showing the full Region, Entity, and Project structure.

### What the Visualization Shows

The tree displays the complete hierarchy:

```
Region: MENA
  ├── Entity: Digittal Holdings
  │     ├── Entity: Digittal AE (subsidiary)
  │     │     ├── Project: PRJ-001
  │     │     └── Project: PRJ-002
  │     └── Entity: Digittal SA (subsidiary)
  │           └── Project: PRJ-010
  └── Entity: Independent Corp
        └── Project: PRJ-020

Region: EMEA
  └── Entity: Digittal UK
        ├── Project: PRJ-100
        └── Project: PRJ-101
```

- **Regions** are the top-level nodes.
- **Entities** are nested under their Region, with parent-child Entity relationships shown as sub-nesting.
- **Projects** are the leaf nodes, nested under their Entity.

### Use Cases

- **Onboarding** -- new team members can quickly understand the organisational topology and where their projects sit.
- **Planning** -- when creating workflow templates scoped to specific parts of the hierarchy, the visualization helps confirm the correct Region/Entity/Project target.
- **Audit** -- auditors can verify the organisational structure and confirm that the hierarchy reflects the actual corporate structure.

---

## Permissions

Only **System Admin** users can manage organisational structure. The table below summarises access.

| Action | System Admin | Legal | Commercial | Finance | Operations | Audit |
|---|---|---|---|---|---|---|
| Create / edit Regions | Yes | -- | -- | -- | -- | -- |
| Create / edit Entities | Yes | -- | -- | -- | -- | -- |
| Create / edit Projects | Yes | -- | -- | -- | -- | -- |
| Create / edit Jurisdictions | Yes | -- | -- | -- | -- | -- |
| Create / edit Signing Authorities | Yes | -- | -- | -- | -- | -- |
| View Organization Visualization | Yes | Yes | Yes | Yes | Yes | Yes |

All users can view the Organization Visualization page, but only System Admins can modify the underlying records.

---

## Best Practices

- **Establish Regions first.** Before onboarding any contracts, define your full set of Regions. Changing Regions later is possible but affects all downstream Entities and their contracts.
- **Use consistent Code conventions.** Adopt a naming scheme for codes (e.g. region codes in uppercase like "MENA", entity codes with a prefix like "DGT-AE", project codes with a sequential pattern like "PRJ-001") and document it so all administrators follow the same convention.
- **Model your real corporate structure.** Use the Parent Entity field to reflect your actual holding-company / subsidiary relationships. This makes the Organization Visualization accurate and useful for auditors.
- **Assign Jurisdictions early.** Attaching Jurisdictions to Entities as soon as they are created ensures that compliance tracking is active from the first contract.
- **Set conservative signing authority limits.** Start with lower Max Contract Value thresholds and increase them as needed. It is easier to grant additional authority than to revoke it after a contract has been signed.
- **Review the visualization after changes.** After adding or restructuring Entities, visit the Organization Visualization page to confirm the hierarchy looks correct before creating contracts against the new structure.
