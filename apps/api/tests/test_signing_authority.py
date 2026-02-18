def test_signing_authority_crud(authed_client):
    create = authed_client.post(
        "/signing-authority",
        json={
            "entityId": "22222222-2222-2222-2222-222222222222",
            "projectId": None,
            "userId": "user-2",
            "userEmail": "user2@example.com",
            "roleOrName": "Legal",
            "contractTypePattern": None,
        },
    )
    assert create.status_code == 200
    sa_id = create.json()["id"]

    list_res = authed_client.get("/signing-authority")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    get_res = authed_client.get(f"/signing-authority/{sa_id}")
    assert get_res.status_code == 200

    update = authed_client.patch(f"/signing-authority/{sa_id}", json={"roleOrName": "Finance"})
    assert update.status_code == 200

    delete = authed_client.delete(f"/signing-authority/{sa_id}")
    assert delete.status_code == 200


def test_signing_authority_requires_auth(unauthed_client):
    r = unauthed_client.get("/signing-authority")
    assert r.status_code == 401


def test_signing_authority_requires_system_admin(legal_client):
    r = legal_client.post(
        "/signing-authority",
        json={
            "entityId": "22222222-2222-2222-2222-222222222222",
            "projectId": None,
            "userId": "user-3",
            "userEmail": "user3@example.com",
            "roleOrName": "Legal",
            "contractTypePattern": None,
        },
    )
    assert r.status_code == 403
