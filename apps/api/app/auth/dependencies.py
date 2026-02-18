from fastapi import Depends, HTTPException, Request
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from app.auth.jwt import decode_token
from app.auth.models import CurrentUser

security = HTTPBearer()


async def get_current_user(
    request: Request,
    credentials: HTTPAuthorizationCredentials = Depends(security),
) -> CurrentUser:
    payload = decode_token(credentials.credentials)
    user_id = payload.get("oid") or payload.get("sub")
    if not user_id:
        raise HTTPException(status_code=401, detail="Invalid token: no subject")
    user = CurrentUser(
        id=str(user_id),
        email=payload.get("email") or payload.get("preferred_username"),
        roles=payload.get("roles") or [],
        ip_address=request.client.host if request.client else None,
    )
    request.state.user_id = user.id
    return user


def require_roles(*allowed_roles: str):
    async def check_roles(user: CurrentUser = Depends(get_current_user)) -> CurrentUser:
        if not any(r in user.roles for r in allowed_roles):
            from fastapi import HTTPException

            raise HTTPException(
                status_code=403,
                detail=f"Requires one of: {', '.join(allowed_roles)}",
            )
        return user

    return check_roles
