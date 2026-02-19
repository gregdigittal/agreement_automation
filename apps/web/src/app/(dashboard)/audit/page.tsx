'use client';

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { RoleGuard } from '@/components/role-guard';
import { handleApiError } from '@/lib/api-error';

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
  const [totalCount, setTotalCount] = useState(0);

  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [resourceType, setResourceType] = useState('');
  const [actorId, setActorId] = useState('');
  const [offset, setOffset] = useState(0);

  const [expanded, setExpanded] = useState<Set<string>>(new Set());

  function buildParams(currentOffset = offset, limit = PAGE_SIZE) {
    const params = new URLSearchParams();
    if (fromDate) params.set('from', new Date(`${fromDate}T00:00:00Z`).toISOString());
    if (toDate) params.set('to', new Date(`${toDate}T23:59:59Z`).toISOString());
    if (resourceType) params.set('resource_type', resourceType);
    if (actorId) params.set('actor_id', actorId);
    params.set('limit', String(limit));
    params.set('offset', String(currentOffset));
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

  async function loadAudit(nextOffset = 0) {
    setLoading(true);
    try {
      const params = buildParams(nextOffset);
      const res = await fetch(`/api/ccrs/audit/export?${params.toString()}`);
      if (await handleApiError(res)) return;
      const data = (await res.json()) as AuditEntry[];
      const countHeader = res.headers.get('X-Total-Count');
      setEntries(data);
      setTotalCount(countHeader ? Number(countHeader) : 0);
      setOffset(nextOffset);
    } catch (e) {
      toast.error('Failed to load audit entries');
    } finally {
      setLoading(false);
    }
  }

  async function exportJson() {
    const params = buildParams(0, 10_000);
    const res = await fetch(`/api/ccrs/audit/export?${params.toString()}`);
    if (await handleApiError(res)) return;
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
  const end = Math.min(offset + entries.length, totalCount);

  return (
    <RoleGuard allowed={['System Admin', 'Legal', 'Audit']}>
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
            <Button onClick={() => loadAudit(0)} disabled={loading}>{loading ? 'Applying…' : 'Apply filters'}</Button>
          </div>
        </CardContent>
      </Card>

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      ) : (
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
            {entries.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-sm text-muted-foreground">
                  No audit entries found.
                </TableCell>
              </TableRow>
            ) : (
              entries.map((entry) => (
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
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          Showing {start}-{end} of {totalCount}
        </p>
        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={offset === 0}
            onClick={() => loadAudit(Math.max(0, offset - PAGE_SIZE))}
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            disabled={offset + PAGE_SIZE >= totalCount}
            onClick={() => loadAudit(offset + PAGE_SIZE)}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
    </RoleGuard>
  );
}
