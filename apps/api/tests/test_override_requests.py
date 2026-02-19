import pytest


def test_create_override_request(authed_client, mock_supabase):
    """Test creating an override request for a blocked counterparty."""
    cps = mock_supabase.table("counterparties").select("*").eq("status", "Blacklisted").execute()
    if not cps.data:
        pytest.skip("No blacklisted counterparty in seed data")
    cp_id = cps.data[0]["id"]

    response = authed_client.post(
        f"/counterparties/{cp_id}/override-requests",
        json={"contractTitle": "Test Contract", "reason": "Urgent business need"},
    )
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "pending"


def test_list_pending_override_requests(authed_client):
    """Test listing pending override requests (requires Legal/Admin)."""
    response = authed_client.get("/override-requests")
    assert response.status_code == 200


def test_decide_override_request_requires_legal(client_context):
    """Test that only Legal/Admin can decide override requests."""
    with client_context(roles=["Commercial"]) as client:
        response = client.patch(
            "/override-requests/00000000-0000-0000-0000-000000000099",
            json={"decision": "approved"},
        )
        assert response.status_code == 403
