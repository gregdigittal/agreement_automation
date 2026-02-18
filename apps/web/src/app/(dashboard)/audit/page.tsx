'use client';

import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface AuditEntry {
  id: string;
  at: string;
  action: string;
  resource_type: string;
  resource_id: string | null;
  actor_email: string | null;
  actor_id: string | null;
  ip_address: string | null;
  details: Record<string, unknown> | null;
}

const PAGE_SIZE = 25;

export default function AuditPage() {
  const [entries, setEntries] = useState<AuditEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [resourceType, setResourceType] = useState('');
  const [actorId, setActorId] = useState('');
  const [offset, setOffset] = useState(0);

  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  const totalCount = entries.length;
  const pagedEntries = useMemo(() => entries.slice(offset, offset + PAGE_SIZE), [entries, offset]);

  function buildParams() {
    const params = new URLSearchParams();
    if (fromDate) params.set('from', new Date(`${fromDate}T00:00:00Z`).toISOString());
    if (toDate) params.set('to', new Date(`${toDate}T23:59:59Z`).toISOString());
    if (resourceType) {
      params.set('resourceType', resourceType);
      params.set('resource_type', resourceType);
    }
    if (actorId) {
      params.set('actorId', actorId);
      params.set('actor_id', actorId);
    }
    params.set('limit', '10000');
    return params;
  }

  function toggleExpanded(id: string) {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  async function loadAudit() {
    setLoading(true);
    setError(null);
    try {
      const params = buildParams();
      const res = await fetch(`/api/ccrs/audit/export?${params.toString()}`);
      if (!res.ok) throw new Error(`${res.status}`);
      const data = (await res.json()) as AuditEntry[];
      setEntries(data);
      setOffset(0);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function exportJson() {
    const params = buildParams();
    const res = await fetch(`/api/ccrs/audit/export?${params.toString()}`);
    if (!res.ok) {
      setError(`${res.status}`);
      return;
    }
    const blob = new Blob([await res.text()], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit-export-${new Date().toISOString().slice(0, 10)}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }

  useEffect(() => {
    void loadAudit();
  }, []);

  const start = totalCount === 0 ? 0 : offset + 1;
  const end = Math.min(offset + PAGE_SIZE, totalCount);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-2xl font-bold">Audit trail</h1>
        <Button variant="outline" onClick={exportJson}>Export JSON</Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-4">
          <div className="space-y-2">
            <Label htmlFor="from">From</Label>
            <Input id="from" type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="to">To</Label>
            <Input id="to" type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="resourceType">Resource Type</Label>
            <Input id="resourceType" value={resourceType} onChange={(e) => setResourceType(e.target.value)} placeholder="e.g. contract" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="actorId">Actor ID</Label>
            <Input id="actorId" value={actorId} onChange={(e) => setActorId(e.target.value)} placeholder="user id" />
          </div>
          <div className="md:col-span-4 flex items-center gap-2">
            <Button onClick={loadAudit} disabled={loading}>{loading ? 'Loading…' : 'Apply filters'}</Button>
            {error && <p className="text-sm text-destructive">Error: {error}</p>}
          </div>
        </CardContent>
      </Card>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Timestamp</TableHead>
            <TableHead>Action</TableHead>
            <TableHead>Resource Type</TableHead>
            <TableHead>Resource ID</TableHead>
            <TableHead>Actor</TableHead>
            <TableHead>IP</TableHead>
            <TableHead>Details</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {pagedEntries.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="text-sm text-muted-foreground">
                No audit entries found.
              </TableCell>
            </TableRow>
          ) : (
            pagedEntries.map((entry) => (
              <TableRow key={entry.id}>
                <TableCell>{new Date(entry.at).toLocaleString()}</TableCell>
                <TableCell>{entry.action}</TableCell>
                <TableCell>{entry.resource_type}</TableCell>
                <TableCell>{entry.resource_id ?? '—'}</TableCell>
                <TableCell>{entry.actor_email ?? entry.actor_id ?? '—'}</TableCell>
                <TableCell>{entry.ip_address ?? '—'}</TableCell>
                <TableCell>
                  <Button variant="ghost" size="sm" onClick={() => toggleExpanded(entry.id)}>
                    {expanded.has(entry.id) ? 'Hide' : 'View'}
                  </Button>
                  {expanded.has(entry.id) && entry.details && (
                    <pre className="mt-2 max-w-[320px] overflow-auto rounded-md bg-muted p-2 text-xs">
                      {JSON.stringify(entry.details, null, 2)}
                    </pre>
                  )}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          Showing {start}-{end} of {totalCount}
        </p>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" disabled={offset === 0} onClick={() => setOffset(Math.max(0, offset - PAGE_SIZE))}>
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            disabled={offset + PAGE_SIZE >= totalCount}
            onClick={() => setOffset(offset + PAGE_SIZE)}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  );
}
