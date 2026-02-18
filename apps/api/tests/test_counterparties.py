def test_counterparties_crud(authed_client):
    create = authed_client.post(
        "/counterparties",
        json={"legal_name": "New Co", "registration_number": "REG555"},
    )
    assert create.status_code == 200
    counterparty_id = create.json()["id"]

    list_res = authed_client.get("/counterparties")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    get_res = authed_client.get(f"/counterparties/{counterparty_id}")
    assert get_res.status_code == 200

    update_res = authed_client.patch(
        f"/counterparties/{counterparty_id}",
        json={"legal_name": "New Co Updated"},
    )
    assert update_res.status_code == 200

    delete_res = authed_client.delete(f"/counterparties/{counterparty_id}")
    assert delete_res.status_code == 200


def test_counterparties_duplicates(authed_client):
    res = authed_client.get("/counterparties/duplicates?legalName=acme")
    assert res.status_code == 200
    data = res.json()
    assert any(d["legal_name"] == "Acme Corp" for d in data)


def test_counterparty_status_requires_legal(authed_client):
    res = authed_client.patch(
        "/counterparties/44444444-4444-4444-4444-444444444444/status",
        json={"status": "Suspended", "reason": "Risk"},
    )
    assert res.status_code == 403


def test_counterparty_status_persists_supporting_ref(legal_client):
    res = legal_client.patch(
        "/counterparties/44444444-4444-4444-4444-444444444444/status",
        json={
            "status": "Suspended",
            "reason": "Risk",
            "supporting_document_ref": "DOC-123",
        },
    )
    assert res.status_code == 200
    payload = res.json()
    assert payload["supporting_document_ref"] == "DOC-123"


def test_counterparties_requires_auth(unauthed_client):
    r = unauthed_client.get("/counterparties")
    assert r.status_code == 401
