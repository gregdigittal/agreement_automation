'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface WorkflowTemplate {
  id: string;
  name: string;
  status: string;
  version: number;
  contract_type: string;
}

export function WorkflowTemplatesList() {
  const [templates, setTemplates] = useState<WorkflowTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/workflow-templates')
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setTemplates)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <p className="text-muted-foreground">Loading…</p>;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">Workflow templates</p>
        <Button asChild>
          <Link href="/workflows/new">New template</Link>
        </Button>
      </div>
      {error && <p className="text-sm text-destructive">Error: {error}</p>}
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Version</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {templates.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="text-sm text-muted-foreground">
                No workflow templates yet.
              </TableCell>
            </TableRow>
          ) : (
            templates.map((t) => (
              <TableRow key={t.id}>
                <TableCell>{t.name}</TableCell>
                <TableCell>{t.status}</TableCell>
                <TableCell>{t.version}</TableCell>
                <TableCell>{t.contract_type}</TableCell>
                <TableCell>
                  <Link href={`/workflows/${t.id}`} className="text-primary text-sm hover:underline">
                    Edit
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
