import pytest
from app.ai.schemas import AnalysisUsage, SummaryResult


@pytest.fixture
def mock_anthropic(monkeypatch):
    class MockMessages:
        def __init__(self):
            self.calls = 0

        def create(self, **_kwargs):
            self.calls += 1
            return type(
                "Resp",
                (),
                {
                    "content": [type("Block", (), {"text": '{"summary": "Test summary", "key_parties": []}'})()],
                    "usage": type("Usage", (), {"input_tokens": 100, "output_tokens": 50})(),
                    "stop_reason": "end_turn",
                },
            )()

    class MockAnthropic:
        def __init__(self, **_kwargs):
            self.messages = MockMessages()

    monkeypatch.setattr("anthropic.Anthropic", MockAnthropic)
    return MockAnthropic


def test_trigger_summary_analysis(authed_client, monkeypatch):
    async def fake_summary(_text):
        return SummaryResult(summary="Test summary", key_parties=[]), AnalysisUsage(input_tokens=10, output_tokens=5)

    monkeypatch.setattr("app.ai_analysis.service.analyze_summary", fake_summary)
    res = authed_client.post(
        "/contracts/77777777-7777-7777-7777-777777777777/analyze",
        json={"analysisType": "summary"},
    )
    assert res.status_code == 200
    assert res.json()["status"] == "completed"


def test_trigger_extraction_analysis_saves_fields(authed_client, monkeypatch, mock_supabase):
    async def fake_complex(_analysis_type, _text, _contract_id, _supabase):
        return {"fields": [{"field_name": "term", "field_value": "12 months"}]}, AnalysisUsage()

    monkeypatch.setattr("app.ai_analysis.service.analyze_complex", fake_complex)
    res = authed_client.post(
        "/contracts/77777777-7777-7777-7777-777777777777/analyze",
        json={"analysisType": "extraction"},
    )
    assert res.status_code == 200
    fields = mock_supabase.data["ai_extracted_fields"]
    assert any(f["field_name"] == "term" for f in fields)


def test_trigger_obligations_analysis_saves_register(authed_client, monkeypatch, mock_supabase):
    async def fake_complex(_analysis_type, _text, _contract_id, _supabase):
        return {"obligations": [{"obligation_type": "sla", "description": "Monthly report"}]}, AnalysisUsage()

    monkeypatch.setattr("app.ai_analysis.service.analyze_complex", fake_complex)
    res = authed_client.post(
        "/contracts/77777777-7777-7777-7777-777777777777/analyze",
        json={"analysisType": "obligations"},
    )
    assert res.status_code == 200
    assert any(o["obligation_type"] == "sla" for o in mock_supabase.data["obligations_register"])


def test_verify_and_correct_field(authed_client, mock_supabase):
    field_id = mock_supabase.data["ai_extracted_fields"][0]["id"]
    res = authed_client.post(f"/ai-fields/{field_id}/verify", json={})
    assert res.status_code == 200
    res = authed_client.patch(f"/ai-fields/{field_id}", json={"field_value": "Updated"})
    assert res.status_code == 200


def test_contract_without_file_returns_400(authed_client, mock_supabase):
    mock_supabase.data["contracts"].append(
        {
            "id": "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee",
            "region_id": mock_supabase.data["regions"][0]["id"],
            "entity_id": mock_supabase.data["entities"][0]["id"],
            "project_id": mock_supabase.data["projects"][0]["id"],
            "counterparty_id": mock_supabase.data["counterparties"][0]["id"],
            "contract_type": "Commercial",
            "title": "No file",
            "workflow_state": "draft",
            "storage_path": None,
            "file_name": None,
        }
    )
    res = authed_client.post(
        "/contracts/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/analyze",
        json={"analysisType": "summary"},
    )
    assert res.status_code == 400


def test_budget_exceeded_still_completes(authed_client, monkeypatch):
    async def fake_complex(_analysis_type, _text, _contract_id, _supabase):
        return {"risks": []}, AnalysisUsage(cost_usd=999)

    monkeypatch.setattr("app.ai_analysis.service.analyze_complex", fake_complex)
    res = authed_client.post(
        "/contracts/77777777-7777-7777-7777-777777777777/analyze",
        json={"analysisType": "risk"},
    )
    assert res.status_code == 200
    assert res.json()["status"] == "completed"


def test_unauthenticated_request(unauthed_client):
    res = unauthed_client.post(
        "/contracts/77777777-7777-7777-7777-777777777777/analyze",
        json={"analysisType": "summary"},
    )
    assert res.status_code == 401
