def test_send_to_sign(authed_client, mock_supabase):
    mock_supabase.data["contracts"][0]["workflow_state"] = "signing"
    res = authed_client.post("/contracts/77777777-7777-7777-7777-777777777777/send-to-sign")
    assert res.status_code == 200


def test_boldsign_webhook_updates_contract(authed_client, mock_supabase):
    mock_supabase.data["boldsign_envelopes"].append(
        {
            "id": "env-1",
            "contract_id": "77777777-7777-7777-7777-777777777777",
            "boldsign_document_id": "doc-1",
            "status": "sent",
        }
    )
    res = authed_client.post("/webhooks/boldsign", json={"document_id": "doc-1", "status": "completed"})
    assert res.status_code == 200
    contract = next(c for c in mock_supabase.data["contracts"] if c["id"] == "77777777-7777-7777-7777-777777777777")
    assert contract["signing_status"] == "completed"
    assert contract["workflow_state"] == "executed"
