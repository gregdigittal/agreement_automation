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
