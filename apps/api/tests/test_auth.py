from datetime import datetime, timedelta, timezone

import pytest
from jose import jwt
from starlette.exceptions import HTTPException as StarletteHTTPException

from app.auth.jwt import decode_token
from app.config import settings


def test_decode_valid_hs256_token():
    token = jwt.encode(
        {"sub": "user-1", "email": "user@example.com"},
        settings.jwt_secret,
        algorithm="HS256",
    )
    payload = decode_token(token)
    assert payload["sub"] == "user-1"


def test_decode_expired_token():
    exp = datetime.now(timezone.utc) - timedelta(seconds=5)
    token = jwt.encode(
        {"sub": "user-1", "exp": int(exp.timestamp())},
        settings.jwt_secret,
        algorithm="HS256",
    )
    with pytest.raises(StarletteHTTPException):
        decode_token(token)


def test_decode_malformed_token():
    with pytest.raises(StarletteHTTPException):
        decode_token("not-a-token")
