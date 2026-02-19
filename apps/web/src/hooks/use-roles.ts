'use client';

import { useSession } from 'next-auth/react';

export function useRoles() {
  const { data: session } = useSession();
  const roles: string[] = session?.user?.roles ?? [];

  return {
    roles,
    hasRole: (...allowed: string[]) => allowed.some((r) => roles.includes(r)),
    isAdmin: roles.includes('System Admin'),
    isLegal: roles.includes('Legal'),
    isAdminOrLegal: roles.includes('System Admin') || roles.includes('Legal'),
  };
}
