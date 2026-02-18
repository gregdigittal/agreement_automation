def test_health_no_auth(authed_client):
    r = authed_client.get("/health")
    assert r.status_code == 200
    data = r.json()
    assert "status" in data
    assert "db" in data
