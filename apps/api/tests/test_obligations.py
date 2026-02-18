def test_obligations_crud(client_context):
    contract_id = "77777777-7777-7777-7777-777777777777"
    with client_context(["System Admin"]) as admin_client:
        res = admin_client.post(
            f"/contracts/{contract_id}/obligations",
            json={
                "obligation_type": "payment",
                "description": "Monthly fee",
                "due_date": "2026-03-01",
                "recurrence": "monthly",
            },
        )
        assert res.status_code == 201
        created = res.json()

        res = admin_client.get(f"/contracts/{contract_id}/obligations")
        assert res.status_code == 200
        assert res.headers.get("X-Total-Count") is not None

    with client_context(["Legal"]) as legal_client:
        res = legal_client.patch(f"/obligations/{created['id']}", json={"status": "completed"})
        assert res.status_code == 200
        assert res.json()["status"] == "completed"

    with client_context(["System Admin"]) as admin_client:
        res = admin_client.delete(f"/obligations/{created['id']}")
        assert res.status_code == 200


def test_obligations_filters(authed_client):
    res = authed_client.get("/obligations?status=active&obligation_type=sla")
    assert res.status_code == 200
