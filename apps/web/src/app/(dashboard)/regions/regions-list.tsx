'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
import type { Region } from '@/lib/types';

export function RegionsList() {
  const [list, setList] = useState<Region[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    fetch('/api/ccrs/regions')
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
  if (list.length === 0) return <p className="text-muted-foreground">No regions. Create one to get started.</p>;
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {list.map((r) => (
        <Card key={r.id}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">{r.name}</CardTitle>
            <Button variant="outline" size="sm" asChild>
              <Link href={`/regions/${r.id}`}>Edit</Link>
            </Button>
          </CardHeader>
          <CardContent>
            {r.code && <p className="text-sm text-muted-foreground">Code: {r.code}</p>}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
