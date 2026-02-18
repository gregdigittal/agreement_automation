def test_regions_crud(authed_client):
    create = authed_client.post("/regions", json={"name": "West", "code": "W"})
    assert create.status_code == 200
    region_id = create.json()["id"]

    list_res = authed_client.get("/regions")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    get_res = authed_client.get(f"/regions/{region_id}")
    assert get_res.status_code == 200
    assert get_res.json()["name"] == "West"

    update_res = authed_client.patch(f"/regions/{region_id}", json={"name": "West-1"})
    assert update_res.status_code == 200
    assert update_res.json()["name"] == "West-1"

    delete_res = authed_client.delete(f"/regions/{region_id}")
    assert delete_res.status_code == 200


def test_regions_not_found(authed_client):
    res = authed_client.get("/regions/cccccccc-cccc-cccc-cccc-cccccccccccc")
    assert res.status_code == 404


def test_regions_list_requires_auth(unauthed_client):
    r = unauthed_client.get("/regions")
    assert r.status_code == 401


def test_regions_requires_system_admin(legal_client):
    r = legal_client.post("/regions", json={"name": "Nope"})
    assert r.status_code == 403
