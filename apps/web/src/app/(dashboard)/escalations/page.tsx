'use client';

import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { RoleGuard } from '@/components/role-guard';
import { handleApiError } from '@/lib/api-error';

interface EscalationEvent {
  id: string;
  contract_id: string;
  stage_name: string;
  tier: number;
  escalated_at: string;
  contracts?: { id: string; title: string | null };
  workflow_instances?: { id: string; current_stage: string | null };
}

export default function EscalationsPage() {
  const [events, setEvents] = useState<EscalationEvent[]>([]);
  const [loading, setLoading] = useState(true);

  async function loadEscalations() {
    setLoading(true);
    try {
      const res = await fetch('/api/ccrs/escalations/active');
      if (await handleApiError(res)) return;
      setEvents(await res.json());
    } catch (e) {
      toast.error('Failed to load escalations');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadEscalations();
    const timer = setInterval(() => {
      void loadEscalations();
    }, 60_000);
    return () => clearInterval(timer);
  }, []);

  async function resolveEscalation(id: string) {
    const res = await fetch(`/api/ccrs/escalations/${id}/resolve`, { method: 'POST' });
    if (await handleApiError(res)) return;
    setEvents((prev) => prev.filter((e) => e.id !== id));
    toast.success('Escalation resolved');
  }

  const rows = useMemo(() => {
    const now = Date.now();
    return events.map((e) => {
      const escalatedAt = new Date(e.escalated_at).getTime();
      const hoursBreached = Math.max(0, (now - escalatedAt) / 36e5);
      return { ...e, hoursBreached: hoursBreached.toFixed(1) };
    });
  }, [events]);

  function tierBadge(tier: number) {
    if (tier >= 3) return <Badge className="bg-red-500 text-white">Tier {tier}</Badge>;
    if (tier === 2) return <Badge className="bg-orange-500 text-white">Tier 2</Badge>;
    return <Badge className="bg-yellow-500 text-black">Tier 1</Badge>;
  }

  return (
    <RoleGuard allowed={['System Admin', 'Legal']}>
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Active Escalations</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {loading && (
            <div className="space-y-4">
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          )}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Contract</TableHead>
                <TableHead>Stage</TableHead>
                <TableHead>Tier</TableHead>
                <TableHead>Hours Breached</TableHead>
                <TableHead>Escalated At</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-sm text-muted-foreground">No active escalations.</TableCell>
                </TableRow>
              ) : (
                rows.map((e) => (
                  <TableRow key={e.id}>
                    <TableCell>{e.contracts?.title ?? e.contract_id}</TableCell>
                    <TableCell>{e.stage_name}</TableCell>
                    <TableCell>{tierBadge(e.tier)}</TableCell>
                    <TableCell>{e.hoursBreached}h</TableCell>
                    <TableCell>{new Date(e.escalated_at).toLocaleString()}</TableCell>
                    <TableCell>
                      <ConfirmDialog
                        trigger={
                          <Button size="sm" variant="outline">
                            Resolve
                          </Button>
                        }
                        title="Resolve escalation"
                        description="This will mark the escalation as resolved."
                        confirmLabel="Resolve"
                        onConfirm={() => resolveEscalation(e.id)}
                      />
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
    </RoleGuard>
  );
}
