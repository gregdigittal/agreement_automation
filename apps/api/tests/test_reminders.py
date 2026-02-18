from datetime import datetime, timedelta, timezone

import pytest

from app.reminders.service import process_due_reminders


def test_reminders_crud(authed_client):
    contract_id = "77777777-7777-7777-7777-777777777777"
    key_date_id = "20202020-2020-2020-2020-202020202020"

    res = authed_client.post(
        f"/contracts/{contract_id}/reminders",
        json={
            "keyDateId": key_date_id,
            "reminder_type": "expiry",
            "lead_days": 30,
            "channel": "email",
            "recipient_email": "ops@example.com",
        },
    )
    assert res.status_code == 201
    reminder_id = res.json()["id"]

    res = authed_client.get(f"/contracts/{contract_id}/reminders")
    assert res.status_code == 200

    res = authed_client.patch(f"/reminders/{reminder_id}", json={"is_active": False})
    assert res.status_code == 200
    assert res.json()["is_active"] is False

    res = authed_client.delete(f"/reminders/{reminder_id}")
    assert res.status_code == 200


@pytest.mark.asyncio
async def test_process_due_reminders(mock_supabase):
    now = datetime.now(timezone.utc)
    mock_supabase.data["reminders"][0]["next_due_at"] = (now - timedelta(hours=1)).isoformat()
    mock_supabase.data["reminders"][0]["last_sent_at"] = None

    count = await process_due_reminders(mock_supabase)
    assert count == 1

    count = await process_due_reminders(mock_supabase)
    assert count == 0
