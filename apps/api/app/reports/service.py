import structlog
from supabase import Client

logger = structlog.get_logger()


def contract_status_summary(
    supabase: Client, region_id: str | None = None, entity_id: str | None = None
) -> dict:
    query = supabase.table("contracts").select("workflow_state, contract_type, id")
    if region_id:
        query = query.eq("region_id", region_id)
    if entity_id:
        query = query.eq("entity_id", entity_id)
    result = query.execute()

    by_state: dict[str, int] = {}
    by_type: dict[str, int] = {}
    for row in result.data or []:
        state = row.get("workflow_state", "unknown")
        ctype = row.get("contract_type", "unknown")
        by_state[state] = by_state.get(state, 0) + 1
        by_type[ctype] = by_type.get(ctype, 0) + 1

    return {"by_state": by_state, "by_type": by_type, "total": len(result.data or [])}


def expiry_horizon(supabase: Client, region_id: str | None = None) -> dict:
    from datetime import datetime, timezone

    now = datetime.now(timezone.utc).date()

    query = supabase.table("contract_key_dates").select(
        "contract_id, date_value, contracts(id, title, workflow_state, entity_id)"
    )
    query = query.eq("date_type", "expiry_date").gte("date_value", now.isoformat())
    if region_id:
        query = query.eq("contracts.region_id", region_id)
    result = query.order("date_value").execute()

    buckets = {"0_30": [], "31_60": [], "61_90": [], "90_plus": []}
    for row in result.data or []:
        dv = row["date_value"]
        if isinstance(dv, str):
            dv = datetime.fromisoformat(dv).date()
        days_until = (dv - now).days
        entry = {
            "contract_id": row["contract_id"],
            "expiry_date": row["date_value"],
            "days_until": days_until,
            "contract": row.get("contracts"),
        }
        if days_until <= 30:
            buckets["0_30"].append(entry)
        elif days_until <= 60:
            buckets["31_60"].append(entry)
        elif days_until <= 90:
            buckets["61_90"].append(entry)
        else:
            buckets["90_plus"].append(entry)

    return {"buckets": buckets, "counts": {k: len(v) for k, v in buckets.items()}}


def signing_status_summary(supabase: Client) -> dict:
    result = supabase.table("boldsign_envelopes").select("status, id").execute()
    by_status: dict[str, int] = {}
    for row in result.data or []:
        st = row.get("status", "unknown")
        by_status[st] = by_status.get(st, 0) + 1
    return {"by_status": by_status, "total": len(result.data or [])}


def ai_cost_summary(supabase: Client, period_days: int = 30) -> dict:
    from datetime import datetime, timedelta, timezone

    since = (datetime.now(timezone.utc) - timedelta(days=period_days)).isoformat()

    result = (
        supabase.table("ai_analysis_results")
        .select("analysis_type, cost_usd, token_usage_input, token_usage_output, model_used")
        .gte("created_at", since)
        .eq("status", "completed")
        .execute()
    )

    total_cost = 0.0
    total_input_tokens = 0
    total_output_tokens = 0
    by_type: dict[str, dict] = {}

    for row in result.data or []:
        cost = float(row.get("cost_usd") or 0)
        inp = row.get("token_usage_input") or 0
        out = row.get("token_usage_output") or 0
        total_cost += cost
        total_input_tokens += inp
        total_output_tokens += out

        atype = row.get("analysis_type", "unknown")
        if atype not in by_type:
            by_type[atype] = {"count": 0, "cost_usd": 0.0, "input_tokens": 0, "output_tokens": 0}
        by_type[atype]["count"] += 1
        by_type[atype]["cost_usd"] += cost
        by_type[atype]["input_tokens"] += inp
        by_type[atype]["output_tokens"] += out

    return {
        "period_days": period_days,
        "total_analyses": len(result.data or []),
        "total_cost_usd": round(total_cost, 4),
        "total_input_tokens": total_input_tokens,
        "total_output_tokens": total_output_tokens,
        "by_type": by_type,
    }
