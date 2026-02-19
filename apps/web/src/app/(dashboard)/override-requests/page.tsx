'use client';

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { RoleGuard } from '@/components/role-guard';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { handleApiError } from '@/lib/api-error';

interface OverrideRequest {
  id: string;
  counterparty_id: string;
  contract_title: string;
  requested_by_email: string;
  reason: string;
  status: string;
  created_at: string;
  counterparties?: { legal_name: string | null; status: string | null };
}

export default function OverrideRequestsPage() {
  const [requests, setRequests] = useState<OverrideRequest[]>([]);
  const [loading, setLoading] = useState(true);

  const [decisionOpen, setDecisionOpen] = useState(false);
  const [decisionId, setDecisionId] = useState<string | null>(null);
  const [decision, setDecision] = useState<'approved' | 'rejected'>('approved');
  const [comment, setComment] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function loadRequests() {
    setLoading(true);
    try {
      const res = await fetch('/api/ccrs/override-requests?limit=50');
      if (await handleApiError(res)) return;
      setRequests(await res.json());
    } catch (e) {
      toast.error('Failed to load override requests');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadRequests();
  }, []);

  function openDecision(id: string, nextDecision: 'approved' | 'rejected') {
    setDecisionId(id);
    setDecision(nextDecision);
    setComment('');
    setDecisionOpen(true);
  }

  async function submitDecision() {
    if (!decisionId) return;
    setSubmitting(true);
    try {
      const res = await fetch(`/api/ccrs/override-requests/${decisionId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decision, comment: comment || undefined }),
      });
      if (await handleApiError(res)) return;
      setRequests((prev) => prev.filter((r) => r.id !== decisionId));
      setDecisionOpen(false);
      toast.success(`Override request ${decision}`);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <RoleGuard allowed={['System Admin', 'Legal']}>
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Override Requests</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
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
                <TableHead>Counterparty</TableHead>
                <TableHead>Contract Title</TableHead>
                <TableHead>Requester</TableHead>
                <TableHead>Reason</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {requests.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-sm text-muted-foreground">
                    No pending override requests.
                  </TableCell>
                </TableRow>
              ) : (
                requests.map((req) => (
                  <TableRow key={req.id}>
                    <TableCell>{req.counterparties?.legal_name ?? req.counterparty_id}</TableCell>
                    <TableCell>{req.contract_title}</TableCell>
                    <TableCell>{req.requested_by_email}</TableCell>
                    <TableCell className="max-w-[280px] truncate">{req.reason}</TableCell>
                    <TableCell>{new Date(req.created_at).toLocaleDateString()}</TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-2">
                        <Button size="sm" onClick={() => openDecision(req.id, 'approved')}>
                          Approve
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => openDecision(req.id, 'rejected')}>
                          Reject
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
          )}
        </CardContent>
      </Card>

      <Dialog open={decisionOpen} onOpenChange={setDecisionOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{decision === 'approved' ? 'Approve request' : 'Reject request'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="decision-comment">Comment (optional)</Label>
            <Textarea
              id="decision-comment"
              value={comment}
              onChange={(e) => setComment(e.target.value)}
            />
          </div>
          <DialogFooter>
            <Button onClick={submitDecision} disabled={submitting}>
              {submitting ? 'Saving…' : 'Submit'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
    </RoleGuard>
  );
}
