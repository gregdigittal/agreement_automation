import structlog
from supabase import Client

logger = structlog.get_logger()


def get_tools(supabase: Client, contract_id: str) -> list[dict]:
    return [
        {
            "definition": {
                "name": "query_org_structure",
                "description": "Query the CCRS organizational structure: regions, entities, projects. Use this to understand which entity owns the contract and what region it belongs to.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "region_id": {"type": "string", "description": "Filter by region ID (optional)"},
                        "entity_id": {"type": "string", "description": "Filter by entity ID (optional)"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_org_structure(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_authority_matrix",
                "description": "Query signing authority rules to check who can approve or sign contracts for a given entity/project/amount.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "entity_id": {"type": "string", "description": "Filter by entity ID (optional)"},
                        "project_id": {"type": "string", "description": "Filter by project ID (optional)"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_authority_matrix(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_wiki_contracts",
                "description": "Search the WikiContracts template library for standard templates and precedents. Use this to compare the contract against organizational standards.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "category": {"type": "string", "description": "Template category (optional)"},
                        "region_id": {"type": "string", "description": "Filter by region (optional)"},
                        "status": {"type": "string", "description": "Template status, default 'published'"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_wiki_contracts(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_counterparty",
                "description": "Look up counterparty details including status, jurisdiction, and contacts for the contract's counterparty.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "counterparty_id": {"type": "string", "description": "The counterparty ID to look up"},
                    },
                    "required": ["counterparty_id"],
                },
            },
            "handler": lambda **kwargs: _query_counterparty(supabase, **kwargs),
        },
    ]


def _query_org_structure(supabase: Client, region_id: str | None = None, entity_id: str | None = None) -> dict:
    try:
        if entity_id:
            result = supabase.table("entities").select("*, regions(*)").eq("id", entity_id).execute()
            return {"entities": result.data}
        if region_id:
            result = supabase.table("entities").select("*, regions(*)").eq("region_id", region_id).execute()
            return {"entities": result.data}
        regions = supabase.table("regions").select("*").execute()
        entities = supabase.table("entities").select("*").limit(50).execute()
        return {"regions": regions.data, "entities": entities.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_org_structure", error=str(e))
        return {"error": str(e)}


def _query_authority_matrix(supabase: Client, entity_id: str | None = None, project_id: str | None = None) -> dict:
    try:
        query = supabase.table("signing_authority").select("*")
        if entity_id:
            query = query.eq("entity_id", entity_id)
        if project_id:
            query = query.eq("project_id", project_id)
        result = query.limit(50).execute()
        return {"signing_authority": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_authority_matrix", error=str(e))
        return {"error": str(e)}


def _query_wiki_contracts(
    supabase: Client, category: str | None = None, region_id: str | None = None, status: str = "published"
) -> dict:
    try:
        query = (
            supabase.table("wiki_contracts")
            .select("id, name, category, region_id, version, status, description")
            .eq("status", status)
        )
        if category:
            query = query.eq("category", category)
        if region_id:
            query = query.eq("region_id", region_id)
        result = query.limit(25).execute()
        return {"templates": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_wiki_contracts", error=str(e))
        return {"error": str(e)}


def _query_counterparty(supabase: Client, counterparty_id: str) -> dict:
    try:
        result = (
            supabase.table("counterparties")
            .select("*, counterparty_contacts(*)")
            .eq("id", counterparty_id)
            .single()
            .execute()
        )
        return {"counterparty": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_counterparty", error=str(e))
        return {"error": str(e)}
import structlog
from supabase import Client

logger = structlog.get_logger()


def get_tools(supabase: Client, contract_id: str) -> list[dict]:
    return [
        {
            "definition": {
                "name": "query_org_structure",
                "description": "Query the CCRS organizational structure: regions, entities, projects. Use this to understand which entity owns the contract and what region it belongs to.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "region_id": {"type": "string", "description": "Filter by region ID (optional)"},
                        "entity_id": {"type": "string", "description": "Filter by entity ID (optional)"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_org_structure(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_authority_matrix",
                "description": "Query signing authority rules to check who can approve or sign contracts for a given entity/project/amount.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "entity_id": {"type": "string", "description": "Filter by entity ID (optional)"},
                        "project_id": {"type": "string", "description": "Filter by project ID (optional)"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_authority_matrix(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_wiki_contracts",
                "description": "Search the WikiContracts template library for standard templates and precedents. Use this to compare the contract against organizational standards.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "category": {"type": "string", "description": "Template category (optional)"},
                        "region_id": {"type": "string", "description": "Filter by region (optional)"},
                        "status": {"type": "string", "description": "Template status, default 'published'"},
                    },
                    "required": [],
                },
            },
            "handler": lambda **kwargs: _query_wiki_contracts(supabase, **kwargs),
        },
        {
            "definition": {
                "name": "query_counterparty",
                "description": "Look up counterparty details including status, jurisdiction, and contacts for the contract's counterparty.",
                "input_schema": {
                    "type": "object",
                    "properties": {
                        "counterparty_id": {"type": "string", "description": "The counterparty ID to look up"},
                    },
                    "required": ["counterparty_id"],
                },
            },
            "handler": lambda **kwargs: _query_counterparty(supabase, **kwargs),
        },
    ]


def _query_org_structure(supabase: Client, region_id: str | None = None, entity_id: str | None = None) -> dict:
    try:
        if entity_id:
            result = supabase.table("entities").select("*, regions(*)").eq("id", entity_id).execute()
            return {"entities": result.data}
        if region_id:
            result = supabase.table("entities").select("*, regions(*)").eq("region_id", region_id).execute()
            return {"entities": result.data}
        regions = supabase.table("regions").select("*").execute()
        entities = supabase.table("entities").select("*").limit(50).execute()
        return {"regions": regions.data, "entities": entities.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_org_structure", error=str(e))
        return {"error": str(e)}


def _query_authority_matrix(supabase: Client, entity_id: str | None = None, project_id: str | None = None) -> dict:
    try:
        query = supabase.table("signing_authority").select("*")
        if entity_id:
            query = query.eq("entity_id", entity_id)
        if project_id:
            query = query.eq("project_id", project_id)
        result = query.limit(50).execute()
        return {"signing_authority": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_authority_matrix", error=str(e))
        return {"error": str(e)}


def _query_wiki_contracts(
    supabase: Client, category: str | None = None, region_id: str | None = None, status: str = "published"
) -> dict:
    try:
        query = (
            supabase.table("wiki_contracts")
            .select("id, name, category, region_id, version, status, description")
            .eq("status", status)
        )
        if category:
            query = query.eq("category", category)
        if region_id:
            query = query.eq("region_id", region_id)
        result = query.limit(25).execute()
        return {"templates": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_wiki_contracts", error=str(e))
        return {"error": str(e)}


def _query_counterparty(supabase: Client, counterparty_id: str) -> dict:
    try:
        result = (
            supabase.table("counterparties")
            .select("*, counterparty_contacts(*)")
            .eq("id", counterparty_id)
            .single()
            .execute()
        )
        return {"counterparty": result.data}
    except Exception as e:
        logger.error("mcp_tool_error", tool="query_counterparty", error=str(e))
        return {"error": str(e)}
