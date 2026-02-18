"use client";

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface WikiContract {
  id: string;
  name: string;
  category: string | null;
  status: string;
  version: number;
}

export function WikiContractsList() {
  const [items, setItems] = useState<WikiContract[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/wiki-contracts')
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setItems)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <p className="text-muted-foreground">Loading…</p>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">Templates and precedents</p>
        <Button asChild>
          <Link href="/wiki-contracts/new">New template</Link>
        </Button>
      </div>
      {error && <p className="text-sm text-destructive">Error: {error}</p>}
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Version</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="text-sm text-muted-foreground">
                No templates yet.
              </TableCell>
            </TableRow>
          ) : (
            items.map((w) => (
              <TableRow key={w.id}>
                <TableCell>{w.name}</TableCell>
                <TableCell>{w.category ?? '—'}</TableCell>
                <TableCell>{w.status}</TableCell>
                <TableCell>{w.version}</TableCell>
                <TableCell>
                  <Link href={`/wiki-contracts/${w.id}`} className="text-primary text-sm hover:underline">
                    View
                  </Link>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
}
