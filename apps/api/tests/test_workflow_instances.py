def test_workflow_instance_lifecycle(client_context):
    with client_context(["Legal"]) as legal_client:
        start = legal_client.post(
            "/contracts/77777777-7777-7777-7777-777777777777/workflow",
            json={"templateId": "dddddddd-dddd-dddd-dddd-dddddddddddd"},
        )
        assert start.status_code == 200
        instance_id = start.json()["id"]

        approve = legal_client.post(
            f"/workflow-instances/{instance_id}/stages/Review/action",
            json={"action": "approve"},
        )
        assert approve.status_code == 200

        status = legal_client.get("/contracts/77777777-7777-7777-7777-777777777777/workflow")
        assert status.status_code == 200
        assert status.json()["current_stage"] == "Sign"

    with client_context(["Operations"]) as basic_client:
        denied = basic_client.post(
            f"/workflow-instances/{instance_id}/stages/Sign/action",
            json={"action": "approve"},
        )
        assert denied.status_code == 403

    with client_context(["Legal"]) as legal_client:
        approve_sign = legal_client.post(
            f"/workflow-instances/{instance_id}/stages/Sign/action",
            json={"action": "approve"},
        )
        assert approve_sign.status_code == 200

        done = legal_client.get("/contracts/77777777-7777-7777-7777-777777777777/workflow")
        assert done.status_code == 404
