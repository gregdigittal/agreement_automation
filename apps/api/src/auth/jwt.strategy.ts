import { Injectable, UnauthorizedException } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { ExtractJwt, Strategy } from 'passport-jwt';

export interface JwtPayload {
  sub: string;
  email?: string;
  oid?: string;
  roles?: string[];
  [key: string]: unknown;
}

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy, 'jwt') {
  constructor() {
    const secret = process.env.JWT_SECRET ?? process.env.AZURE_AD_CLIENT_SECRET ?? process.env.NEXTAUTH_SECRET ?? 'ccrs-dev-secret-change-in-production';
    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      ignoreExpiration: false,
      secretOrKey: secret,
      ...(process.env.AZURE_AD_CLIENT_ID && { audience: process.env.AZURE_AD_CLIENT_ID }),
      ...(process.env.AZURE_AD_ISSUER && { issuer: process.env.AZURE_AD_ISSUER }),
    });
  }

  async validate(payload: JwtPayload): Promise<{ id: string; email?: string; roles?: string[] }> {
    if (!payload.sub) throw new UnauthorizedException();
    return {
      id: payload.oid ?? payload.sub,
      email: payload.email ?? (payload as { preferred_username?: string }).preferred_username,
      roles: payload.roles ?? [],
    };
  }
}
