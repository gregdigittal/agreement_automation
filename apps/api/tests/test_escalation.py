from datetime import datetime, timedelta, timezone

import pytest

from app.escalation.service import check_sla_breaches


def test_escalation_rules_crud(authed_client):
    template_id = "dddddddd-dddd-dddd-dddd-dddddddddddd"
    res = authed_client.post(
        f"/workflow-templates/{template_id}/escalation-rules",
        json={"stage_name": "Review", "sla_breach_hours": 2, "tier": 1},
    )
    assert res.status_code == 201
    rule_id = res.json()["id"]

    res = authed_client.get(f"/workflow-templates/{template_id}/escalation-rules")
    assert res.status_code == 200

    res = authed_client.patch(f"/escalation-rules/{rule_id}", json={"tier": 2})
    assert res.status_code == 200
    assert res.json()["tier"] == 2

    res = authed_client.delete(f"/escalation-rules/{rule_id}")
    assert res.status_code == 200


@pytest.mark.asyncio
async def test_check_sla_breaches_creates_escalations(mock_supabase):
    mock_supabase.data["escalation_events"] = []
    mock_supabase.data["workflow_instances"] = [
        {
            "id": "19191919-1919-1919-1919-191919191919",
            "contract_id": "77777777-7777-7777-7777-777777777777",
            "template_id": "dddddddd-dddd-dddd-dddd-dddddddddddd",
            "state": "active",
            "current_stage": "Review",
            "started_at": (datetime.now(timezone.utc) - timedelta(hours=5)).isoformat(),
        }
    ]
    mock_supabase.data["workflow_stage_actions"] = []
    mock_supabase.data["escalation_rules"] = [
        {
            "id": "15151515-1515-1515-1515-151515151515",
            "workflow_template_id": "dddddddd-dddd-dddd-dddd-dddddddddddd",
            "stage_name": "Review",
            "sla_breach_hours": 1,
            "tier": 1,
            "escalate_to_role": "Legal",
            "escalate_to_user_id": None,
            "created_at": datetime.now(timezone.utc).isoformat(),
            "updated_at": datetime.now(timezone.utc).isoformat(),
        }
    ]
    count = await check_sla_breaches(mock_supabase)
    assert count >= 1
    second = await check_sla_breaches(mock_supabase)
    assert second == 0


def test_resolve_escalation(authed_client, mock_supabase):
    event_id = mock_supabase.data["escalation_events"][0]["id"]
    res = authed_client.post(f"/escalations/{event_id}/resolve")
    assert res.status_code == 200
    assert res.json()["resolved_at"] is not None


def test_list_active_escalations(authed_client):
    res = authed_client.get("/escalations/active")
    assert res.status_code == 200
