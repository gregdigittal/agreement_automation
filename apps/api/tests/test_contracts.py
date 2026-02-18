def test_contract_upload_pdf(authed_client):
    files = {"file": ("test.pdf", b"PDFDATA", "application/pdf")}
    data = {
        "regionId": "11111111-1111-1111-1111-111111111111",
        "entityId": "22222222-2222-2222-2222-222222222222",
        "projectId": "33333333-3333-3333-3333-333333333333",
        "counterpartyId": "44444444-4444-4444-4444-444444444444",
        "contractType": "Commercial",
        "title": "Uploaded Contract",
    }
    res = authed_client.post("/contracts/upload", data=data, files=files)
    assert res.status_code == 200


def test_contract_upload_rejects_non_pdf(authed_client):
    files = {"file": ("test.txt", b"DATA", "text/plain")}
    data = {
        "regionId": "11111111-1111-1111-1111-111111111111",
        "entityId": "22222222-2222-2222-2222-222222222222",
        "projectId": "33333333-3333-3333-3333-333333333333",
        "counterpartyId": "44444444-4444-4444-4444-444444444444",
        "contractType": "Commercial",
        "title": "Bad Upload",
    }
    res = authed_client.post("/contracts/upload", data=data, files=files)
    assert res.status_code == 400


def test_contract_upload_blocked_for_blacklisted(authed_client):
    files = {"file": ("test.pdf", b"PDFDATA", "application/pdf")}
    data = {
        "regionId": "11111111-1111-1111-1111-111111111111",
        "entityId": "22222222-2222-2222-2222-222222222222",
        "projectId": "33333333-3333-3333-3333-333333333333",
        "counterpartyId": "55555555-5555-5555-5555-555555555555",
        "contractType": "Commercial",
        "title": "Blocked Upload",
    }
    res = authed_client.post("/contracts/upload", data=data, files=files)
    assert res.status_code == 400


def test_contract_search_with_filters(authed_client):
    res = authed_client.get("/contracts?regionId=11111111-1111-1111-1111-111111111111&contractType=Commercial")
    assert res.status_code == 200
    assert len(res.json()) >= 1
    assert "X-Total-Count" in res.headers


def test_contract_search_with_query(authed_client):
    res = authed_client.get("/contracts?q=contract")
    assert res.status_code == 200
    assert len(res.json()) >= 1


def test_contract_update_blocked_for_executed(authed_client):
    res = authed_client.patch("/contracts/88888888-8888-8888-8888-888888888888", json={"title": "New"})
    assert res.status_code == 400


def test_contract_delete_blocked_for_archived(authed_client):
    res = authed_client.delete("/contracts/99999999-9999-9999-9999-999999999999")
    assert res.status_code == 400


def test_contract_download_audits_access(authed_client, mock_supabase):
    before = len(mock_supabase.data["audit_log"])
    res = authed_client.get("/contracts/77777777-7777-7777-7777-777777777777/download-url")
    assert res.status_code == 200
    after = len(mock_supabase.data["audit_log"])
    assert after == before + 1
    assert mock_supabase.data["audit_log"][-1]["action"] == "contract.download"
