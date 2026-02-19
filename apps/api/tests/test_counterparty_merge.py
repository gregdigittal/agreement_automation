def test_merge_counterparties(authed_client, mock_supabase):
    """Test merging a duplicate counterparty into the target."""
    cp1 = authed_client.post(
        "/counterparties",
        json={"legalName": "Duplicate Corp"},
    ).json()
    cp2 = authed_client.post(
        "/counterparties",
        json={"legalName": "Original Corp"},
    ).json()

    response = authed_client.post(
        f"/counterparties/{cp2['id']}/merge",
        json={"sourceId": cp1["id"]},
    )
    assert response.status_code == 200


def test_merge_into_self_fails(authed_client, mock_supabase):
    """Test that merging a counterparty into itself fails."""
    cps = authed_client.get("/counterparties").json()
    if not cps:
        return
    cp_id = cps[0]["id"]

    response = authed_client.post(
        f"/counterparties/{cp_id}/merge",
        json={"sourceId": cp_id},
    )
    assert response.status_code == 400


def test_merge_requires_legal_or_admin(client_context):
    """Test that merge requires Legal or System Admin role."""
    with client_context(roles=["Viewer"]) as client:
        response = client.post(
            "/counterparties/00000000-0000-0000-0000-000000000001/merge",
            json={"sourceId": "00000000-0000-0000-0000-000000000002"},
        )
        assert response.status_code == 403
