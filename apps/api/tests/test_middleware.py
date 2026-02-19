import pytest


@pytest.mark.asyncio
async def test_request_logging_middleware(authed_client):
    """Verify the logging middleware does not break requests."""
    response = authed_client.get("/health")
    assert response.status_code == 200


@pytest.mark.asyncio
async def test_error_handler_not_found(authed_client):
    """Verify 404 for non-existent routes."""
    response = authed_client.get("/nonexistent-route-xyz")
    assert response.status_code in (404, 405)


@pytest.mark.asyncio
async def test_error_handler_returns_json(authed_client):
    """Verify error responses are JSON."""
    response = authed_client.get("/regions/00000000-0000-0000-0000-000000000000")
    assert response.headers.get("content-type", "").startswith("application/json")
