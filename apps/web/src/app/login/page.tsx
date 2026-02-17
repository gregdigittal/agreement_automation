'use client';

import { signIn } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useState } from 'react';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/30 p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>CCRS</CardTitle>
          <CardDescription>Contract &amp; Merchant Agreement Repository</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {process.env.NEXT_PUBLIC_AZURE_AD_ENABLED === 'true' ? (
            <Button
              className="w-full"
              variant="default"
              onClick={() => signIn('azure-ad', { callbackUrl: '/' })}
            >
              Sign in with Microsoft
            </Button>
          ) : (
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                signIn('credentials', { email, password, callbackUrl: '/' });
              }}
            >
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  placeholder="dev@example.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <Input
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
              </div>
              <Button type="submit" className="w-full">
                Sign in (Dev)
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
