def test_entities_crud(authed_client):
    create = authed_client.post(
        "/entities",
        json={"regionId": "11111111-1111-1111-1111-111111111111", "name": "Entity Two", "code": "E2"},
    )
    assert create.status_code == 200
    entity_id = create.json()["id"]

    list_res = authed_client.get("/entities")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    filtered = authed_client.get("/entities?regionId=11111111-1111-1111-1111-111111111111")
    assert filtered.status_code == 200

    get_res = authed_client.get(f"/entities/{entity_id}")
    assert get_res.status_code == 200
    assert get_res.json()["name"] == "Entity Two"

    update_res = authed_client.patch(f"/entities/{entity_id}", json={"name": "Entity Two Updated"})
    assert update_res.status_code == 200

    delete_res = authed_client.delete(f"/entities/{entity_id}")
    assert delete_res.status_code == 200


def test_entities_not_found(authed_client):
    res = authed_client.get("/entities/cccccccc-cccc-cccc-cccc-cccccccccccc")
    assert res.status_code == 404


def test_entities_requires_auth(unauthed_client):
    r = unauthed_client.get("/entities")
    assert r.status_code == 401


def test_entities_requires_system_admin(legal_client):
    r = legal_client.post(
        "/entities",
        json={"regionId": "11111111-1111-1111-1111-111111111111", "name": "Nope"},
    )
    assert r.status_code == 403
