def test_audit_export_requires_auth(unauthed_client):
    r = unauthed_client.get("/audit/export")
    assert r.status_code == 401


def test_audit_export_invalid_dates(authed_client):
    r = authed_client.get("/audit/export?from=not-a-date")
    assert r.status_code == 422


def test_audit_resource_query(authed_client):
    r = authed_client.get("/audit/resource/region/11111111-1111-1111-1111-111111111111")
    assert r.status_code == 200
