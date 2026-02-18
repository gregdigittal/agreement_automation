def test_generate_merchant_agreement(authed_client):
    res = authed_client.post(
        "/merchant-agreements/generate",
        json={
            "templateId": "cccccccc-cccc-cccc-cccc-cccccccccccc",
            "vendorName": "Vendor A",
            "merchantFee": "2.5%",
            "regionId": "11111111-1111-1111-1111-111111111111",
            "entityId": "22222222-2222-2222-2222-222222222222",
            "projectId": "33333333-3333-3333-3333-333333333333",
            "counterpartyId": "44444444-4444-4444-4444-444444444444",
            "regionTerms": {"notes": "Test"},
        },
    )
    assert res.status_code == 200
    assert res.json()["contract_type"] == "Merchant"


def test_tito_validate_valid(authed_client, monkeypatch):
    from app.config import settings

    monkeypatch.setattr(settings, "tito_api_key", "test-key")
    res = authed_client.get(
        "/tito/validate?entity_id=22222222-2222-2222-2222-222222222222&region_id=11111111-1111-1111-1111-111111111111",
        headers={"X-API-Key": "test-key"},
    )
    assert res.status_code == 200
    assert res.json()["valid"] is True


def test_tito_validate_invalid(authed_client, monkeypatch):
    from app.config import settings

    monkeypatch.setattr(settings, "tito_api_key", "test-key")
    res = authed_client.get(
        "/tito/validate?entity_id=aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa",
        headers={"X-API-Key": "test-key"},
    )
    assert res.status_code == 200
    assert res.json()["valid"] is False