import structlog
from supabase import Client

logger = structlog.get_logger()


def contract_status_summary(
    supabase: Client, region_id: str | None = None, entity_id: str | None = None
) -> dict:
    query = supabase.table("contracts").select("workflow_state, contract_type, id")
    if region_id:
        query = query.eq("region_id", region_id)
    if entity_id:
        query = query.eq("entity_id", entity_id)
    result = query.execute()

    by_state: dict[str, int] = {}
    by_type: dict[str, int] = {}
    for row in result.data or []:
        state = row.get("workflow_state", "unknown")
        ctype = row.get("contract_type", "unknown")
        by_state[state] = by_state.get(state, 0) + 1
        by_type[ctype] = by_type.get(ctype, 0) + 1

    return {"by_state": by_state, "by_type": by_type, "total": len(result.data or [])}


def expiry_horizon(supabase: Client, region_id: str | None = None) -> dict:
    from datetime import datetime, timezone

    now = datetime.now(timezone.utc).date()
    query = supabase.table("contract_key_dates").select(
        "contract_id, date_value, contracts(id, title, workflow_state, entity_id)"
    )
    query = query.eq("date_type", "expiry_date").gte("date_value", now.isoformat())
    result = query.order("date_value").execute()

    buckets = {"0_30": [], "31_60": [], "61_90": [], "90_plus": []}
    for row in result.data or []:
        dv = row["date_value"]
        if isinstance(dv, str):
            dv = datetime.fromisoformat(dv).date()
        days_until = (dv - now).days
        entry = {
            "contract_id": row["contract_id"],
            "expiry_date": row["date_value"],
            "days_until": days_until,
            "contract": row.get("contracts"),
        }
        if days_until <= 30:
            buckets["0_30"].append(entry)
        elif days_until <= 60:
            buckets["31_60"].append(entry)
        elif days_until <= 90:
            buckets["61_90"].append(entry)
        else:
            buckets["90_plus"].append(entry)

    return {"buckets": buckets, "counts": {k: len(v) for k, v in buckets.items()}}


def signing_status_summary(supabase: Client) -> dict:
    result = supabase.table("boldsign_envelopes").select("status, id").execute()
    by_status: dict[str, int] = {}
    for row in result.data or []:
        st = row.get("status", "unknown")
        by_status[st] = by_status.get(st, 0) + 1
    return {"by_status": by_status, "total": len(result.data or [])}


def ai_cost_summary(supabase: Client, period_days: int = 30) -> dict:
    from datetime import datetime, timedelta, timezone

    since = (datetime.now(timezone.utc) - timedelta(days=period_days)).isoformat()
    result = (
        supabase.table("ai_analysis_results")
        .select("analysis_type, cost_usd, token_usage_input, token_usage_output, model_used")
        .gte("created_at", since)
        .eq("status", "completed")
        .execute()
    )

    total_cost = 0.0
    total_input_tokens = 0
    total_output_tokens = 0
    by_type: dict[str, dict] = {}

    for row in result.data or []:
        cost = float(row.get("cost_usd") or 0)
        inp = row.get("token_usage_input") or 0
        out = row.get("token_usage_output") or 0
        total_cost += cost
        total_input_tokens += inp
        total_output_tokens += out

        atype = row.get("analysis_type", "unknown")
        if atype not in by_type:
            by_type[atype] = {"count": 0, "cost_usd": 0.0, "input_tokens": 0, "output_tokens": 0}
        by_type[atype]["count"] += 1
        by_type[atype]["cost_usd"] += cost
        by_type[atype]["input_tokens"] += inp
        by_type[atype]["output_tokens"] += out

    return {
        "period_days": period_days,
        "total_analyses": len(result.data or []),
        "total_cost_usd": round(total_cost, 4),
        "total_input_tokens": total_input_tokens,
        "total_output_tokens": total_output_tokens,
        "by_type": by_type,
    }
