import { auth } from '@/auth';

export default auth((req) => {
  const isLoggedIn = !!req.auth;
  const isLogin = req.nextUrl.pathname.startsWith('/login');
  if (!isLoggedIn && !isLogin) {
    return Response.redirect(new URL('/login', req.nextUrl));
  }
  if (isLoggedIn && isLogin) {
    return Response.redirect(new URL('/', req.nextUrl));
  }
  return undefined;
});

export const config = { matcher: ['/((?!api|_next/static|_next/image|favicon.ico).*)'] };
