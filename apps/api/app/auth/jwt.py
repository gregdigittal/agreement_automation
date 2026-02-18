import httpx
import time as _time
from jose import ExpiredSignatureError, JWTError, jwk, jwt
from starlette.exceptions import HTTPException as StarletteHTTPException

from app.config import settings


_jwks_cache: dict[str, dict] = {}
_jwks_cache_ttl = 3600


def _get_azure_jwks(issuer: str) -> dict:
    now = _time.monotonic()
    cached = _jwks_cache.get(issuer)
    if cached and now - cached["fetched_at"] < _jwks_cache_ttl:
        return cached["keys"]

    config_url = f"{issuer}/.well-known/openid-configuration"
    config = httpx.get(config_url, timeout=10).json()
    jwks = httpx.get(config["jwks_uri"], timeout=10).json()
    _jwks_cache[issuer] = {"keys": jwks, "fetched_at": now}
    return jwks


def decode_token(token: str) -> dict:
    try:
        payload = jwt.decode(
            token,
            settings.jwt_secret,
            algorithms=["HS256"],
            options={"verify_aud": False},
        )
        return payload
    except (JWTError, ExpiredSignatureError):
        pass

    if settings.azure_ad_client_id and settings.azure_ad_issuer:
        try:
            jwks = _get_azure_jwks(settings.azure_ad_issuer)
            unverified = jwt.get_unverified_header(token)
            key_data = next(k for k in jwks["keys"] if k["kid"] == unverified["kid"])
            key = jwk.construct(key_data)
            payload = jwt.decode(
                token,
                key.to_pem().decode("utf-8"),
                algorithms=["RS256"],
                audience=settings.azure_ad_client_id,
                issuer=settings.azure_ad_issuer,
            )
            return payload
        except (JWTError, ExpiredSignatureError, StopIteration):
            pass

    raise StarletteHTTPException(status_code=401, detail="Invalid or expired token")
