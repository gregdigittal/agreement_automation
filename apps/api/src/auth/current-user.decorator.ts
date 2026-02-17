import { createParamDecorator, ExecutionContext } from '@nestjs/common';

export interface CurrentUserPayload {
  id?: string;
  email?: string;
  roles?: string[];
}

export const CurrentUser = createParamDecorator(
  (data: unknown, ctx: ExecutionContext): CurrentUserPayload | undefined => {
    const request = ctx.switchToHttp().getRequest<{ user?: CurrentUserPayload }>();
    return request.user;
  },
);
