'use client';

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import Link from 'next/link';
import type { Region } from '@/lib/types';

export function RegionsList() {
  const [list, setList] = useState<Region[]>([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/api/ccrs/regions')
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setList)
      .catch(() => toast.error('Failed to load regions'))
      .finally(() => setLoading(false));
  }, []);
  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
      </div>
    );
  }
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
