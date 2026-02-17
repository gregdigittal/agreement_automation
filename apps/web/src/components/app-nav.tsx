'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { signOut } from 'next-auth/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const nav = [
  { href: '/', label: 'Dashboard' },
  { href: '/regions', label: 'Regions' },
  { href: '/entities', label: 'Entities' },
  { href: '/projects', label: 'Projects' },
  { href: '/counterparties', label: 'Counterparties' },
  { href: '/contracts', label: 'Contracts' },
  { href: '/audit', label: 'Audit' },
];

export function AppNav() {
  const pathname = usePathname();
  return (
    <nav className="flex items-center gap-2 border-b bg-card px-4 py-2">
      <Link href="/" className="mr-4 font-semibold">
        CCRS
      </Link>
      {nav.map(({ href, label }) => (
        <Link key={href} href={href}>
          <Button variant={pathname === href ? 'secondary' : 'ghost'} size="sm" className={cn(pathname === href && 'font-medium')}>
            {label}
          </Button>
        </Link>
      ))}
      <div className="ml-auto">
        <Button variant="ghost" size="sm" onClick={() => signOut({ callbackUrl: '/login' })}>
          Sign out
        </Button>
      </div>
    </nav>
  );
}
