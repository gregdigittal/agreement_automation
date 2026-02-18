def test_wiki_contracts_crud(authed_client):
    create = authed_client.post(
        "/wiki-contracts",
        json={"name": "New Template", "category": "Commercial"},
    )
    assert create.status_code == 200
    template_id = create.json()["id"]

    list_res = authed_client.get("/wiki-contracts")
    assert list_res.status_code == 200
    assert "X-Total-Count" in list_res.headers

    get_res = authed_client.get(f"/wiki-contracts/{template_id}")
    assert get_res.status_code == 200

    update = authed_client.patch(f"/wiki-contracts/{template_id}", json={"description": "Updated"})
    assert update.status_code == 200

    publish = authed_client.patch(f"/wiki-contracts/{template_id}/publish")
    assert publish.status_code == 200

    files = {"file": ("template.docx", b"docx", "application/vnd.openxmlformats-officedocument.wordprocessingml.document")}
    upload = authed_client.post(f"/wiki-contracts/{template_id}/upload", files=files)
    assert upload.status_code == 200

    download = authed_client.get(f"/wiki-contracts/{template_id}/download-url")
    assert download.status_code == 200
