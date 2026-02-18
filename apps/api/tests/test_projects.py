def test_projects_crud(authed_client):
    create = authed_client.post(
        "/projects",
        json={"entityId": "22222222-2222-2222-2222-222222222222", "name": "Project Two", "code": "P2"},
    )
    assert create.status_code == 200
    project_id = create.json()["id"]

    list_res = authed_client.get("/projects")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    filtered = authed_client.get("/projects?entityId=22222222-2222-2222-2222-222222222222")
    assert filtered.status_code == 200

    get_res = authed_client.get(f"/projects/{project_id}")
    assert get_res.status_code == 200
    assert get_res.json()["name"] == "Project Two"

    update_res = authed_client.patch(f"/projects/{project_id}", json={"name": "Project Two Updated"})
    assert update_res.status_code == 200

    delete_res = authed_client.delete(f"/projects/{project_id}")
    assert delete_res.status_code == 200


def test_projects_not_found(authed_client):
    res = authed_client.get("/projects/cccccccc-cccc-cccc-cccc-cccccccccccc")
    assert res.status_code == 404


def test_projects_requires_auth(unauthed_client):
    r = unauthed_client.get("/projects")
    assert r.status_code == 401


def test_projects_requires_system_admin(legal_client):
    r = legal_client.post(
        "/projects",
        json={"entityId": "22222222-2222-2222-2222-222222222222", "name": "Nope"},
    )
    assert r.status_code == 403
