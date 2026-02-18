'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
import { Badge } from '@/components/ui/badge';
import type { Counterparty } from '@/lib/types';

export function CounterpartiesList() {
  const [list, setList] = useState<Counterparty[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    fetch('/api/ccrs/counterparties')
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setList)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);
  if (loading) return <p className="text-muted-foreground">Loading…</p>;
  if (error) return <p className="text-sm text-destructive">Error: {error}</p>;
  if (list.length === 0) return <p className="text-muted-foreground">No counterparties yet.</p>;
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {list.map((c) => (
        <Card key={c.id}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">{c.legal_name}</CardTitle>
            <Badge variant={c.status === 'Active' ? 'default' : 'secondary'}>{c.status}</Badge>
          </CardHeader>
          <CardContent>
            {c.registration_number && <p className="text-sm text-muted-foreground">Reg: {c.registration_number}</p>}
            <Button variant="outline" size="sm" asChild className="mt-2">
              <Link href={`/counterparties/${c.id}`}>View / Edit</Link>
            </Button>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
