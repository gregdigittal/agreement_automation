import { DefaultSession, DefaultJWT, DefaultUser } from 'next-auth';

declare module 'next-auth' {
  interface User extends DefaultUser {
    roles?: string[];
  }

  interface Session {
    user: {
      id: string;
      roles: string[];
    } & DefaultSession['user'];
    accessToken?: string;
  }
}

declare module 'next-auth/jwt' {
  interface JWT extends DefaultJWT {
    id?: string;
    roles?: string[];
    accessToken?: string;
  }
}
