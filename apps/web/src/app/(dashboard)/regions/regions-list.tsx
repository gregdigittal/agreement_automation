'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Link from 'next/link';

interface Region {
  id: string;
  name: string;
  code: string | null;
}

export function RegionsList() {
  const [list, setList] = useState<Region[]>([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/api/ccrs/regions')
      .then((r) => r.json())
      .then(setList)
      .finally(() => setLoading(false));
  }, []);
  if (loading) return <p className="text-muted-foreground">Loading…</p>;
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
