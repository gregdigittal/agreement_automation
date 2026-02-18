from app.reports import service


def test_contract_status_summary(mock_supabase):
    result = service.contract_status_summary(mock_supabase)
    assert "by_state" in result
    assert "by_type" in result


def test_expiry_horizon(mock_supabase):
    result = service.expiry_horizon(mock_supabase)
    assert "buckets" in result
    assert "counts" in result


def test_signing_status_summary(mock_supabase):
    result = service.signing_status_summary(mock_supabase)
    assert result["total"] >= 1


def test_ai_cost_summary(mock_supabase):
    result = service.ai_cost_summary(mock_supabase, period_days=30)
    assert result["total_analyses"] >= 1
