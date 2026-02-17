'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Link from 'next/link';

interface Contract {
  id: string;
  title: string | null;
  contract_type: string;
  workflow_state: string;
  created_at: string;
}

export function ContractsList() {
  const [list, setList] = useState<Contract[]>([]);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch('/api/ccrs/contracts').then((r) => r.json()).then(setList).finally(() => setLoading(false));
  }, []);
  if (loading) return <p className="text-muted-foreground">Loading…</p>;
  if (list.length === 0) return <p className="text-muted-foreground">No contracts yet. Upload one to get started.</p>;
  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {list.map((c) => (
        <Card key={c.id}>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-base">{c.title ?? 'Untitled'}</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">{c.contract_type} — {c.workflow_state}</p>
            <Link href={`/contracts/${c.id}`} className="text-primary text-sm hover:underline mt-2 inline-block">View details →</Link>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
