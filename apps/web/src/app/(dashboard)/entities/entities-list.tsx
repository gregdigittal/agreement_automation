'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
import type { Entity } from '@/lib/types';

export function EntitiesList() {
  const [list, setList] = useState<Entity[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    fetch('/api/ccrs/entities')
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
  if (list.length === 0) return <p className="text-muted-foreground">No entities. Create a region first, then add entities.</p>;
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {list.map((e) => (
        <Card key={e.id}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">{e.name}</CardTitle>
            <Button variant="outline" size="sm" asChild><Link href={`/entities/${e.id}`}>Edit</Link></Button>
          </CardHeader>
          <CardContent>
            {e.code && <p className="text-sm text-muted-foreground">Code: {e.code}</p>}
            {e.regions && <p className="text-sm text-muted-foreground">Region: {e.regions.name}</p>}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
