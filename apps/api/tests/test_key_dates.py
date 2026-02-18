def test_key_dates_crud(client_context):
    with client_context(["Legal"]) as legal_client:
        create = legal_client.post(
            "/contracts/77777777-7777-7777-7777-777777777777/key-dates",
            json={"date_type": "expiry_date", "date_value": "2026-12-31"},
        )
        assert create.status_code == 200
        key_id = create.json()["id"]

        listing = legal_client.get("/contracts/77777777-7777-7777-7777-777777777777/key-dates")
        assert listing.status_code == 200

        verify = legal_client.patch(f"/key-dates/{key_id}/verify")
        assert verify.status_code == 200

    with client_context(["System Admin"]) as admin_client:
        delete = admin_client.delete(f"/key-dates/{key_id}")
        assert delete.status_code == 200
