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
