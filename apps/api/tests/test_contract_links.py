def test_contract_links_crud(authed_client):
    amend = authed_client.post("/contracts/77777777-7777-7777-7777-777777777777/amendments")
    assert amend.status_code == 200
    renewal = authed_client.post(
        "/contracts/77777777-7777-7777-7777-777777777777/renewals",
        json={"type": "new_version"},
    )
    assert renewal.status_code == 200
    side = authed_client.post("/contracts/77777777-7777-7777-7777-777777777777/side-letters")
    assert side.status_code == 200

    linked = authed_client.get("/contracts/77777777-7777-7777-7777-777777777777/linked")
    assert linked.status_code == 200
    data = linked.json()
    assert len(data["amendment"]) >= 1
    assert len(data["renewal"]) >= 1
    assert len(data["side_letter"]) >= 1
