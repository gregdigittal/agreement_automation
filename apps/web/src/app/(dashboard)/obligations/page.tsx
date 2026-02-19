'use client';

import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { handleApiError } from '@/lib/api-error';

interface Obligation {
  id: string;
  contract_id: string;
  obligation_type: string;
  description: string;
  due_date: string | null;
  recurrence: string | null;
  responsible_party: string | null;
  status: string;
}

export default function ObligationsPage() {
  const [obligations, setObligations] = useState<Obligation[]>([]);
  const [status, setStatus] = useState('');
  const [obligationType, setObligationType] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [limit, setLimit] = useState(50);
  const [offset, setOffset] = useState(0);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);

  async function loadObligations() {
    setLoading(true);
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (obligationType) params.set('obligation_type', obligationType);
    params.set('limit', String(limit));
    params.set('offset', String(offset));
    const res = await fetch(`/api/ccrs/obligations?${params.toString()}`);
    if (await handleApiError(res)) {
      setLoading(false);
      return;
    }
    const data = await res.json();
    const totalCount = res.headers.get('X-Total-Count');
    setTotal(totalCount ? Number(totalCount) : data.length);
    setObligations(data);
    setLoading(false);
  }

  useEffect(() => {
    void loadObligations();
  }, [status, obligationType, limit, offset]);

  const filtered = useMemo(() => {
    return obligations.filter((o) => {
      if (fromDate && o.due_date && o.due_date < fromDate) return false;
      if (toDate && o.due_date && o.due_date > toDate) return false;
      return true;
    });
  }, [obligations, fromDate, toDate]);

  async function updateStatus(id: string, nextStatus: string) {
    const res = await fetch(`/api/ccrs/obligations/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: nextStatus }),
    });
    if (await handleApiError(res)) return;
    setObligations((prev) => prev.map((o) => (o.id === id ? { ...o, status: nextStatus } : o)));
    toast.success('Obligation status updated');
  }

  function exportCsv() {
    const rows = [
      ['Contract ID', 'Type', 'Description', 'Due Date', 'Recurrence', 'Responsible', 'Status'],
      ...filtered.map((o) => [
        o.contract_id,
        o.obligation_type,
        o.description,
        o.due_date ?? '',
        o.recurrence ?? '',
        o.responsible_party ?? '',
        o.status,
      ]),
    ];
    const csv = rows.map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'obligations.csv';
    link.click();
    URL.revokeObjectURL(url);
  }

  const rangeStart = offset + 1;
  const rangeEnd = Math.min(offset + limit, total || offset + limit);

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Obligations Register</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-2 md:grid-cols-4">
            <Select value={status || 'all'} onValueChange={(value) => setStatus(value === 'all' ? '' : value)}>
              <SelectTrigger>
                <SelectValue placeholder="All statuses" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All statuses</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="waived">Waived</SelectItem>
                <SelectItem value="overdue">Overdue</SelectItem>
              </SelectContent>
            </Select>
            <Select value={obligationType || 'all'} onValueChange={(value) => setObligationType(value === 'all' ? '' : value)}>
              <SelectTrigger>
                <SelectValue placeholder="All types" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All types</SelectItem>
                <SelectItem value="reporting">Reporting</SelectItem>
                <SelectItem value="sla">SLA</SelectItem>
                <SelectItem value="insurance">Insurance</SelectItem>
                <SelectItem value="deliverable">Deliverable</SelectItem>
                <SelectItem value="payment">Payment</SelectItem>
                <SelectItem value="other">Other</SelectItem>
              </SelectContent>
            </Select>
            <Input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
            <Input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
          </div>

          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm text-muted-foreground">
              Showing {rangeStart}-{rangeEnd} of {total}
            </p>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm" onClick={exportCsv}>Export CSV</Button>
            </div>
          </div>

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
                <TableHead>Contract</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>Due Date</TableHead>
                <TableHead>Recurrence</TableHead>
                <TableHead>Responsible</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filtered.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-sm text-muted-foreground">
                    No obligations found.
                  </TableCell>
                </TableRow>
              ) : (
                filtered.map((o) => (
                  <TableRow key={o.id}>
                    <TableCell className="text-xs text-muted-foreground">{o.contract_id}</TableCell>
                    <TableCell>{o.obligation_type}</TableCell>
                    <TableCell>{o.description}</TableCell>
                    <TableCell>{o.due_date ?? '—'}</TableCell>
                    <TableCell>{o.recurrence ?? '—'}</TableCell>
                    <TableCell>{o.responsible_party ?? '—'}</TableCell>
                    <TableCell>
                      <Select value={o.status} onValueChange={(value) => updateStatus(o.id, value)}>
                        <SelectTrigger className="h-7 text-xs">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="active">Active</SelectItem>
                          <SelectItem value="completed">Completed</SelectItem>
                          <SelectItem value="waived">Waived</SelectItem>
                          <SelectItem value="overdue">Overdue</SelectItem>
                        </SelectContent>
                      </Select>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
          )}

          <div className="flex justify-between">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setOffset(Math.max(0, offset - limit))}
              disabled={offset === 0}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setOffset(offset + limit)}
              disabled={offset + limit >= total}
            >
              Next
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
