import NextAuth from 'next-auth';
import MicrosoftEntraID from 'next-auth/providers/microsoft-entra-id';
import CredentialsProvider from 'next-auth/providers/credentials';

const providers = [];
if (process.env.AZURE_AD_CLIENT_ID && process.env.AZURE_AD_CLIENT_SECRET) {
  providers.push(
    MicrosoftEntraID({
      clientId: process.env.AZURE_AD_CLIENT_ID,
      clientSecret: process.env.AZURE_AD_CLIENT_SECRET,
      issuer: process.env.AZURE_AD_ISSUER,
    })
  );
}
if (process.env.NODE_ENV === 'development') {
  providers.push(
    CredentialsProvider({
      name: 'Credentials',
      credentials: { email: { label: 'Email', type: 'email' } },
      async authorize(credentials) {
        if (!credentials?.email) return null;
        const email = credentials.email as string;
        return {
          id: `dev-${email.split('@')[0]}`,
          email,
          name: email,
          roles: ['System Admin'],
        };
      },
    })
  );
}

export const { handlers, signIn, signOut, auth } = NextAuth({
  providers,
  callbacks: {
    async jwt({ token, user, account, profile }) {
      if (user) {
        token.id = user.id;
        if (user.email) token.email = user.email;
        // Dev Credentials: roles come from the user object
        if ('roles' in user && Array.isArray(user.roles)) {
          token.roles = user.roles;
        }
      }
      if (account?.access_token) token.accessToken = account.access_token;
      // Azure AD: roles come from the ID token claims
      if (profile && 'roles' in profile && Array.isArray(profile.roles)) {
        token.roles = profile.roles as string[];
      }
      return token;
    },
    async session({ session, token }) {
      if (session.user) {
        const userId = token.id ?? token.sub;
        session.user.id = typeof userId === 'string' ? userId : '';
        session.user.roles = (token.roles as string[]) ?? [];
        const accessToken = token.accessToken;
        if (typeof accessToken === 'string') session.accessToken = accessToken;
      }
      return session;
    },
  },
  pages: { signIn: '/login' },
  session: { strategy: 'jwt', maxAge: 30 * 24 * 60 * 60 },
});
