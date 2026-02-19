'use client';

import type { ReactNode } from 'react';
import { useRoles } from '@/hooks/use-roles';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface RoleGuardProps {
  children: ReactNode;
  allowed: string[];
  fallback?: ReactNode;
}

export function RoleGuard({ children, allowed, fallback }: RoleGuardProps) {
  const { hasRole, roles } = useRoles();

  if (roles.length === 0) return null;

  if (!hasRole(...allowed)) {
    return (
      fallback ?? (
        <Card>
          <CardHeader>
            <CardTitle>Access Denied</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">
              You do not have permission to view this page. Required role:{' '}
              {allowed.join(' or ')}.
            </p>
          </CardContent>
        </Card>
      )
    );
  }

  return <>{children}</>;
}
