'use client';

import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

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
  const [error, setError] = useState<string | null>(null);
  const [limit, setLimit] = useState(50);
  const [offset, setOffset] = useState(0);
  const [total, setTotal] = useState(0);

  async function loadObligations() {
    setError(null);
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (obligationType) params.set('obligation_type', obligationType);
    params.set('limit', String(limit));
    params.set('offset', String(offset));
    const res = await fetch(`/api/ccrs/obligations?${params.toString()}`);
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    const data = await res.json();
    const totalCount = res.headers.get('X-Total-Count');
    setTotal(totalCount ? Number(totalCount) : data.length);
    setObligations(data);
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
    if (res.ok) {
      setObligations((prev) => prev.map((o) => (o.id === id ? { ...o, status: nextStatus } : o)));
    }
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
            <select
              className="rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={status}
              onChange={(e) => setStatus(e.target.value)}
            >
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="waived">Waived</option>
              <option value="overdue">Overdue</option>
            </select>
            <select
              className="rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={obligationType}
              onChange={(e) => setObligationType(e.target.value)}
            >
              <option value="">All types</option>
              <option value="reporting">Reporting</option>
              <option value="sla">SLA</option>
              <option value="insurance">Insurance</option>
              <option value="deliverable">Deliverable</option>
              <option value="payment">Payment</option>
              <option value="other">Other</option>
            </select>
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

          {error && <p className="text-sm text-destructive">{error}</p>}

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
                      <select
                        className="rounded-md border border-input bg-background px-2 py-1 text-xs"
                        value={o.status}
                        onChange={(e) => updateStatus(o.id, e.target.value)}
                      >
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="waived">Waived</option>
                        <option value="overdue">Overdue</option>
                      </select>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

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
