def test_notification_unread_count(authed_client):
    response = authed_client.get("/notifications/unread-count")
    assert response.status_code == 200
    assert "count" in response.json()


def test_notification_mark_all_read(authed_client):
    response = authed_client.post("/notifications/mark-all-read")
    assert response.status_code == 200


def test_notifications_list_unauthenticated(unauthed_client):
    response = unauthed_client.get("/notifications")
    assert response.status_code == 401
