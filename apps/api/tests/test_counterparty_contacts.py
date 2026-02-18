def test_counterparty_contacts_crud(authed_client):
    create = authed_client.post(
        "/counterparties/44444444-4444-4444-4444-444444444444/contacts",
        json={"name": "Bob", "email": "bob@example.com", "role": "Buyer", "is_signer": False},
    )
    assert create.status_code == 200
    contact_id = create.json()["id"]

    list_res = authed_client.get("/counterparties/44444444-4444-4444-4444-444444444444/contacts")
    assert list_res.status_code == 200
    assert any(c["id"] == contact_id for c in list_res.json())

    update = authed_client.patch(
        f"/counterparty-contacts/{contact_id}",
        json={"role": "Signer", "is_signer": True},
    )
    assert update.status_code == 200

    delete = authed_client.delete(f"/counterparty-contacts/{contact_id}")
    assert delete.status_code == 200
