'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import Link from 'next/link';
import type { Project } from '@/lib/types';

export function ProjectsList() {
  const [list, setList] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    fetch('/api/ccrs/projects')
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
  if (list.length === 0) return <p className="text-muted-foreground">No projects. Create an entity first.</p>;
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {list.map((p) => (
        <Card key={p.id}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">{p.name}</CardTitle>
            <Button variant="outline" size="sm" asChild><Link href={`/projects/${p.id}`}>Edit</Link></Button>
          </CardHeader>
          <CardContent>
            {p.code && <p className="text-sm text-muted-foreground">Code: {p.code}</p>}
            {p.entities && <p className="text-sm text-muted-foreground">Entity: {p.entities.name}</p>}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
