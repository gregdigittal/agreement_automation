from app.workflows.state_machine import validate_template


def _mock_anthropic(monkeypatch, payloads):
    class MockMessages:
        def __init__(self):
            self.calls = 0

        def create(self, **_kwargs):
            payload = payloads[min(self.calls, len(payloads) - 1)]
            self.calls += 1
            return type(
                "Resp",
                (),
                {
                    "content": [type("Block", (), {"text": payload})()],
                    "usage": type("Usage", (), {"input_tokens": 10, "output_tokens": 5})(),
                    "stop_reason": "end_turn",
                },
            )()

    class MockAnthropic:
        def __init__(self, **_kwargs):
            self.messages = MockMessages()

    monkeypatch.setattr("anthropic.Anthropic", MockAnthropic)


def test_generate_workflow_valid(authed_client, monkeypatch):
    payload = """
{"stages":[
  {"name":"draft","type":"draft","description":"Draft","owners":["Legal"],"approvers":[],"required_artifacts":[],"allowed_transitions":["review"],"sla_hours":24},
  {"name":"review","type":"approval","description":"Review","owners":["Legal"],"approvers":["Legal"],"required_artifacts":[],"allowed_transitions":["sign"],"sla_hours":24},
  {"name":"sign","type":"signing","description":"Sign","owners":["Legal"],"approvers":["Legal"],"required_artifacts":[],"allowed_transitions":[],"sla_hours":24}
],"explanation":"Test workflow","confidence":0.8}
""".strip()
    _mock_anthropic(monkeypatch, [payload])

    res = authed_client.post("/workflows/generate", json={"description": "Simple flow"})
    assert res.status_code == 200
    data = res.json()
    errors = validate_template([type("S", (), s)() for s in data["stages"]])
    assert errors == []


def test_generate_workflow_self_correction(monkeypatch, authed_client):
    bad = """
{"stages":[
  {"name":"draft","type":"draft","description":"Draft","owners":["Legal"],"approvers":[],"required_artifacts":[],"allowed_transitions":[],"sla_hours":24}
],"explanation":"Bad","confidence":0.2}
""".strip()
    good = """
{"stages":[
  {"name":"draft","type":"draft","description":"Draft","owners":["Legal"],"approvers":[],"required_artifacts":[],"allowed_transitions":["review"],"sla_hours":24},
  {"name":"review","type":"approval","description":"Review","owners":["Legal"],"approvers":["Legal"],"required_artifacts":[],"allowed_transitions":["sign"],"sla_hours":24},
  {"name":"sign","type":"signing","description":"Sign","owners":["Legal"],"approvers":["Legal"],"required_artifacts":[],"allowed_transitions":[],"sla_hours":24}
],"explanation":"Fixed","confidence":0.7}
""".strip()
    _mock_anthropic(monkeypatch, [bad, good])

    res = authed_client.post("/workflows/generate", json={"description": "Needs approval and signing"})
    assert res.status_code == 200
    assert res.json()["validation_errors"] == []


def test_generate_workflow_requires_admin(client_context):
    with client_context(["Legal"]) as client:
        res = client.post("/workflows/generate", json={"description": "Simple"})
        assert res.status_code == 403
