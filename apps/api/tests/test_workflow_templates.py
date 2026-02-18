def test_workflow_templates_crud(authed_client):
    create = authed_client.post(
        "/workflow-templates",
        json={
            "name": "Template A",
            "contractType": "Commercial",
            "stages": [
                {"name": "Review", "type": "approval", "allowed_transitions": ["Sign"]},
                {"name": "Sign", "type": "signing", "allowed_transitions": []},
            ],
        },
    )
    assert create.status_code == 200
    template_id = create.json()["id"]

    list_res = authed_client.get("/workflow-templates")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    get_res = authed_client.get(f"/workflow-templates/{template_id}")
    assert get_res.status_code == 200

    update = authed_client.patch(f"/workflow-templates/{template_id}", json={"name": "Template A+"})
    assert update.status_code == 200

    publish = authed_client.post(f"/workflow-templates/{template_id}/publish")
    assert publish.status_code == 200
    assert publish.json().get("version") == 2


def test_workflow_template_publish_validation(authed_client):
    create = authed_client.post(
        "/workflow-templates",
        json={
            "name": "Invalid Template",
            "contractType": "Commercial",
            "stages": [
                {"name": "Sign", "type": "signing", "allowed_transitions": []},
            ],
        },
    )
    template_id = create.json()["id"]
    publish = authed_client.post(f"/workflow-templates/{template_id}/publish")
    assert publish.status_code == 400
