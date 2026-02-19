import re

import pytest

from app.notifications.service import send_pending_notifications


def test_list_notifications(authed_client):
    res = authed_client.get("/notifications")
    assert res.status_code == 200
    assert len(res.json()) >= 1


@pytest.mark.asyncio
async def test_send_pending_notifications(mock_supabase):
    count = await send_pending_notifications(mock_supabase)
    assert count >= 1
    assert mock_supabase.data["notifications"][0]["status"] == "sent"


def test_mark_read_sets_timestamp(authed_client):
    """Verify mark-read sets a real ISO timestamp, not the string 'now()'."""
    res = authed_client.get("/notifications")
    assert res.status_code == 200
    notifications = res.json()
    if not notifications:
        pytest.skip("No notifications in seed data")
    notif_id = notifications[0]["id"]

    mark_res = authed_client.patch(f"/notifications/{notif_id}/read")
    assert mark_res.status_code == 200
    data = mark_res.json()

    read_at = data.get("read_at")
    assert read_at is not None
    assert read_at != "now()"
    assert re.match(r"\d{4}-\d{2}-\d{2}T", read_at), f"Expected ISO timestamp, got: {read_at}"
