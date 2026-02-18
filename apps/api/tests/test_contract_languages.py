def test_contract_languages_crud(authed_client, mock_supabase):
    contract_id = "77777777-7777-7777-7777-777777777777"
    res = authed_client.post(
        f"/contracts/{contract_id}/languages",
        data={"language_code": "fr", "is_primary": "false"},
        files={"file": ("contract-fr.pdf", b"data", "application/pdf")},
    )
    assert res.status_code == 201
    lang_id = res.json()["id"]

    res = authed_client.get(f"/contracts/{contract_id}/languages")
    assert res.status_code == 200

    res = authed_client.delete(f"/contract-languages/{lang_id}")
    assert res.status_code == 200
    assert all(
        lang.get("id") != lang_id for lang in mock_supabase.data["contract_languages"]
    )


def test_contract_languages_duplicate_code(authed_client):
    contract_id = "77777777-7777-7777-7777-777777777777"
    res = authed_client.post(
        f"/contracts/{contract_id}/languages",
        data={"language_code": "en", "is_primary": "false"},
        files={"file": ("contract-en.pdf", b"data", "application/pdf")},
    )
    assert res.status_code == 400


def test_contract_languages_invalid_type(authed_client):
    contract_id = "77777777-7777-7777-7777-777777777777"
    res = authed_client.post(
        f"/contracts/{contract_id}/languages",
        data={"language_code": "de", "is_primary": "false"},
        files={"file": ("contract-de.txt", b"data", "text/plain")},
    )
    assert res.status_code == 400
